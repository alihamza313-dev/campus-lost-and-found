<?php
declare(strict_types=1);

/** Plain read, used BEFORE a transaction starts. */
function claim_lookup_item_id(int $claimId): ?int
{
    $stmt = db()->prepare('SELECT item_id FROM claims WHERE claim_id = ?');
    $stmt->execute([$claimId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int) $v;
}

/** SELECT ... FOR UPDATE on the item row (step 1 of the approval transaction). */
function item_lock(int $itemId): ?array
{
    $stmt = db()->prepare(
        'SELECT item_id, reporter_id, report_type, title, item_status, is_high_value
         FROM items WHERE item_id = ? FOR UPDATE'
    );
    $stmt->execute([$itemId]);
    return $stmt->fetch() ?: null;
}

function claim_lock(int $claimId): ?array
{
    $stmt = db()->prepare(
        'SELECT claim_id, item_id, claimant_id, claim_status FROM claims WHERE claim_id = ? FOR UPDATE'
    );
    $stmt->execute([$claimId]);
    return $stmt->fetch() ?: null;
}

function claim_insert(int $itemId, int $claimantId, string $details): int
{
    $stmt = db()->prepare('INSERT INTO claims (item_id, claimant_id, ownership_details) VALUES (?, ?, ?)');
    $stmt->execute([$itemId, $claimantId, $details]);
    return (int) db()->lastInsertId();
}

/** The user's own claim on this item, if any. */
function claim_has(int $itemId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT claim_id, claim_status FROM claims WHERE item_id = ? AND claimant_id = ?');
    $stmt->execute([$itemId, $userId]);
    return $stmt->fetch() ?: null;
}

function claim_active_count(int $itemId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM claims WHERE item_id = ? AND claim_status IN ('pending','under_review','approved')"
    );
    $stmt->execute([$itemId]);
    return (int) $stmt->fetchColumn();
}

/** claim_pending -> open when the last active claim is gone. Call inside the transaction, after locking the item. */
function item_reopen_if_idle(int $itemId): void
{
    if (claim_active_count($itemId) === 0) {
        $stmt = db()->prepare("UPDATE items SET item_status = 'open' WHERE item_id = ? AND item_status = 'claim_pending'");
        $stmt->execute([$itemId]);
    }
}

/** Reviewer opened the item: pending claims become under_review. */
function claim_mark_under_review(int $itemId): void
{
    $stmt = db()->prepare("UPDATE claims SET claim_status = 'under_review' WHERE item_id = ? AND claim_status = 'pending'");
    $stmt->execute([$itemId]);
}

function claims_by_claimant(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT c.claim_id, c.claim_status, c.created_at, i.item_id, i.title, i.item_status
         FROM claims c JOIN items i ON i.item_id = c.item_id
         WHERE c.claimant_id = ? ORDER BY c.created_at DESC, c.claim_id DESC LIMIT 100'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function claim_find_detail(int $claimId): ?array
{
    $stmt = db()->prepare(
        'SELECT c.claim_id, c.item_id, c.claimant_id, c.ownership_details, c.claim_status,
                c.review_reason, c.reviewed_at, c.created_at,
                i.title, i.item_status, i.report_type, i.reporter_id,
                u.full_name AS claimant_name, u.university_email AS claimant_email
         FROM claims c
         JOIN items i ON i.item_id = c.item_id
         JOIN users u ON u.user_id = c.claimant_id
         WHERE c.claim_id = ?'
    );
    $stmt->execute([$claimId]);
    return $stmt->fetch() ?: null;
}

function claim_proof_id(int $claimId): ?int
{
    $stmt = db()->prepare(
        "SELECT evidence_id FROM private_item_evidence
         WHERE claim_id = ? AND evidence_type = 'claim_proof' ORDER BY evidence_id LIMIT 1"
    );
    $stmt->execute([$claimId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int) $v;
}

/** All claims on one item for the reviewer (every status), each with its proof id if any. */
function claims_for_review(int $itemId): array
{
    $stmt = db()->prepare(
        "SELECT c.claim_id, c.claimant_id, c.ownership_details, c.claim_status, c.review_reason,
                c.reviewed_at, c.created_at, u.full_name AS claimant_name, u.university_email AS claimant_email,
                (SELECT p.evidence_id FROM private_item_evidence p
                  WHERE p.claim_id = c.claim_id AND p.evidence_type = 'claim_proof'
                  ORDER BY p.evidence_id LIMIT 1) AS proof_id
         FROM claims c JOIN users u ON u.user_id = c.claimant_id
         WHERE c.item_id = ? ORDER BY c.created_at, c.claim_id"
    );
    $stmt->execute([$itemId]);
    return $stmt->fetchAll();
}

function evidence_item_detail_id(int $itemId): ?int
{
    $stmt = db()->prepare(
        "SELECT evidence_id FROM private_item_evidence
         WHERE item_id = ? AND evidence_type = 'item_detail' ORDER BY evidence_id DESC LIMIT 1"
    );
    $stmt->execute([$itemId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int) $v;
}

function evidence_proof_add(int $itemId, int $claimId, int $userId, string $description, string $fileName): void
{
    $stmt = db()->prepare(
        "INSERT INTO private_item_evidence (item_id, claim_id, evidence_type, submitted_by, private_description, file_reference)
         VALUES (?, ?, 'claim_proof', ?, ?, ?)"
    );
    $stmt->execute([$itemId, $claimId, $userId, $description, $fileName]);
}

/** One evidence row with the facts the access check needs. */
function evidence_find(int $evidenceId): ?array
{
    $stmt = db()->prepare(
        'SELECT e.evidence_id, e.item_id, e.claim_id, e.evidence_type, e.private_description,
                e.file_reference, e.created_at, i.reporter_id, i.title AS item_title, c.claimant_id
         FROM private_item_evidence e
         JOIN items i ON i.item_id = e.item_id
         LEFT JOIN claims c ON c.claim_id = e.claim_id
         WHERE e.evidence_id = ?'
    );
    $stmt->execute([$evidenceId]);
    return $stmt->fetch() ?: null;
}

/** Found items with active claims, oldest waiting first. Items the reviewer posted are left out. */
function review_queue(int $reviewerId): array
{
    $stmt = db()->prepare(
        "SELECT i.item_id, i.title, i.item_status, i.is_high_value,
                COUNT(c.claim_id) AS active_claims, MIN(c.created_at) AS oldest
         FROM items i
         JOIN claims c ON c.item_id = i.item_id AND c.claim_status IN ('pending','under_review')
         WHERE i.report_type = 'found' AND i.reporter_id <> ?
         GROUP BY i.item_id, i.title, i.item_status, i.is_high_value
         ORDER BY oldest ASC LIMIT 100"
    );
    $stmt->execute([$reviewerId]);
    return $stmt->fetchAll();
}

function staff_user_ids(): array
{
    return db()->query(
        "SELECT user_id FROM users
         WHERE role IN ('custodian','admin') AND verification_status = 'verified' AND account_status = 'active'"
    )->fetchAll(PDO::FETCH_COLUMN);
}