<?php
declare(strict_types=1);

const SUSPENDED_NOW_SQL = "(account_status = 'suspended' AND (suspended_until IS NULL OR suspended_until > NOW()))";

/* ---------- Dashboard numbers (all real) ---------- */

function admin_user_counts(): array
{
    return db()->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(role = 'student'), 0)   AS students,
                COALESCE(SUM(role = 'custodian'), 0) AS custodians,
                COALESCE(SUM(role = 'admin'), 0)     AS admins,
                COALESCE(SUM" . SUSPENDED_NOW_SQL . ", 0) AS suspended,
                COALESCE(SUM(verification_status = 'pending'), 0) AS unverified
         FROM users"
    )->fetch();
}

function admin_item_counts(): array
{
    return db()->query('SELECT item_status, COUNT(*) FROM items GROUP BY item_status')->fetchAll(PDO::FETCH_KEY_PAIR);
}

function admin_open_report_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM reports WHERE report_status IN ('open','under_review')")->fetchColumn();
}

/* ---------- Users ---------- */

function admin_users_where(array $f): array
{
    $where = ['1 = 1'];
    $p     = [];
    if ($f['q'] !== '') {
        $where[] = '(full_name LIKE ? OR university_email LIKE ? OR student_id LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like);
    }
    if ($f['role'] !== '') {
        $where[] = 'role = ?';
        $p[] = $f['role'];
    }
    switch ($f['status']) {
        case 'suspended':   $where[] = SUSPENDED_NOW_SQL; break;
        case 'active':      $where[] = "account_status = 'active'"; break;
        case 'deactivated': $where[] = "account_status = 'deactivated'"; break;
        case 'unverified':  $where[] = "verification_status = 'pending'"; break;
    }
    return [implode(' AND ', $where), $p];
}

function admin_users_count(array $f): int
{
    [$w, $p] = admin_users_where($f);
    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE $w");
    $stmt->execute($p);
    return (int) $stmt->fetchColumn();
}

function admin_users_page(array $f, int $page, int $perPage): array
{
    [$w, $p] = admin_users_where($f);
    $perPage = max(1, $perPage);
    $offset  = max(0, ($page - 1) * $perPage);
    $stmt = db()->prepare(
        'SELECT user_id, full_name, university_email, role, verification_status, account_status, created_at,
                ' . SUSPENDED_NOW_SQL . " AS is_suspended
         FROM users WHERE $w ORDER BY user_id DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($p);
    return $stmt->fetchAll();
}

function admin_user_detail(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT user_id, student_id, full_name, university_email, phone, role, verification_status,
                account_status, suspended_until, suspension_reason, created_at,
                ' . SUSPENDED_NOW_SQL . ' AS is_suspended
         FROM users WHERE user_id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/* ---------- Audit log (read-only) ---------- */

function audit_where(array $f): array
{
    $where = ['1 = 1'];
    $p     = [];
    if ($f['action'] !== '')      { $where[] = 'a.action = ?';      $p[] = $f['action']; }
    if ($f['target_type'] !== '') { $where[] = 'a.target_type = ?'; $p[] = $f['target_type']; }
    if ($f['actor_id'] > 0)       { $where[] = 'a.actor_id = ?';    $p[] = $f['actor_id']; }
    return [implode(' AND ', $where), $p];
}

function audit_count(array $f): int
{
    [$w, $p] = audit_where($f);
    $stmt = db()->prepare("SELECT COUNT(*) FROM audit_logs a WHERE $w");
    $stmt->execute($p);
    return (int) $stmt->fetchColumn();
}

function audit_page(array $f, int $page, int $perPage): array
{
    [$w, $p] = audit_where($f);
    $perPage = max(1, $perPage);
    $offset  = max(0, ($page - 1) * $perPage);
    $stmt = db()->prepare(
        "SELECT a.log_id, a.actor_id, a.action, a.target_type, a.target_id,
                SUBSTRING(a.details, 1, 300) AS details, a.ip_address, a.created_at, u.full_name AS actor_name
         FROM audit_logs a LEFT JOIN users u ON u.user_id = a.actor_id
         WHERE $w ORDER BY a.log_id DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($p);
    return $stmt->fetchAll();
}

function audit_distinct_actions(): array
{
    return db()->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
}

function audit_recent_for(string $type, int $id, int $limit): array
{
    $limit = max(1, $limit);
    $stmt = db()->prepare(
        "SELECT a.action, SUBSTRING(a.details, 1, 200) AS details, a.created_at, u.full_name AS actor_name
         FROM audit_logs a LEFT JOIN users u ON u.user_id = a.actor_id
         WHERE a.target_type = ? AND a.target_id = ? ORDER BY a.log_id DESC LIMIT $limit"
    );
    $stmt->execute([$type, $id]);
    return $stmt->fetchAll();
}

/* ---------- Categories and locations ---------- */

function lookup_meta(string $kind): array
{
    return match ($kind) {
        'categories' => ['table' => 'categories', 'id' => 'category_id', 'name' => 'category_name', 'label' => 'Categories'],
        'locations'  => ['table' => 'campus_locations', 'id' => 'location_id', 'name' => 'location_name', 'label' => 'Campus locations'],
        default      => throw new InvalidArgumentException('Unknown lookup'),
    };
}

/** Identifiers come only from lookup_meta() above (our constants), never from the request. */
function lookup_all(string $kind): array
{
    $m = lookup_meta($kind);
    return db()->query(
        "SELECT t.{$m['id']} AS id, t.{$m['name']} AS name, t.is_active,
                (SELECT COUNT(*) FROM items i WHERE i.{$m['id']} = t.{$m['id']}) AS uses
         FROM {$m['table']} t ORDER BY t.{$m['id']}"
    )->fetchAll();
}

function lookup_exists(string $kind, int $id): bool
{
    $m = lookup_meta($kind);
    $stmt = db()->prepare("SELECT 1 FROM {$m['table']} WHERE {$m['id']} = ?");
    $stmt->execute([$id]);
    return (bool) $stmt->fetchColumn();
}

function lookup_add(string $kind, string $name): array
{
    $m = lookup_meta($kind);
    try {
        db()->prepare("INSERT INTO {$m['table']} ({$m['name']}) VALUES (?)")->execute([$name]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'message' => 'That name already exists.'];
        }
        throw $e;
    }
    return ['ok' => true, 'message' => 'Added.'];
}

function lookup_rename(string $kind, int $id, string $name): array
{
    $m = lookup_meta($kind);
    if (!lookup_exists($kind, $id)) {
        return ['ok' => false, 'message' => 'Not found.'];
    }
    try {
        db()->prepare("UPDATE {$m['table']} SET {$m['name']} = ? WHERE {$m['id']} = ?")->execute([$name, $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'message' => 'That name already exists.'];
        }
        throw $e;
    }
    return ['ok' => true, 'message' => 'Renamed.'];
}

function lookup_set_active(string $kind, int $id, bool $active): array
{
    $m = lookup_meta($kind);
    if (!lookup_exists($kind, $id)) {
        return ['ok' => false, 'message' => 'Not found.'];
    }
    if (!$active) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM {$m['table']} WHERE is_active = 1 AND {$m['id']} <> ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 0) {
            return ['ok' => false, 'message' => 'At least one must stay active, or nobody could post listings.'];
        }
    }
    db()->prepare("UPDATE {$m['table']} SET is_active = ? WHERE {$m['id']} = ?")->execute([$active ? 1 : 0, $id]);
    return ['ok' => true, 'message' => $active ? 'Activated.' : 'Deactivated. Existing listings keep it.'];
}