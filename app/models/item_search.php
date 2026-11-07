<?php
declare(strict_types=1);

const ITEM_PUBLIC_STATUSES = ['open', 'claim_pending'];

/** Builds the WHERE part for a search. Returns [sql, params]. All values are bound, never concatenated. */
function item_search_where(array $f): array
{
    $where  = ["i.item_status IN ('open','claim_pending')"];   // BR-5 / FR-10: public statuses only
    $params = [];

    if ($f['status'] !== '')      { $where[] = 'i.item_status = ?';   $params[] = $f['status']; }
    if ($f['type'] !== '')        { $where[] = 'i.report_type = ?';   $params[] = $f['type']; }
    if ($f['category_id'] > 0)    { $where[] = 'i.category_id = ?';   $params[] = $f['category_id']; }
    if ($f['location_id'] > 0)    { $where[] = 'i.location_id = ?';   $params[] = $f['location_id']; }
    if ($f['date_from'] !== '')   { $where[] = 'i.incident_date >= ?'; $params[] = $f['date_from']; }
    if ($f['date_to'] !== '')     { $where[] = 'i.incident_date <= ?'; $params[] = $f['date_to']; }

    // Every word must match (AND). Words contain only letters and digits, so no operators can sneak in.
    foreach ($f['words'] as $w) {
        if (mb_strlen($w) >= 3) {
            $where[]  = 'MATCH(i.title, i.description) AGAINST (? IN BOOLEAN MODE)';
            $params[] = '+' . $w . '*';
        } else {
            $where[]  = '(i.title LIKE ? OR i.description LIKE ?)';
            $params[] = '%' . $w . '%';
            $params[] = '%' . $w . '%';
        }
    }
    return [implode(' AND ', $where), $params];
}

function items_search_count(array $f): int
{
    [$whereSql, $params] = item_search_where($f);
    $stmt = db()->prepare("SELECT COUNT(*) FROM items i WHERE $whereSql");
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function items_search_page(array $f, int $page, int $perPage): array
{
    [$whereSql, $params] = item_search_where($f);
    $perPage = max(1, $perPage);                     // integers from our own code, safe to place in SQL
    $offset  = max(0, ($page - 1) * $perPage);

    $stmt = db()->prepare(
        "SELECT i.item_id, i.report_type, i.title, i.incident_date, i.item_status, i.created_at,
                SUBSTRING(i.description, 1, 160) AS snippet,
                c.category_name, l.location_name,
                (SELECT im.image_path FROM item_images im WHERE im.item_id = i.item_id ORDER BY im.image_id LIMIT 1) AS thumb
         FROM items i
         JOIN categories c ON c.category_id = i.category_id
         JOIN campus_locations l ON l.location_id = i.location_id
         WHERE $whereSql
         ORDER BY i.created_at DESC, i.item_id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Public columns of one listing, in ANY status. The controller decides who may see which status. */
function item_find_detail(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT i.item_id, i.reporter_id, i.report_type, i.title, i.description, i.incident_date,
                i.item_status, i.created_at, c.category_name, l.location_name, u.full_name AS poster_name
         FROM items i
         JOIN categories c ON c.category_id = i.category_id
         JOIN campus_locations l ON l.location_id = i.location_id
         JOIN users u ON u.user_id = i.reporter_id
         WHERE i.item_id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Real numbers for the dashboard. */
function user_item_stats(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total, COALESCE(SUM(item_status = 'open'), 0) AS open_count
         FROM items WHERE reporter_id = ?"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return ['total' => (int) $row['total'], 'open' => (int) $row['open_count']];
}

function items_latest_public(int $limit): array
{
    $limit = max(1, $limit);
    return db()->query(
        "SELECT i.item_id, i.report_type, i.title, i.incident_date, i.item_status, l.location_name
         FROM items i JOIN campus_locations l ON l.location_id = i.location_id
         WHERE i.item_status IN ('open','claim_pending')
         ORDER BY i.created_at DESC, i.item_id DESC LIMIT $limit"
    )->fetchAll();
}