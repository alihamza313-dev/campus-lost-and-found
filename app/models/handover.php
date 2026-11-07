<?php
declare(strict_types=1);

function handover_find_by_claim(int $claimId): ?array
{
    $stmt = db()->prepare(
        'SELECT handover_id, claim_id, custodian_id, handover_status, scheduled_at, completed_at, notes
         FROM handovers WHERE claim_id = ?'
    );
    $stmt->execute([$claimId]);
    return $stmt->fetch() ?: null;
}

/** Locks the handover row (call inside a transaction, after the item and claim are locked). */
function handover_lock_by_claim(int $claimId): ?array
{
    $stmt = db()->prepare(
        'SELECT handover_id, claim_id, custodian_id, handover_status, scheduled_at
         FROM handovers WHERE claim_id = ? FOR UPDATE'
    );
    $stmt->execute([$claimId]);
    return $stmt->fetch() ?: null;
}

function claim_count_all(int $itemId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM claims WHERE item_id = ?');
    $stmt->execute([$itemId]);
    return (int) $stmt->fetchColumn();
}

/** Everything the handover page needs about one claim. */
function handover_page_data(int $claimId): ?array
{
    $stmt = db()->prepare(
        'SELECT c.claim_id, c.item_id, c.claimant_id, c.claim_status, c.review_reason, c.reviewed_at,
                i.title, i.item_status, i.is_high_value, i.reporter_id, i.report_type,
                u.full_name AS claimant_name, u.university_email AS claimant_email,
                (SELECT COUNT(*) FROM claims x WHERE x.item_id = c.item_id) AS claims_filed
         FROM claims c
         JOIN items i ON i.item_id = c.item_id
         JOIN users u ON u.user_id = c.claimant_id
         WHERE c.claim_id = ?'
    );
    $stmt->execute([$claimId]);
    return $stmt->fetch() ?: null;
}

/** Approved claims whose item is waiting for handover, unscheduled ones first. */
function handovers_dashboard(int $staffId): array
{
    $stmt = db()->prepare(
        "SELECT c.claim_id, c.item_id, i.title, i.is_high_value, u.full_name AS claimant_name,
                h.handover_status, h.scheduled_at,
                (SELECT COUNT(*) FROM claims x WHERE x.item_id = i.item_id) AS claims_filed
         FROM claims c
         JOIN items i ON i.item_id = c.item_id
         JOIN users u ON u.user_id = c.claimant_id
         LEFT JOIN handovers h ON h.claim_id = c.claim_id
         WHERE c.claim_status = 'approved' AND i.item_status = 'ready_for_handover'
           AND i.reporter_id <> ? AND c.claimant_id <> ?
         ORDER BY (h.scheduled_at IS NULL) DESC, h.scheduled_at ASC, c.reviewed_at ASC
         LIMIT 100"
    );
    $stmt->execute([$staffId, $staffId]);
    return $stmt->fetchAll();
}