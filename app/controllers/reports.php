<?php
declare(strict_types=1);

const REPORT_REASONS = [
    'spam'          => 'Spam',
    'fake_listing'  => 'Fake or misleading',
    'harassment'    => 'Harassment',
    'inappropriate' => 'Inappropriate content',
    'other'         => 'Something else',
];

/* ---------- User side ---------- */

/** What the logged-in user is allowed to report, or null (shown as 404). Never trusts anything from the form. */
function report_resolve_target(array $user, string $kind, int $id): ?array
{
    $uid = (int) $user['user_id'];

    if ($kind === 'item') {
        $item = item_find_detail($id);
        if ($item === null || !in_array($item['item_status'], ITEM_PUBLIC_STATUSES, true) || (int) $item['reporter_id'] === $uid) {
            return null;
        }
        return ['item_id' => $id, 'message_id' => null, 'reported_user_id' => (int) $item['reporter_id'],
                'label' => 'Listing: ' . $item['title'], 'back' => '/items/' . $id];
    }
    if ($kind === 'message') {
        $m = message_for_report($id, $uid);
        if ($m === null) {
            return null;
        }
        return ['item_id' => (int) $m['item_id'], 'message_id' => $id, 'reported_user_id' => (int) $m['sender_id'],
                'label' => 'Message: "' . $m['excerpt'] . '"', 'back' => '/messages/' . (int) $m['item_id'] . '/' . (int) $m['sender_id']];
    }
    if ($id === $uid) {
        return null;
    }
    $target = user_find_by_id($id);
    if ($target === null || !report_user_relation_ok($uid, $id)) {
        return null;
    }
    return ['item_id' => null, 'message_id' => null, 'reported_user_id' => $id,
            'label' => 'User: ' . explode(' ', trim((string) $target['full_name']))[0], 'back' => '/dashboard'];
}

function report_action_path(string $kind, int $id): string
{
    return match ($kind) {
        'item'    => '/items/' . $id . '/report',
        'message' => '/messages/' . $id . '/report',
        default   => '/users/' . $id . '/report',
    };
}

function report_render_form(string $kind, int $id, array $target, array $old, array $errors, int $status = 200): void
{
    http_response_code($status);
    view('reports/form', [
        'title' => 'Report', 'kind' => $kind, 'action' => report_action_path($kind, $id),
        'target' => $target, 'old' => $old, 'errors' => $errors,
    ]);
}

function report_form_common(string $kind, int $id): void
{
    $user   = require_verified();
    $target = report_resolve_target($user, $kind, $id);
    if ($target === null) {
        abort(404);
    }
    report_render_form($kind, $id, $target, ['reason' => '', 'details' => ''], []);
}

function report_submit_common(string $kind, int $id): void
{
    $user = require_verified();                    // unverified or suspended users stop here, even with a direct POST
    require_csrf();
    $uid = (int) $user['user_id'];

    if (!form_token_consume()) {
        flash_set('warning', 'That form was already submitted or has expired.');
        redirect('/dashboard');
    }
    $target = report_resolve_target($user, $kind, $id);
    if ($target === null) {
        abort(404);
    }

    $old    = ['reason' => post_str('reason'), 'details' => post_str('details')];
    $errors = [];
    if (!array_key_exists($old['reason'], REPORT_REASONS)) {
        $errors['reason'] = 'Choose a reason.';
    }
    if (mb_strlen($old['details']) > 1000) {
        $errors['details'] = 'Keep the details under 1000 characters.';
    }
    $status = 422;
    if (!$errors && rate_limited('report', 'report:user:' . $uid)) {
        $errors['form'] = 'You have reached the daily limit of ' . config('limits.report.max') . ' reports. Please try again tomorrow.';
        $status = 429;
    }
    if (!$errors && report_live_exists($uid, $target['reported_user_id'], $target['item_id'], $target['message_id'])) {
        $errors['form'] = 'You already reported this and it is still being reviewed.';
    }
    if ($errors) {
        report_render_form($kind, $id, $target, $old, $errors, $status);
        return;
    }

    $reportId = report_insert($uid, $target['reported_user_id'], $target['item_id'], $target['message_id'],
        $old['reason'], $old['details'] !== '' ? $old['details'] : null);
    rate_hit('report', 'report:user:' . $uid);
    report_notify_admins($uid, $target['item_id'], 'A new report (#' . $reportId . ') needs review. Open Admin, then Reports.');

    flash_set('success', 'Thank you. Your report was sent to the moderators.');
    redirect($target['back']);
}

function report_item_form(int $id): void     { report_form_common('item', $id); }
function report_item_submit(int $id): void   { report_submit_common('item', $id); }
function report_message_form(int $id): void  { report_form_common('message', $id); }
function report_message_submit(int $id): void { report_submit_common('message', $id); }
function report_user_form(int $id): void     { report_form_common('user', $id); }
function report_user_submit(int $id): void   { report_submit_common('user', $id); }

/* ---------- Admin side ---------- */

function admin_reports_list(): void
{
    require_admin();
    $status = get_str('status');
    $f = ['status' => in_array($status, ['open', 'under_review', 'resolved', 'dismissed'], true) ? $status : ''];
    $perPage = 25;
    $total   = reports_count($f);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min($pages, max(1, (int) get_str('page')));
    view('admin/reports', ['title' => 'Reports', 'f' => $f, 'rows' => reports_page($f, $page, $perPage),
                           'total' => $total, 'page' => $page, 'pages' => $pages]);
}

function admin_report_show(int $id): void
{
    $admin = require_admin();
    $r = report_detail($id);
    if ($r === null) {
        abort(404);
    }
    view('admin/report', [
        'title' => 'Report #' . $id, 'r' => $r, 'kind' => report_kind($r),
        'involved' => report_involves($r, (int) $admin['user_id']),
    ]);
}

function admin_report_done(int $id, array $result): void
{
    flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('/admin/reports/' . $id);
}

function admin_report_review_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    admin_report_done($id, report_start_review($id, (int) $admin['user_id']));
}

function admin_report_dismiss_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    admin_report_done($id, report_finish($id, (int) $admin['user_id'], 'dismissed', post_str('notes')));
}

function admin_report_resolve_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    admin_report_done($id, report_finish($id, (int) $admin['user_id'], 'resolved', post_str('notes')));
}

/** The target always comes from the STORED report, never from the form. */
function admin_report_remove_item_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    $r = report_detail($id);
    if ($r === null) { abort(404); }
    if (report_kind($r) !== 'item') {
        admin_report_done($id, ['ok' => false, 'message' => 'This report is not about a listing.']);
    }
    admin_report_done($id, moderate_remove_item((int) $r['item_id'], (int) $admin['user_id'], post_str('reason'), $id));
}

function admin_report_remove_message_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    $r = report_detail($id);
    if ($r === null) { abort(404); }
    if (report_kind($r) !== 'message') {
        admin_report_done($id, ['ok' => false, 'message' => 'This report is not about a message.']);
    }
    admin_report_done($id, moderate_remove_message((int) $r['message_id'], (int) $admin['user_id'], post_str('reason'), $id));
}

function admin_report_suspend_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    $r = report_detail($id);
    if ($r === null) { abort(404); }
    if ($r['reported_user_id'] === null) {
        admin_report_done($id, ['ok' => false, 'message' => 'This report has no user to suspend.']);
    }
    admin_report_done($id, admin_suspend_user((int) $r['reported_user_id'], (int) $admin['user_id'],
        post_str('reason'), (int) post_str('days'), $id));
}

/* ---------- Admin listings page ---------- */

function admin_items_list(): void
{
    require_admin();
    $status = get_str('status');
    $f = [
        'q'      => mb_substr(get_str('q'), 0, 100),
        'status' => in_array($status, ['open', 'claim_pending', 'ready_for_handover', 'recovered', 'closed', 'removed'], true) ? $status : '',
    ];
    $perPage = 25;
    $total   = admin_items_count($f);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min($pages, max(1, (int) get_str('page')));
    view('admin/items', ['title' => 'Listings', 'f' => $f, 'rows' => admin_items_page($f, $page, $perPage),
                         'total' => $total, 'page' => $page, 'pages' => $pages]);
}

function admin_item_remove_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    $result = moderate_remove_item($id, (int) $admin['user_id'], post_str('reason'));
    flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('/admin/items');
}