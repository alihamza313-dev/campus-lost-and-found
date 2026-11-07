<?php
declare(strict_types=1);

function now_local(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone', 'Asia/Karachi')));
}

/** "2026-11-10T14:30" (from a datetime-local input) -> "2026-11-10 14:30:00", or null if invalid or out of range. */
function handover_parse_datetime(string $raw): ?string
{
    $tz = new DateTimeZone((string) config('app.timezone', 'Asia/Karachi'));
    $d  = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, $tz);
    if ($d === false || $d->format('Y-m-d\TH:i') !== $raw) {
        return null;
    }
    $now = now_local();
    if ($d <= $now || $d > $now->modify('+60 days')) {
        return null;
    }
    return $d->format('Y-m-d H:i:s');
}

/**
 * Re-reads the actor, starts the transaction, locks item then claim, and checks the shared rules.
 * On failure it has already rolled back and returns ['err' => message].
 */
function handover_begin(int $claimId, int $actorId): array
{
    $actor = user_find_by_id($actorId);                     // plain reads BEFORE the transaction
    if ($actor === null || !in_array($actor['role'], ['custodian', 'admin'], true) || !user_can_act($actor)) {
        return ['err' => 'Only active custodians and admins can manage handovers.'];
    }
    $itemId = claim_lookup_item_id($claimId);
    if ($itemId === null) {
        return ['err' => 'Claim not found.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    $item  = item_lock($itemId);
    $claim = claim_lock($claimId);

    if ($item === null || $claim === null || (int) $claim['item_id'] !== $itemId
        || $item['report_type'] !== 'found'
        || $item['item_status'] !== 'ready_for_handover'
        || $claim['claim_status'] !== 'approved') {
        $pdo->rollBack();
        return ['err' => 'This claim is not waiting for a handover. Another action may already have changed it, so reload the page.'];
    }
    if ((int) $item['reporter_id'] === $actorId) {
        $pdo->rollBack();
        return ['err' => 'You posted this item, so another staff member must handle its handover.'];
    }
    if ((int) $claim['claimant_id'] === $actorId) {
        $pdo->rollBack();
        return ['err' => 'You cannot manage a handover for your own claim.'];
    }
    return ['actor' => $actor, 'item' => $item, 'claim' => $claim];
}

/* ---------- Schedule / reschedule (FR-16) ---------- */

function handover_schedule(int $claimId, int $actorId, string $rawWhen): array
{
    $when = handover_parse_datetime($rawWhen);
    if ($when === null) {
        return ['ok' => false, 'message' => 'Choose a date and time in the future, within the next 60 days.'];
    }
    $ctx = handover_begin($claimId, $actorId);
    if (isset($ctx['err'])) {
        return ['ok' => false, 'message' => $ctx['err']];
    }
    $pdo = db();
    try {
        $item  = $ctx['item'];
        $claim = $ctx['claim'];
        $h     = handover_lock_by_claim($claimId);

        if ($h === null) {
            $st = $pdo->prepare('INSERT INTO handovers (claim_id, custodian_id, scheduled_at) VALUES (?, ?, ?)');
            $st->execute([$claimId, $actorId, $when]);
            audit_log($actorId, 'handover_scheduled', 'handover', (int) $pdo->lastInsertId(), "at $when");
            $msg = 'Handover scheduled.';
        } elseif ($h['handover_status'] === 'scheduled') {
            $st = $pdo->prepare("UPDATE handovers SET scheduled_at = ?, custodian_id = ? WHERE handover_id = ? AND handover_status = 'scheduled'");
            $st->execute([$when, $actorId, $h['handover_id']]);
            audit_log($actorId, 'handover_rescheduled', 'handover', (int) $h['handover_id'], $h['scheduled_at'] . " -> $when");
            $msg = 'Handover rescheduled.';
        } else {
            return claim_fail($pdo, 'This handover is already finished and cannot be changed.');
        }

        notify((int) $claim['claimant_id'], 'handover_scheduled', (int) $item['item_id'],
            'Handover for "' . $item['title'] . '" is set for ' . substr($when, 0, 16) . '. Bring your student ID.');
        $pdo->commit();
        return ['ok' => true, 'message' => $msg];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Complete (FR-16, BR-8) ---------- */

function handover_complete(int $claimId, int $actorId, bool $idVerified, string $notes): array
{
    $notes = trim($notes);
    if (!$idVerified) {
        return ['ok' => false, 'message' => 'Tick the box to confirm you checked the claimant\'s ID in person.'];
    }
    if (mb_strlen($notes) > 255) {
        return ['ok' => false, 'message' => 'ID-check notes can be at most 255 characters.'];
    }
    $ctx = handover_begin($claimId, $actorId);
    if (isset($ctx['err'])) {
        return ['ok' => false, 'message' => $ctx['err']];
    }
    $pdo = db();
    try {
        $item  = $ctx['item'];
        $claim = $ctx['claim'];
        $itemId = (int) $item['item_id'];

        $h = handover_lock_by_claim($claimId);
        if ($h === null || $h['handover_status'] !== 'scheduled') {
            return claim_fail($pdo, 'Schedule the handover before completing it.');
        }

        // BR-8: high-value or disputed items need written ID-check notes.
        if (((int) $item['is_high_value'] === 1 || claim_count_all($itemId) > 1) && mb_strlen($notes) < 5) {
            return claim_fail($pdo, 'This item is high value or had several claims, so written ID-check notes are required (at least 5 characters).');
        }

        $st = $pdo->prepare(
            "UPDATE handovers SET handover_status = 'completed', completed_at = ?, notes = ?
             WHERE handover_id = ? AND handover_status = 'scheduled'"
        );
        $st->execute([now_local()->format('Y-m-d H:i:s'), $notes !== '' ? $notes : null, $h['handover_id']]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, 'This handover is out of date. Reload the page.');
        }

        $st = $pdo->prepare("UPDATE items SET item_status = 'recovered' WHERE item_id = ? AND item_status = 'ready_for_handover'");
        $st->execute([$itemId]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, 'This item is out of date. Reload the page.');
        }

        audit_log($actorId, 'handover_completed', 'handover', (int) $h['handover_id'], $notes !== '' ? $notes : 'ID checked in person');
        audit_log($actorId, 'item_status_changed', 'item', $itemId, 'ready_for_handover -> recovered');

        $title = (string) $item['title'];
        notify((int) $claim['claimant_id'], 'item_recovered', $itemId, "\"$title\" was handed over to you. Case closed.");
        notify((int) $item['reporter_id'], 'item_recovered', $itemId, "Your found item \"$title\" was returned to its owner. Thank you!");

        $pdo->commit();
        return ['ok' => true, 'message' => 'Handover completed. The item is marked as recovered.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Revoke approval, which also cancels any scheduled handover (FR-15, BR-9) ---------- */

function handover_revoke(int $claimId, int $actorId, string $reason): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 1000) {
        return ['ok' => false, 'message' => 'Write a reason of 3 to 1000 characters.'];
    }
    $ctx = handover_begin($claimId, $actorId);
    if (isset($ctx['err'])) {
        return ['ok' => false, 'message' => $ctx['err']];
    }
    $pdo = db();
    try {
        $item   = $ctx['item'];
        $claim  = $ctx['claim'];
        $itemId = (int) $item['item_id'];

        $h = handover_lock_by_claim($claimId);
        if ($h !== null && $h['handover_status'] === 'scheduled') {
            $st = $pdo->prepare("UPDATE handovers SET handover_status = 'cancelled' WHERE handover_id = ? AND handover_status = 'scheduled'");
            $st->execute([$h['handover_id']]);
            audit_log($actorId, 'handover_cancelled', 'handover', (int) $h['handover_id'], 'approval revoked');
        }

        $st = $pdo->prepare(
            "UPDATE claims SET claim_status = 'rejected', reviewed_by = ?, review_reason = ?, reviewed_at = NOW()
             WHERE claim_id = ? AND claim_status = 'approved'"
        );
        $st->execute([$actorId, 'Approval revoked: ' . $reason, $claimId]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, CLAIM_STALE);
        }

        $st = $pdo->prepare("UPDATE items SET item_status = 'open' WHERE item_id = ? AND item_status = 'ready_for_handover'");
        $st->execute([$itemId]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, CLAIM_STALE);
        }

        audit_log($actorId, 'claim_revoked', 'claim', $claimId, $reason);
        audit_log($actorId, 'item_status_changed', 'item', $itemId, 'ready_for_handover -> open');
        notify((int) $claim['claimant_id'], 'claim_rejected', $itemId,
            'The approval of your claim on "' . $item['title'] . '" was withdrawn. Open the claim to read the reason.');

        $pdo->commit();
        return ['ok' => true, 'message' => 'Approval revoked. The item is open again.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}