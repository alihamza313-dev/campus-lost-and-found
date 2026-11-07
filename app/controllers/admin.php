<?php
declare(strict_types=1);

function require_admin(): array
{
    $user = require_role('admin');
    if (!user_can_act($user)) {
        abort(403);
    }
    return $user;
}

/* ---------- Dashboard ---------- */

function admin_dashboard(): void
{
    require_admin();
    view('admin/dashboard', [
        'title'   => 'Admin dashboard',
        'users'   => admin_user_counts(),
        'items'   => admin_item_counts(),
        'reports' => admin_open_report_count(),
    ]);
}

/* ---------- Users ---------- */

function admin_users_list(): void
{
    require_admin();
    $role   = get_str('role');
    $status = get_str('status');
    $f = [
        'q'      => mb_substr(get_str('q'), 0, 100),
        'role'   => in_array($role, ['student', 'custodian', 'admin'], true) ? $role : '',
        'status' => in_array($status, ['active', 'suspended', 'deactivated', 'unverified'], true) ? $status : '',
    ];
    $perPage = 25;
    $total   = admin_users_count($f);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min($pages, max(1, (int) get_str('page')));

    view('admin/users', [
        'title' => 'Users', 'f' => $f, 'users' => admin_users_page($f, $page, $perPage),
        'total' => $total, 'page' => $page, 'pages' => $pages,
    ]);
}

function admin_user_show(int $id): void
{
    $admin = require_admin();
    $u = admin_user_detail($id);
    if ($u === null) {
        abort(404);
    }
    view('admin/user', [
        'title' => 'User', 'u' => $u, 'me' => (int) $admin['user_id'],
        'history' => audit_recent_for('user', $id, 10),
    ]);
}

function admin_user_action(int $id, array $result): void
{
    flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('/admin/users/' . $id);
}

function admin_user_suspend_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    admin_user_action($id, admin_suspend_user($id, (int) $admin['user_id'], post_str('reason'), (int) post_str('days')));
}

function admin_user_reactivate_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    admin_user_action($id, admin_reactivate_user($id, (int) $admin['user_id']));
}

function admin_user_role_submit(int $id): void
{
    $admin = require_admin();
    require_csrf();
    admin_user_action($id, admin_change_role($id, (int) $admin['user_id'], post_str('role')));
}

/* ---------- Create custodian ---------- */

function admin_staff_form(): void
{
    require_admin();
    view('admin/staff_new', ['title' => 'Create custodian', 'old' => [], 'errors' => []]);
}

function admin_staff_submit(): void
{
    $admin = require_admin();
    require_csrf();

    $old = ['full_name' => post_str('full_name'), 'email' => strtolower(post_str('email'))];
    $pw      = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    $errors = [];
    if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 100) {
        $errors['full_name'] = 'Enter the full name (2 to 100 characters).';
    }
    if (!valid_university_email($old['email'])) {
        $errors['email'] = 'Use a university email ending in @' . config('app.allowed_email_domain') . '.';
    }
    if ($err = password_error($pw)) {
        $errors['password'] = $err;
    } elseif ($pw !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    if (!$errors) {
        $result = admin_create_custodian((int) $admin['user_id'], $old['full_name'], $old['email'], password_hash($pw, PASSWORD_DEFAULT));
        if ($result['ok']) {
            flash_set('success', $result['message'] . (config('app.debug') ? ' (Development: see storage/logs/mail.log)' : ''));
            redirect('/admin/users/' . $result['user_id']);
        }
        $errors['email'] = $result['message'];
    }
    http_response_code(422);
    view('admin/staff_new', ['title' => 'Create custodian', 'old' => $old, 'errors' => $errors]);
}

/* ---------- Categories and locations ---------- */

function admin_lookups_page(): void
{
    require_admin();
    view('admin/lookups', [
        'title' => 'Categories and locations',
        'sets'  => ['categories' => lookup_all('categories'), 'locations' => lookup_all('locations')],
    ]);
}

function lookup_name_ok(string $name): bool
{
    return mb_strlen($name) >= 2 && mb_strlen($name) <= 100;
}

function lookup_finish(array $result): void
{
    flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('/admin/lookups');
}

function lookup_add_flow(string $kind): void
{
    require_admin();
    require_csrf();
    $name = post_str('name');
    lookup_finish(lookup_name_ok($name) ? lookup_add($kind, $name) : ['ok' => false, 'message' => 'Name must be 2 to 100 characters.']);
}

function lookup_rename_flow(string $kind, int $id): void
{
    require_admin();
    require_csrf();
    $name = post_str('name');
    lookup_finish(lookup_name_ok($name) ? lookup_rename($kind, $id, $name) : ['ok' => false, 'message' => 'Name must be 2 to 100 characters.']);
}

function lookup_active_flow(string $kind, int $id): void
{
    require_admin();
    require_csrf();
    lookup_finish(lookup_set_active($kind, $id, post_str('active') === '1'));
}

function admin_category_add_submit(): void             { lookup_add_flow('categories'); }
function admin_category_rename_submit(int $id): void   { lookup_rename_flow('categories', $id); }
function admin_category_active_submit(int $id): void   { lookup_active_flow('categories', $id); }
function admin_location_add_submit(): void             { lookup_add_flow('locations'); }
function admin_location_rename_submit(int $id): void   { lookup_rename_flow('locations', $id); }
function admin_location_active_submit(int $id): void   { lookup_active_flow('locations', $id); }

/* ---------- Audit log viewer (FR-24: admins only) ---------- */

function admin_audit_log(): void
{
    require_admin();
    $type = get_str('target_type');
    $f = [
        'action'      => mb_substr(get_str('action'), 0, 50),
        'target_type' => in_array($type, ['user', 'item', 'claim', 'handover', 'report', 'message', 'evidence'], true) ? $type : '',
        'actor_id'    => max(0, (int) get_str('actor_id')),
    ];
    $perPage = 50;
    $total   = audit_count($f);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min($pages, max(1, (int) get_str('page')));

    view('admin/audit', [
        'title' => 'Audit log', 'f' => $f, 'rows' => audit_page($f, $page, $perPage),
        'total' => $total, 'page' => $page, 'pages' => $pages, 'actions' => audit_distinct_actions(),
    ]);
}