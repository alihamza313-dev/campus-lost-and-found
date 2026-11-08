<?php
declare(strict_types=1);

/* ---------- Reporter side ---------- */

/** A message the user RECEIVED and may report. Returns null otherwise. */
function message_for_report(int $messageId, int $userId): ?array
{
    $stmt = db()->prepare(
        "SELECT message_id, item_id, sender_id, SUBSTRING(message_text, 1, 120) AS excerpt
         FROM messages WHERE message_id = ? AND recipient_id = ? AND message_status <> 'removed'"
    );
    $stmt->execute([$messageId, $userId]);
    return $stmt->fetch() ?: null;
}

/** Context rule: you may report a user only if you exchanged messages or they have a public listing. */
function report_user_relation_ok(int $me, int $target): bool
{
    $stmt = db()->prepare(
        'SELECT 1 FROM messages WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?) LIMIT 1'
    );
    $stmt->execute([$me, $target, $target, $me]);
    if ($stmt->fetchColumn()) {
        return true;
    }
    $stmt = db()->prepare("SELECT 1 FROM items WHERE reporter_id = ? AND item_status IN ('open','claim_pending') LIMIT 1");
    $stmt->execute([$target]);
    return (bool) $stmt->fetchColumn();
}

/** True if this reporter already has a live report on exactly this target. <=> is NULL-safe equality. */
function report_live_exists(int $reporterId, ?int $userId, ?int $itemId, ?int $messageId): bool
{
    $stmt = db()->prepare(
        "SELECT 1 FROM reports
         WHERE reporter_id = ? AND reported_user_id <=> ? AND item_id <=> ? AND message_id <=> ?
           AND report_status IN ('open','under_review') LIMIT 1"
    );
    $stmt->execute([$reporterId, $userId, $itemId, $messageId]);
    return (bool) $stmt->fetchColumn();
}

function report_insert(int $reporterId, ?int $userId, ?int $itemId, ?int $messageId, string $reason, ?string $details): int
{
    $stmt = db()->prepare(
        'INSERT INTO reports (reporter_id, reported_user_id, item_id, message_id, reason, details)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$reporterId, $userId, $itemId, $messageId, $reason, $details]);
    return (int) db()->lastInsertId();
}

/* ---------- Admin side ---------- */

function reports_where(array $f): array
{
    $status = $f['status'];
    if ($status === '') {
        return ["r.report_status IN ('open','under_review')", [], 'ASC'];     // the work queue, oldest first
    }
    return ['r.report_status = ?', [$status], 'DESC'];
}

function reports_count(array $f): int
{
    [$w, $p] = reports_where($f);
    $stmt = db()->prepare("SELECT COUNT(*) FROM reports r WHERE $w");
    $stmt->execute($p);
    return (int) $stmt->fetchColumn();
}

function reports_page(array $f, int $page, int $perPage): array
{
    [$w, $p, $dir] = reports_where($f);                  // $dir is one of our own constants
    $perPage = max(1, $perPage);
    $offset  = max(0, ($page - 1) * $perPage);
    $stmt = db()->prepare(
        "SELECT r.report_id, r.reason, r.report_status, r.created_at, r.item_id, r.message_id,
                ru.full_name AS reporter_name, tu.full_name AS reported_name, i.title AS item_title
         FROM reports r
         JOIN users ru ON ru.user_id = r.reporter_id
         LEFT JOIN users tu ON tu.user_id = r.reported_user_id
         LEFT JOIN items i ON i.item_id = r.item_id
         WHERE $w ORDER BY r.report_id $dir LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($p);
    return $stmt->fetchAll();
}

function report_detail(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.report_id, r.reporter_id, r.reported_user_id, r.item_id, r.message_id, r.reason, r.details,
                r.report_status, r.moderator_id, r.resolution_notes, r.resolved_at, r.created_at,
                ru.full_name AS reporter_name, ru.university_email AS reporter_email,
                tu.full_name AS reported_name, tu.university_email AS reported_email,
                tu.account_status AS reported_account_status, tu.role AS reported_role,
                i.title AS item_title, i.item_status, i.reporter_id AS item_poster_id,
                m.message_text, m.message_status, m.sender_id AS message_sender_id,
                mu.full_name AS moderator_name
         FROM reports r
         JOIN users ru ON ru.user_id = r.reporter_id
         LEFT JOIN users tu ON tu.user_id = r.reported_user_id
         LEFT JOIN items i ON i.item_id = r.item_id
         LEFT JOIN messages m ON m.message_id = r.message_id
         LEFT JOIN users mu ON mu.user_id = r.moderator_id
         WHERE r.report_id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* ---------- Admin listings page ---------- */

function admin_items_where(array $f): array
{
    $where = ['1 = 1'];
    $p     = [];
    if ($f['status'] !== '') { $where[] = 'i.item_status = ?'; $p[] = $f['status']; }
    if ($f['q'] !== '')      { $where[] = 'i.title LIKE ?';    $p[] = '%' . $f['q'] . '%'; }
    return [implode(' AND ', $where), $p];
}

function admin_items_count(array $f): int
{
    [$w, $p] = admin_items_where($f);
    $stmt = db()->prepare("SELECT COUNT(*) FROM items i WHERE $w");
    $stmt->execute($p);
    return (int) $stmt->fetchColumn();
}

function admin_items_page(array $f, int $page, int $perPage): array
{
    [$w, $p] = admin_items_where($f);
    $perPage = max(1, $perPage);
    $offset  = max(0, ($page - 1) * $perPage);
    $stmt = db()->prepare(
        "SELECT i.item_id, i.title, i.report_type, i.item_status, i.created_at, u.full_name AS poster_name
         FROM items i JOIN users u ON u.user_id = i.reporter_id
         WHERE $w ORDER BY i.item_id DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($p);
    return $stmt->fetchAll();
}