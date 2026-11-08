<?php
declare(strict_types=1);

/**
 * Removes a listing (any status except already removed). Active claims are rejected, a scheduled handover is
 * cancelled, everyone affected is notified, and (optionally) the report is resolved, all in ONE transaction.
 */
function moderate_remove_item(int $itemId, int $actorId, string $reason, ?int $reportId = null): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
        return ['ok' => false, 'message' => 'Write a reason of 3 to 255 characters.'];
    }
    $pdo = db();
    try {
        $ctx = admin_tx_begin($actorId, [$itemId]);               // item first, then the admin rows
        if (isset($ctx['err'])) {
            return ['ok' => false, 'message' => $ctx['err']];
        }
        $item = item_lock($itemId);
        if ($item === null) {
            return claim_fail($pdo, 'Listing not found.');
        }
        if ($item['item_status'] === 'removed') {
            return claim_fail($pdo, 'This listing is already removed.');
        }

        $cl = $pdo->prepare(
            "SELECT claim_id, claimant_id, claim_status FROM claims
             WHERE item_id = ? AND claim_status IN ('pending','under_review','approved') ORDER BY claim_id FOR UPDATE"
        );
        $cl->execute([$itemId]);
        $title = (string) $item['title'];

        foreach ($cl->fetchAll() as $c) {
            if ($c['claim_status'] === 'approved') {
                $h = handover_lock_by_claim((int) $c['claim_id']);
                if ($h !== null && $h['handover_status'] === 'scheduled') {
                    $pdo->prepare("UPDATE handovers SET handover_status = 'cancelled' WHERE handover_id = ? AND handover_status = 'scheduled'")
                        ->execute([$h['handover_id']]);
                    audit_log($actorId, 'handover_cancelled', 'handover', (int) $h['handover_id'], 'listing removed');
                }
            }
            $pdo->prepare(
                "UPDATE claims SET claim_status = 'rejected', reviewed_by = IF(claimant_id = ?, NULL, ?),
                        review_reason = 'listing removed by a moderator', reviewed_at = NOW()
                 WHERE claim_id = ? AND claim_status IN ('pending','under_review','approved')"
            )->execute([$actorId, $actorId, $c['claim_id']]);
            audit_log($actorId, 'claim_rejected', 'claim', (int) $c['claim_id'], 'listing removed by a moderator');
            notify((int) $c['claimant_id'], 'claim_rejected', $itemId, "Your claim on \"$title\" was closed because the listing was removed.");
        }

        $st = $pdo->prepare("UPDATE items SET item_status = 'removed' WHERE item_id = ? AND item_status <> 'removed'");
        $st->execute([$itemId]);
        if ($st->rowCount() !== 1) {
            return claim_fail($pdo, 'This listing changed while you were working. Reload the page.');
        }
        audit_log($actorId, 'item_removed', 'item', $itemId, $item['item_status'] . ' -> removed: ' . $reason);
        if ((int) $item['reporter_id'] !== $actorId) {
            notify((int) $item['reporter_id'], 'report_update', $itemId, "Your listing \"$title\" was removed by a moderator.");
        }

        if ($reportId !== null) {
            if ($err = report_close_in_tx($pdo, $reportId, $actorId, 'resolved', 'Listing removed: ' . $reason, ['item_id' => $itemId])) {
                return claim_fail($pdo, $err);
            }
        }
        $pdo->commit();
        return ['ok' => true, 'message' => 'Listing removed.' . ($reportId !== null ? ' The report was resolved.' : '')];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Marks a message as removed by a moderator, optionally resolving the report in the same transaction. */
function moderate_remove_message(int $messageId, int $actorId, string $reason, ?int $reportId = null): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
        return ['ok' => false, 'message' => 'Write a reason of 3 to 255 characters.'];
    }
    $stmt = db()->prepare('SELECT item_id FROM messages WHERE message_id = ?');   // plain read BEFORE the transaction
    $stmt->execute([$messageId]);
    $itemId = $stmt->fetchColumn();
    if ($itemId === false) {
        return ['ok' => false, 'message' => 'Message not found.'];
    }

    $pdo = db();
    try {
        $ctx = admin_tx_begin($actorId, [(int) $itemId]);
        if (isset($ctx['err'])) {
            return ['ok' => false, 'message' => $ctx['err']];
        }
        $st = $pdo->prepare('SELECT message_id, sender_id, item_id, message_status FROM messages WHERE message_id = ? FOR UPDATE');
        $st->execute([$messageId]);
        $m = $st->fetch();
        if (!$m) {
            return claim_fail($pdo, 'Message not found.');
        }
        $up = $pdo->prepare("UPDATE messages SET message_status = 'removed' WHERE message_id = ? AND message_status IN ('sent','read')");
        $up->execute([$messageId]);
        if ($up->rowCount() !== 1) {
            return claim_fail($pdo, 'This message was already removed.');
        }
        audit_log($actorId, 'message_removed', 'message', $messageId, $reason);
        if ((int) $m['sender_id'] !== $actorId) {
            notify((int) $m['sender_id'], 'report_update', (int) $m['item_id'], 'One of your messages was removed by a moderator.');
        }
        if ($reportId !== null) {
            if ($err = report_close_in_tx($pdo, $reportId, $actorId, 'resolved', 'Message removed: ' . $reason, ['message_id' => $messageId])) {
                return claim_fail($pdo, $err);
            }
        }
        $pdo->commit();
        return ['ok' => true, 'message' => 'Message removed.' . ($reportId !== null ? ' The report was resolved.' : '')];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}