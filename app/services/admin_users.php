<?php
declare(strict_types=1);

/**
 * Starts the transaction, locks the given items (item rows always come first, like everywhere else),
 * then locks every active admin row and confirms the ACTING user is still one of them.
 * On failure it has already rolled back and returns ['err' => message].
 */
function admin_tx_begin(int $actorId, array $itemIds = []): array
{
    $pdo = db();
    $pdo->beginTransaction();
    foreach ($itemIds as $iid) {
        item_lock((int) $iid);
    }
    $admins = array_map('intval', $pdo->query(
        "SELECT user_id FROM users
         WHERE role = 'admin' AND account_status = 'active' AND verification_status = 'verified'
         ORDER BY user_id FOR UPDATE"
    )->fetchAll(PDO::FETCH_COLUMN));

    if (!in_array($actorId, $admins, true)) {
        $pdo->rollBack();
        return ['err' => 'Only active admins can do that.'];
    }
    return ['admins' => $admins];
}

function admin_lock_user(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT user_id, full_name, university_email, role, account_status, verification_status
         FROM users WHERE user_id = ? FOR UPDATE'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** True if this target is an active admin and there is nobody else (BR-14). */
function admin_is_last_active_admin(array $admins, array $target): bool
{
    return $target['role'] === 'admin'
        && in_array((int) $target['user_id'], $admins, true)
        && count($admins) === 1;
}

/* ---------- Suspend (BR-2, BR-14, BR-15) ---------- */

function admin_suspend_user(int $targetId, int $actorId, string $reason, int $days, ?int $reportId = null): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
        return ['ok' => false, 'message' => 'Write a reason of 3 to 255 characters.'];
    }
    if (!in_array($days, [0, 7, 30, 90], true)) {
        return ['ok' => false, 'message' => 'Choose how long the suspension lasts.'];
    }

    $pdo = db();
    // Plain read BEFORE the transaction: which items have this user's active claims? They are locked first.
    $stmt = $pdo->prepare("SELECT DISTINCT item_id FROM claims WHERE claimant_id = ? AND claim_status IN ('pending','under_review') ORDER BY item_id");
    $stmt->execute([$targetId]);
    $itemIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    try {
        $ctx = admin_tx_begin($actorId, $itemIds);
        if (isset($ctx['err'])) {
            return ['ok' => false, 'message' => $ctx['err']];
        }
        $target = admin_lock_user($targetId);
        if ($target === null) {
            return claim_fail($pdo, 'User not found.');
        }
        if ($target['account_status'] !== 'active') {
            return claim_fail($pdo, 'Only active accounts can be suspended. Reactivate first to change an existing suspension.');
        }
        if (admin_is_last_active_admin($ctx['admins'], $target)) {
            return claim_fail($pdo, 'The last active admin cannot be suspended.');
        }

        $until = $days === 0 ? 'NULL' : 'DATE_ADD(NOW(), INTERVAL ' . (int) $days . ' DAY)';   // $days is whitelisted above
        $st = $pdo->prepare(
            "UPDATE users SET account_status = 'suspended', suspended_until = $until, suspension_reason = ?
             WHERE user_id = ? AND account_status = 'active'"
        );
        $st->execute([$reason, $targetId]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, 'This account changed while you were working. Reload the page.');
        }

        // Withdraw the pending claims (BR-2). If one appeared on an item we did not lock, stop and let the admin retry.
        $cl = $pdo->prepare("SELECT claim_id, item_id FROM claims WHERE claimant_id = ? AND claim_status IN ('pending','under_review') ORDER BY claim_id FOR UPDATE");
        $cl->execute([$targetId]);
        $claims = $cl->fetchAll();
        foreach ($claims as $c) {
            if (!in_array((int) $c['item_id'], $itemIds, true)) {
                return claim_fail($pdo, 'The user filed a new claim just now. Please try again.');
            }
        }
        foreach ($claims as $c) {
            $w = $pdo->prepare("UPDATE claims SET claim_status = 'withdrawn' WHERE claim_id = ? AND claim_status IN ('pending','under_review')");
            $w->execute([$c['claim_id']]);
            audit_log(null, 'claim_withdrawn', 'claim', (int) $c['claim_id'],
                "system: claimant user $targetId was suspended by admin $actorId");
        }
        foreach ($itemIds as $iid) {
            item_reopen_if_idle($iid);
        }

        audit_log($actorId, 'user_suspended', 'user', $targetId,
            $reason . ($days === 0 ? ' (open-ended)' : " ($days days)") . '; claims withdrawn: ' . count($claims));
        if ($reportId !== null) {
            if ($err = report_close_in_tx($pdo, $reportId, $actorId, 'resolved', 'User suspended: ' . $reason, ['reported_user_id' => $targetId])) {
                return claim_fail($pdo, $err);
            }
        }
        $pdo->commit();

        return ['ok' => true, 'message' => 'Account suspended. Pending claims withdrawn: ' . count($claims) . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Reactivate ---------- */

function admin_reactivate_user(int $targetId, int $actorId): array
{
    $pdo = db();
    try {
        $ctx = admin_tx_begin($actorId);
        if (isset($ctx['err'])) {
            return ['ok' => false, 'message' => $ctx['err']];
        }
        $target = admin_lock_user($targetId);
        if ($target === null) {
            return claim_fail($pdo, 'User not found.');
        }
        $st = $pdo->prepare(
            "UPDATE users SET account_status = 'active', suspended_until = NULL, suspension_reason = NULL
             WHERE user_id = ? AND account_status = 'suspended'"
        );
        $st->execute([$targetId]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, 'This account is not suspended.');
        }
        audit_log($actorId, 'user_reactivated', 'user', $targetId, 'suspended -> active');
        $pdo->commit();
        return ['ok' => true, 'message' => 'Account reactivated.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Role change (BR-14, BR-15) ---------- */

function admin_change_role(int $targetId, int $actorId, string $newRole): array
{
    if (!in_array($newRole, ['student', 'custodian', 'admin'], true)) {
        return ['ok' => false, 'message' => 'Choose a valid role.'];
    }
    $pdo = db();
    try {
        $ctx = admin_tx_begin($actorId);
        if (isset($ctx['err'])) {
            return ['ok' => false, 'message' => $ctx['err']];
        }
        $target = admin_lock_user($targetId);
        if ($target === null) {
            return claim_fail($pdo, 'User not found.');
        }
        if ($target['account_status'] === 'deactivated') {
            return claim_fail($pdo, 'Deactivated accounts cannot change role.');
        }
        if ($target['role'] === $newRole) {
            return claim_fail($pdo, 'That user already has this role.');
        }
        if ($newRole !== 'admin' && admin_is_last_active_admin($ctx['admins'], $target)) {
            return claim_fail($pdo, 'The last active admin cannot be demoted.');
        }

        $st = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ? AND role = ?');
        $st->execute([$newRole, $targetId, $target['role']]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, 'This account changed while you were working. Reload the page.');
        }
        audit_log($actorId, 'role_changed', 'user', $targetId, $target['role'] . ' -> ' . $newRole);
        $pdo->commit();
        return ['ok' => true, 'message' => 'Role changed to ' . $newRole . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Create a custodian (BR-14) ---------- */

function admin_create_custodian(int $actorId, string $name, string $email, string $passwordHash): array
{
    $pdo = db();
    try {
        $ctx = admin_tx_begin($actorId);
        if (isset($ctx['err'])) {
            return ['ok' => false, 'message' => $ctx['err']];
        }
        $st = $pdo->prepare("INSERT INTO users (full_name, university_email, password_hash, role) VALUES (?, ?, ?, 'custodian')");
        $st->execute([$name, $email, $passwordHash]);
        $userId = (int) $pdo->lastInsertId();
        $token  = create_verification_token($userId);
        audit_log($actorId, 'role_changed', 'user', $userId, 'account created as custodian');
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'message' => 'This email is already registered.'];
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    rate_hit('verification_email', 'verify:user:' . $userId);
    send_verification_email($email, $name, $token);
    return ['ok' => true, 'message' => 'Custodian account created. A verification link was sent to ' . $email . '.', 'user_id' => $userId];
}