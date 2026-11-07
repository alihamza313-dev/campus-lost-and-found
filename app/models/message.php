<?php
declare(strict_types=1);

function message_exists_from(int $itemId, int $fromId, int $toId): bool
{
    $stmt = db()->prepare(
        "SELECT 1 FROM messages
         WHERE item_id = ? AND sender_id = ? AND recipient_id = ? AND message_status <> 'removed' LIMIT 1"
    );
    $stmt->execute([$itemId, $fromId, $toId]);
    return (bool) $stmt->fetchColumn();
}

function claim_active_for(int $itemId, int $userId): bool
{
    $stmt = db()->prepare(
        "SELECT 1 FROM claims
         WHERE item_id = ? AND claimant_id = ? AND claim_status IN ('pending','under_review','approved') LIMIT 1"
    );
    $stmt->execute([$itemId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function message_insert(int $itemId, int $senderId, int $recipientId, string $text): int
{
    $stmt = db()->prepare(
        'INSERT INTO messages (item_id, sender_id, recipient_id, message_text) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$itemId, $senderId, $recipientId, $text]);
    return (int) db()->lastInsertId();
}

/** Newest 200 messages between the two users on this item, oldest first. Removed messages have no text. */
function message_thread(int $itemId, int $me, int $other): array
{
    $stmt = db()->prepare(
        "SELECT message_id, sender_id, message_status, created_at,
                CASE WHEN message_status = 'removed' THEN NULL ELSE message_text END AS message_text
         FROM messages
         WHERE item_id = ? AND ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))
         ORDER BY message_id DESC LIMIT 200"
    );
    $stmt->execute([$itemId, $me, $other, $other, $me]);
    return array_reverse($stmt->fetchAll());
}

function message_mark_thread_read(int $itemId, int $me, int $other): void
{
    $stmt = db()->prepare(
        "UPDATE messages SET message_status = 'read'
         WHERE item_id = ? AND sender_id = ? AND recipient_id = ? AND message_status = 'sent'"
    );
    $stmt->execute([$itemId, $other, $me]);
}

/** One row per conversation (item + other person), newest first. */
function message_inbox(int $uid): array
{
    $stmt = db()->prepare(
        "SELECT t.item_id, t.other_id, i.title, i.item_status, u.full_name AS other_name,
                MAX(t.created_at) AS last_at, SUM(t.is_unread) AS unread
         FROM (
             SELECT item_id, IF(sender_id = ?, recipient_id, sender_id) AS other_id, created_at,
                    (recipient_id = ? AND message_status = 'sent') AS is_unread
             FROM messages WHERE sender_id = ? OR recipient_id = ?
         ) t
         JOIN items i ON i.item_id = t.item_id
         JOIN users u ON u.user_id = t.other_id
         GROUP BY t.item_id, t.other_id, i.title, i.item_status, u.full_name
         ORDER BY last_at DESC LIMIT 100"
    );
    $stmt->execute([$uid, $uid, $uid, $uid]);
    return $stmt->fetchAll();
}

function message_unread_count(int $uid): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND message_status = 'sent'");
    $stmt->execute([$uid]);
    return (int) $stmt->fetchColumn();
}