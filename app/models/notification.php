<?php
declare(strict_types=1);

function notifications_for(int $uid, int $limit): array
{
    $limit = max(1, $limit);
    $stmt = db()->prepare(
        "SELECT n.notification_id, n.related_item_id, n.notification_type, n.notification_text,
                n.is_read, n.created_at, i.reporter_id AS item_reporter_id
         FROM notifications n
         LEFT JOIN items i ON i.item_id = n.related_item_id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC, n.notification_id DESC LIMIT $limit"
    );
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
}

function notification_unread_count(int $uid): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$uid]);
    return (int) $stmt->fetchColumn();
}

/** The user_id condition is in the SQL, so nobody can mark someone else's notification. */
function notification_mark_read(int $id, int $uid): void
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
}

function notification_mark_all_read(int $uid): void
{
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$uid]);
}

/** Opening a conversation clears its "new message" notifications. */
function notification_mark_messages_read(int $uid, int $itemId): void
{
    $stmt = db()->prepare(
        "UPDATE notifications SET is_read = 1
         WHERE user_id = ? AND related_item_id = ? AND notification_type = 'new_message' AND is_read = 0"
    );
    $stmt->execute([$uid, $itemId]);
}

/** Where a notification should lead. Returns a path or null. Chosen so the destination is always allowed for that user. */
function notification_link(array $n, int $uid): ?string
{
    $type   = $n['notification_type'];
    $itemId = $n['related_item_id'] !== null ? (int) $n['related_item_id'] : null;

    if ($type === 'new_message') {
        return '/messages';
    }
    if ($itemId === null) {
        return null;
    }
    if ($n['item_reporter_id'] !== null && (int) $n['item_reporter_id'] === $uid) {
        return '/items/' . $itemId;                       // the poster can always open their own listing
    }
    return match ($type) {
        'claim_approved', 'claim_rejected', 'handover_scheduled', 'item_recovered' => '/my/claims',
        'claim_submitted' => '/review/items/' . $itemId,
        default           => null,
    };
}