<?php
declare(strict_types=1);

/** 'poster', 'staff', 'claimant' or null: this person's role in a FOUND-item conversation. */
function message_participant_kind(array $item, array $user): ?string
{
    $uid = (int) $user['user_id'];
    if ((int) $item['reporter_id'] === $uid) {
        return 'poster';
    }
    if (in_array($user['role'], ['custodian', 'admin'], true) && user_can_act($user)) {
        return 'staff';
    }
    if (claim_active_for((int) $item['item_id'], $uid)) {
        return 'claimant';
    }
    return null;
}

/**
 * The messaging rule (BR-10). Returns an error message, or null if $sender may message $recipientId about $item.
 * $item needs: item_id, reporter_id, report_type, item_status.
 */
function messaging_rule_error(array $sender, array $item, int $recipientId): ?string
{
    $sid = (int) $sender['user_id'];

    if (!user_can_act($sender)) {
        return 'You need a verified email and an active account to send messages.';
    }
    if ($recipientId === $sid) {
        return 'You cannot message yourself.';
    }
    if (in_array($item['item_status'], ['recovered', 'closed', 'removed'], true)) {
        return 'Messaging is closed because this listing is finished.';
    }

    $recipient = user_find_by_id($recipientId);
    if ($recipient === null || $recipient['account_status'] === 'deactivated' || $recipient['verification_status'] !== 'verified') {
        return 'That user cannot receive messages.';
    }

    $posterId = (int) $item['reporter_id'];
    $itemId   = (int) $item['item_id'];

    if ($item['report_type'] === 'lost') {
        if ($recipientId === $posterId) {
            return null;                                           // any verified user may message the poster
        }
        if ($sid === $posterId && message_exists_from($itemId, $recipientId, $sid)) {
            return null;                                           // the poster may reply to someone who wrote first
        }
        return $sid === $posterId
            ? 'You can only reply to people who have already messaged you about this item.'
            : 'On a lost-item listing you can only message the person who posted it.';
    }

    // Found item: only the finder, people with an active claim, and staff.
    $a = message_participant_kind($item, $sender);
    $b = message_participant_kind($item, $recipient);
    if ($a === null || $b === null || ($a === 'claimant' && $b === 'claimant')) {
        return 'On a found item, only the finder, people with an active claim and custodians can message each other.';
    }
    return null;
}

/** Sends one message. The rule is checked again after the item row is locked, so a status change cannot slip in between. */
function message_send(int $itemId, int $senderId, int $recipientId, string $text): array
{
    $sender = user_find_by_id($senderId);                          // fresh from the database (BR-1)
    if ($sender === null) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $item = item_lock($itemId);
        if ($item === null) {
            return claim_fail($pdo, 'Listing not found.');
        }
        if ($err = messaging_rule_error($sender, $item, $recipientId)) {
            return claim_fail($pdo, $err);
        }

        message_insert($itemId, $senderId, $recipientId, $text);
        $first = explode(' ', trim((string) $sender['full_name']))[0];
        notify($recipientId, 'new_message', $itemId, "New message from $first about \"" . $item['title'] . '".');

        $pdo->commit();
        return ['ok' => true, 'message' => 'Message sent.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}