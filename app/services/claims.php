<?php
declare(strict_types=1);

function claim_fail(PDO $pdo, string $message): array
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    return ['ok' => false, 'message' => $message];
}

const CLAIM_STALE = 'This claim is out of date. Another decision was already made, so reload the page.';

/* ---------- Submit (FR-11) ---------- */

function claim_create(int $itemId, int $claimantId, string $details, ?string $proofFile, string $proofDesc): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $item = item_lock($itemId);

        if ($item === null || $item['report_type'] !== 'found'
            || !in_array($item['item_status'], ITEM_PUBLIC_STATUSES, true)) {
            return claim_fail($pdo, 'This item is not open for claims.');
        }
        if ((int) $item['reporter_id'] === $claimantId) {
            return claim_fail($pdo, 'You cannot claim your own listing.');
        }

        $claimId = claim_insert($itemId, $claimantId, $details);     // UNIQUE (item_id, claimant_id) is the final guard
        if ($proofFile !== null) {
            evidence_proof_add($itemId, $claimId, $claimantId, $proofDesc, $proofFile);
        }
        if ($item['item_status'] === 'open') {
            $st = $pdo->prepare("UPDATE items SET item_status = 'claim_pending' WHERE item_id = ? AND item_status = 'open'");
            $st->execute([$itemId]);
            if ($st->rowCount() !== 1) {
                throw new RuntimeException('Item status changed unexpectedly.');
            }
        }

        audit_log($claimantId, 'claim_submitted', 'claim', $claimId);

        $title = (string) $item['title'];
        notify((int) $item['reporter_id'], 'claim_submitted', $itemId, "Someone submitted a claim on your found item \"$title\".");
        foreach (staff_user_ids() as $staffId) {
            $staffId = (int) $staffId;
            if ($staffId !== $claimantId && $staffId !== (int) $item['reporter_id']) {
                notify($staffId, 'claim_submitted', $itemId, "New claim to review for \"$title\".");
            }
        }

        $pdo->commit();
        return ['ok' => true, 'message' => 'Your claim was submitted.', 'claim_id' => $claimId];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'message' => 'You have already submitted a claim for this item.'];
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Withdraw (FR-12) ---------- */

function claim_withdraw(int $claimId, int $userId): array
{
    $itemId = claim_lookup_item_id($claimId);
    if ($itemId === null) {
        return ['ok' => false, 'message' => 'Claim not found.'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        item_lock($itemId);                                          // item first, then claim (fixed order)
        $claim = claim_lock($claimId);
        if ($claim === null || (int) $claim['claimant_id'] !== $userId) {
            return claim_fail($pdo, 'Claim not found.');
        }

        $st = $pdo->prepare(
            "UPDATE claims SET claim_status = 'withdrawn'
             WHERE claim_id = ? AND claimant_id = ? AND claim_status IN ('pending','under_review')"
        );
        $st->execute([$claimId, $userId]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, 'This claim can no longer be withdrawn.');
        }

        audit_log($userId, 'claim_withdrawn', 'claim', $claimId, 'withdrawn by claimant');
        item_reopen_if_idle($itemId);
        $pdo->commit();
        return ['ok' => true, 'message' => 'Your claim was withdrawn.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Review ---------- */

/** Checks shared by approve and reject. Returns an error message or null. Runs AFTER the item and claim are locked. */
function claim_review_guard(array $reviewer, ?array $item, ?array $claim, int $itemId): ?string
{
    if ($item === null || $claim === null || (int) $claim['item_id'] !== $itemId
        || $item['report_type'] !== 'found'
        || !in_array($item['item_status'], ITEM_PUBLIC_STATUSES, true)
        || !in_array($claim['claim_status'], ['pending', 'under_review'], true)) {
        return CLAIM_STALE;
    }
    if ((int) $item['reporter_id'] === (int) $reviewer['user_id']) {
        return 'You posted this item, so you cannot review its claims.';
    }
    if ((int) $claim['claimant_id'] === (int) $reviewer['user_id']) {
        return 'You cannot review your own claim.';
    }
    return null;
}

/** Re-reads the reviewer from the database: must be an active, verified custodian or admin (BR-1, BR-6). */
function claim_load_reviewer(int $reviewerId, string $reason): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 1000) {
        return [null, 'Write a reason of 3 to 1000 characters.'];
    }
    $reviewer = user_find_by_id($reviewerId);
    if ($reviewer === null || !in_array($reviewer['role'], ['custodian', 'admin'], true) || !user_can_act($reviewer)) {
        return [null, 'Only active custodians and admins can review claims.'];
    }
    return [$reviewer, null];
}

function claim_reject(int $claimId, int $reviewerId, string $reason): array
{
    [$reviewer, $err] = claim_load_reviewer($reviewerId, $reason);
    if ($reviewer === null) {
        return ['ok' => false, 'message' => $err];
    }
    $reason = trim($reason);
    $itemId = claim_lookup_item_id($claimId);
    if ($itemId === null) {
        return ['ok' => false, 'message' => 'Claim not found.'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $item  = item_lock($itemId);
        $claim = claim_lock($claimId);
        if ($err = claim_review_guard($reviewer, $item, $claim, $itemId)) {
            return claim_fail($pdo, $err);
        }

        $st = $pdo->prepare(
            "UPDATE claims SET claim_status = 'rejected', reviewed_by = ?, review_reason = ?, reviewed_at = NOW()
             WHERE claim_id = ? AND claim_status IN ('pending','under_review')"
        );
        $st->execute([$reviewerId, $reason, $claimId]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, CLAIM_STALE);
        }

        audit_log($reviewerId, 'claim_rejected', 'claim', $claimId, $reason);
        notify((int) $claim['claimant_id'], 'claim_rejected', $itemId, 'Your claim on "' . $item['title'] . '" was rejected. Open the claim to read the reason.');
        item_reopen_if_idle($itemId);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Claim rejected.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Approves one claim. The numbered comments are the steps from the project documentation (Section 3).
 * Two reviewers acting at once cannot both succeed: the second waits at the item lock, then finds the claim changed.
 */
function claim_approve(int $claimId, int $reviewerId, string $reason, bool $highValue): array
{
    [$reviewer, $err] = claim_load_reviewer($reviewerId, $reason);
    if ($reviewer === null) {
        return ['ok' => false, 'message' => $err];
    }
    $reason = trim($reason);
    $itemId = claim_lookup_item_id($claimId);
    if ($itemId === null) {
        return ['ok' => false, 'message' => 'Claim not found.'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();                                    // 1. begin
        $item  = item_lock($itemId);                                 // 1. lock the item (SELECT ... FOR UPDATE)
        $claim = claim_lock($claimId);

        if ($err = claim_review_guard($reviewer, $item, $claim, $itemId)) {   // 2. found item, open/claim_pending,
            return claim_fail($pdo, $err);                                    //    reviewer is not claimant or poster
        }

        $others = $pdo->prepare(
            "SELECT claim_id, claimant_id FROM claims
             WHERE item_id = ? AND claim_id <> ? AND claim_status IN ('pending','under_review') FOR UPDATE"
        );
        $others->execute([$itemId, $claimId]);
        $rejectedClaims = $others->fetchAll();

        $st = $pdo->prepare(                                         // 3. approve only if still pending/under_review
            "UPDATE claims SET claim_status = 'approved', reviewed_by = ?, review_reason = ?, reviewed_at = NOW()
             WHERE claim_id = ? AND claim_status IN ('pending','under_review')"
        );
        $st->execute([$reviewerId, $reason, $claimId]);
        if ($st->rowCount() !== 1) {                                 // 3. exactly one affected row
            return claim_fail($pdo, CLAIM_STALE);
        }

        $st = $pdo->prepare(                                         // 4. reject all other active claims
            "UPDATE claims SET claim_status = 'rejected', reviewed_by = IF(claimant_id = ?, NULL, ?),
                    review_reason = 'another claim approved', reviewed_at = NOW()
             WHERE item_id = ? AND claim_id <> ? AND claim_status IN ('pending','under_review')"
        );
        $st->execute([$reviewerId, $reviewerId, $itemId, $claimId]);

        $st = $pdo->prepare(                                         // 5. item becomes ready_for_handover
            "UPDATE items SET item_status = 'ready_for_handover', is_high_value = ?
             WHERE item_id = ? AND item_status IN ('open','claim_pending')"
        );
        $st->execute([$highValue ? 1 : 0, $itemId]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, CLAIM_STALE);
        }

        $title = (string) $item['title'];
        audit_log($reviewerId, 'claim_approved', 'claim', $claimId, $reason);
        audit_log($reviewerId, 'item_status_changed', 'item', $itemId,
            $item['item_status'] . ' -> ready_for_handover' . ($highValue ? ' (marked high value)' : ''));
        notify((int) $claim['claimant_id'], 'claim_approved', $itemId, "Your claim on \"$title\" was approved. A custodian will arrange the handover.");

        foreach ($rejectedClaims as $r) {
            audit_log($reviewerId, 'claim_rejected', 'claim', (int) $r['claim_id'], 'another claim approved');
            notify((int) $r['claimant_id'], 'claim_rejected', $itemId, "Your claim on \"$title\" was rejected because another claim was approved.");
        }

        $pdo->commit();                                              // 5. commit
        return ['ok' => true, 'message' => 'Claim approved. The other claims on this item were rejected.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {                                 // anything unexpected: roll everything back
            $pdo->rollBack();
        }
        throw $e;
    }
}