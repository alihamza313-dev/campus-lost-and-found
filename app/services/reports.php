<?php
declare(strict_types=1);

function report_kind(array $r): string
{
    if ($r['message_id'] !== null) { return 'message'; }
    if ($r['item_id'] !== null)    { return 'item'; }
    return 'user';
}

/** True if this admin is a subject of the report: reported user, listing poster or message sender (BR-14). */
function report_involves(array $r, int $adminId): bool
{
    foreach (['reported_user_id', 'item_poster_id', 'message_sender_id'] as $k) {
        if ($r[$k] !== null && (int) $r[$k] === $adminId) {
            return true;
        }
    }
    return false;
}

/** Notifies every other active admin about a new report. Call inside the creating flow. */
function report_notify_admins(int $reporterId, ?int $itemId, string $text): void
{
    $ids = db()->query(
        "SELECT user_id FROM users WHERE role = 'admin' AND verification_status = 'verified' AND account_status = 'active'"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        if ((int) $id !== $reporterId) {
            notify((int) $id, 'report_update', $itemId, $text);
        }
    }
}

/**
 * Moves a report from under_review to resolved/dismissed. Runs INSIDE an open transaction where the acting
 * admin was already verified and locked (admin_tx_begin). Returns an error message or null.
 * $expect pins the report to the target the caller is acting on, e.g. ['item_id' => 5].
 */
function report_close_in_tx(PDO $pdo, int $reportId, int $actorId, string $status, string $notes, ?array $expect = null): ?string
{
    $st = $pdo->prepare(
        'SELECT report_id, reporter_id, reported_user_id, item_id, message_id, report_status
         FROM reports WHERE report_id = ? FOR UPDATE'
    );
    $st->execute([$reportId]);
    $r = $st->fetch();
    if (!$r) {
        return 'Report not found.';
    }
    if ($r['report_status'] !== 'under_review') {
        return 'Start the review of this report first, or it was already handled.';
    }
    foreach (($expect ?? []) as $k => $v) {
        if ((int) ($r[$k] ?? 0) !== $v) {
            return 'That action does not match this report.';
        }
    }

    // Subjects of the report (these columns never change after creation, so a plain read is safe here).
    $s = $pdo->prepare(
        'SELECT r.reported_user_id, i.reporter_id AS poster_id, m.sender_id
         FROM reports r LEFT JOIN items i ON i.item_id = r.item_id LEFT JOIN messages m ON m.message_id = r.message_id
         WHERE r.report_id = ?'
    );
    $s->execute([$reportId]);
    $subj = $s->fetch();
    foreach (['reported_user_id', 'poster_id', 'sender_id'] as $k) {
        if ($subj[$k] !== null && (int) $subj[$k] === $actorId) {
            return 'This report is about you, so another admin must handle it.';
        }
    }

    $up = $pdo->prepare(
        "UPDATE reports SET report_status = ?, moderator_id = ?, resolution_notes = ?, resolved_at = NOW()
         WHERE report_id = ? AND report_status = 'under_review'"
    );
    $up->execute([$status, $actorId, $notes, $reportId]);
    if ($up->rowCount() !== 1) {
        return 'This report is out of date. Reload the page.';
    }

    audit_log($actorId, $status === 'dismissed' ? 'report_dismissed' : 'report_resolved', 'report', $reportId, $notes);
    notify((int) $r['reporter_id'], 'report_update', $r['item_id'] !== null ? (int) $r['item_id'] : null,
        'Your report was ' . $status . '. Thank you for helping keep the platform safe.');
    return null;
}

/* ---------- Start review: open -> under_review ---------- */

function report_start_review(int $reportId, int $actorId): array
{
    $pdo = db();
    try {
        $ctx = admin_tx_begin($actorId);
        if (isset($ctx['err'])) {
            return ['ok' => false, 'message' => $ctx['err']];
        }
        $st = $pdo->prepare('SELECT report_id FROM reports WHERE report_id = ? FOR UPDATE');
        $st->execute([$reportId]);
        if (!$st->fetchColumn()) {
            return claim_fail($pdo, 'Report not found.');
        }
        $s = $pdo->prepare(
            'SELECT r.reported_user_id, i.reporter_id AS poster_id, m.sender_id
             FROM reports r LEFT JOIN items i ON i.item_id = r.item_id LEFT JOIN messages m ON m.message_id = r.message_id
             WHERE r.report_id = ?'
        );
        $s->execute([$reportId]);
        $subj = $s->fetch();
        foreach (['reported_user_id', 'poster_id', 'sender_id'] as $k) {
            if ($subj[$k] !== null && (int) $subj[$k] === $actorId) {
                return claim_fail($pdo, 'This report is about you, so another admin must handle it.');
            }
        }
        $up = $pdo->prepare(
            "UPDATE reports SET report_status = 'under_review', moderator_id = ?
             WHERE report_id = ? AND report_status = 'open'"
        );
        $up->execute([$actorId, $reportId]);
        if ($up->rowCount() !== 1) {
            return claim_fail($pdo, 'This report was already taken. Reload the page.');
        }
        audit_log($actorId, 'report_review_started', 'report', $reportId, 'open -> under_review');
        $pdo->commit();
        return ['ok' => true, 'message' => 'Review started.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* ---------- Dismiss, or resolve without further action ---------- */

function report_finish(int $reportId, int $actorId, string $status, string $notes): array
{
    $notes = trim($notes);
    if (!in_array($status, ['resolved', 'dismissed'], true)) {
        return ['ok' => false, 'message' => 'Invalid decision.'];
    }
    if (mb_strlen($notes) < 3 || mb_strlen($notes) > 1000) {
        return ['ok' => false, 'message' => 'Write notes of 3 to 1000 characters.'];
    }
    $pdo = db();
    try {
        $ctx = admin_tx_begin($actorId);
        if (isset($ctx['err'])) {
            return ['ok' => false, 'message' => $ctx['err']];
        }
        if ($err = report_close_in_tx($pdo, $reportId, $actorId, $status, $notes)) {
            return claim_fail($pdo, $err);
        }
        $pdo->commit();
        return ['ok' => true, 'message' => 'Report ' . $status . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}