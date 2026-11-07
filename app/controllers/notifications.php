<?php
declare(strict_types=1);

function notifications_index(): void
{
    $user = require_login();
    view('notifications/index', [
        'title' => 'Notifications',
        'notes' => notifications_for((int) $user['user_id'], 50),
        'uid'   => (int) $user['user_id'],
    ]);
}

function notification_read_submit(int $id): void
{
    $user = require_login();
    require_csrf();
    notification_mark_read($id, (int) $user['user_id']);
    redirect('/notifications');
}

function notifications_read_all_submit(): void
{
    $user = require_login();
    require_csrf();
    notification_mark_all_read((int) $user['user_id']);
    flash_set('success', 'All notifications marked as read.');
    redirect('/notifications');
}