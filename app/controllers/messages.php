<?php
declare(strict_types=1);

function messages_inbox_page(): void
{
    $user = require_login();
    view('messages/inbox', ['title' => 'Messages', 'threads' => message_inbox((int) $user['user_id'])]);
}

function message_render_thread(array $user, int $itemId, int $otherId, string $old = '', ?string $error = null, int $status = 200): void
{
    $uid   = (int) $user['user_id'];
    $item  = item_find_detail($itemId);
    $other = user_find_by_id($otherId);
    if ($item === null || $other === null || $otherId === $uid) {
        abort(404);
    }

    $messages  = message_thread($itemId, $uid, $otherId);       // always filtered to MY conversations
    $ruleError = messaging_rule_error($user, $item, $otherId);

    if (!$messages && $ruleError !== null) {                     // nothing to read and not allowed to start one
        flash_set('error', $ruleError);
        redirect('/items/' . $itemId);
    }

    $label = explode(' ', trim((string) $other['full_name']))[0];
    if (in_array($other['role'], ['custodian', 'admin'], true)) {
        $label .= ' (staff)';
    }

    http_response_code($status);
    view('messages/thread', [
        'title'     => 'Conversation',
        'item'      => $item,
        'messages'  => $messages,
        'me'        => $uid,
        'otherId'   => $otherId,
        'otherName' => $label,
        'canSend'   => $ruleError === null,
        'ruleError' => $ruleError,
        'old'       => $old,
        'error'     => $error,
    ]);
}

function message_thread_show(int $itemId, int $otherId): void
{
    $user = require_login();
    $uid  = (int) $user['user_id'];

    if ($otherId !== $uid) {
        message_mark_thread_read($itemId, $uid, $otherId);
        notification_mark_messages_read($uid, $itemId);
    }
    message_render_thread($user, $itemId, $otherId);
}

function message_send_submit(int $itemId, int $otherId): void
{
    $user = require_verified();                                  // unverified or suspended users stop here
    require_csrf();
    $uid  = (int) $user['user_id'];
    $back = '/messages/' . $itemId . '/' . $otherId;

    if (!form_token_consume()) {                                 // a double click lands here
        flash_set('warning', 'That message was already sent or the form expired.');
        redirect($back);
    }

    $text = post_str('message_text');
    $len  = mb_strlen($text);
    if ($len < 1 || $len > 2000) {
        message_render_thread($user, $itemId, $otherId, $text, 'Write a message of 1 to 2000 characters.', 422);
        return;
    }
    if (rate_limited('message', 'message:user:' . $uid)) {
        flash_set('error', 'You have reached the limit of ' . config('limits.message.max') . ' messages per hour. Please try again later.');
        redirect($back);
    }

    $result = message_send($itemId, $uid, $otherId, $text);
    if ($result['ok']) {
        rate_hit('message', 'message:user:' . $uid);
    }
    flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    redirect($back);
}