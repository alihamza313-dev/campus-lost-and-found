<?php
declare(strict_types=1);

/** Creates one notification. Call it inside the transaction of the action it reports. */
function notify(int $userId, string $type, ?int $itemId, string $text): void
{
    $allowed = ['claim_submitted', 'claim_approved', 'claim_rejected', 'new_message',
                'handover_scheduled', 'item_recovered', 'report_update'];
    if (!in_array($type, $allowed, true)) {
        throw new InvalidArgumentException("Unknown notification type: $type");
    }
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, related_item_id, notification_type, notification_text)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $itemId, $type, mb_substr($text, 0, 255)]);
}