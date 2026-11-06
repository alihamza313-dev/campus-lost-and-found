<?php
declare(strict_types=1);

function category_list_active(): array
{
    return db()->query('SELECT category_id, category_name FROM categories WHERE is_active = 1 ORDER BY category_id')->fetchAll();
}

function location_list_active(): array
{
    return db()->query('SELECT location_id, location_name FROM campus_locations WHERE is_active = 1 ORDER BY location_id')->fetchAll();
}

function category_active_exists(int $id): bool
{
    $stmt = db()->prepare('SELECT 1 FROM categories WHERE category_id = ? AND is_active = 1');
    $stmt->execute([$id]);
    return (bool) $stmt->fetchColumn();
}

function location_active_exists(int $id): bool
{
    $stmt = db()->prepare('SELECT 1 FROM campus_locations WHERE location_id = ? AND is_active = 1');
    $stmt->execute([$id]);
    return (bool) $stmt->fetchColumn();
}

function item_create(int $reporterId, string $type, string $title, int $categoryId, int $locationId, string $description, string $date): int
{
    $stmt = db()->prepare(
        'INSERT INTO items (reporter_id, report_type, title, category_id, location_id, description, incident_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$reporterId, $type, $title, $categoryId, $locationId, $description, $date]);
    return (int) db()->lastInsertId();
}

/** The poster's own listing. Returns null if it does not exist OR belongs to someone else. */
function item_find_owned(int $itemId, int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT item_id, reporter_id, report_type, title, category_id, location_id, description, incident_date, item_status
         FROM items WHERE item_id = ? AND reporter_id = ?'
    );
    $stmt->execute([$itemId, $userId]);
    return $stmt->fetch() ?: null;
}

/** Locks the row until the transaction ends. Only returns it if it is the poster's AND still open (BR-3). */
function item_lock_open_owned(int $itemId, int $userId): ?array
{
    $stmt = db()->prepare(
        "SELECT item_id, report_type FROM items
         WHERE item_id = ? AND reporter_id = ? AND item_status = 'open' FOR UPDATE"
    );
    $stmt->execute([$itemId, $userId]);
    return $stmt->fetch() ?: null;
}

function item_update_details(int $itemId, int $userId, string $title, int $categoryId, int $locationId, string $description, string $date): void
{
    $stmt = db()->prepare(
        "UPDATE items SET title = ?, category_id = ?, location_id = ?, description = ?, incident_date = ?
         WHERE item_id = ? AND reporter_id = ? AND item_status = 'open'"
    );
    $stmt->execute([$title, $categoryId, $locationId, $description, $date, $itemId, $userId]);
}

function item_images_for(int $itemId): array
{
    $stmt = db()->prepare('SELECT image_id, image_path FROM item_images WHERE item_id = ? ORDER BY image_id');
    $stmt->execute([$itemId]);
    return $stmt->fetchAll();
}

function item_image_count(int $itemId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM item_images WHERE item_id = ?');
    $stmt->execute([$itemId]);
    return (int) $stmt->fetchColumn();
}

function item_image_add(int $itemId, string $storedName): void
{
    $stmt = db()->prepare('INSERT INTO item_images (item_id, image_path) VALUES (?, ?)');
    $stmt->execute([$itemId, $storedName]);
}

function items_by_reporter(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT i.item_id, i.report_type, i.title, i.item_status, i.incident_date, i.created_at,
                c.category_name, l.location_name,
                (SELECT COUNT(*) FROM item_images im WHERE im.item_id = i.item_id) AS image_count
         FROM items i
         JOIN categories c ON c.category_id = i.category_id
         JOIN campus_locations l ON l.location_id = i.location_id
         WHERE i.reporter_id = ?
         ORDER BY i.created_at DESC, i.item_id DESC
         LIMIT 100'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/* ---------- Duplicate detection (BR-13) ---------- */

/** Same type, category, location and date as the user's own listing in the last 3 days: warn. */
function item_duplicate_similar(int $userId, string $type, int $categoryId, int $locationId, string $date): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM items
         WHERE reporter_id = ? AND report_type = ? AND category_id = ? AND location_id = ? AND incident_date = ?
           AND created_at >= NOW() - INTERVAL 3 DAY'
    );
    $stmt->execute([$userId, $type, $categoryId, $locationId, $date]);
    return (int) $stmt->fetchColumn() > 0;
}

/** The same plus the same title within 24 hours: block. (Title match is case-insensitive via the column collation.) */
function item_duplicate_identical(int $userId, string $type, int $categoryId, int $locationId, string $date, string $title): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM items
         WHERE reporter_id = ? AND report_type = ? AND category_id = ? AND location_id = ? AND incident_date = ?
           AND title = ? AND created_at >= NOW() - INTERVAL 24 HOUR'
    );
    $stmt->execute([$userId, $type, $categoryId, $locationId, $date, $title]);
    return (int) $stmt->fetchColumn() > 0;
}

/* ---------- Finder's private detail (FR-09) ---------- */

/** Only call this after item_find_owned() has confirmed the viewer is the poster. */
function evidence_item_detail_get(int $itemId): ?string
{
    $stmt = db()->prepare(
        "SELECT private_description FROM private_item_evidence
         WHERE item_id = ? AND evidence_type = 'item_detail' ORDER BY evidence_id DESC LIMIT 1"
    );
    $stmt->execute([$itemId]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (string) $v;
}

/** Creates or updates the single item_detail row. Call inside the transaction that locked the item. */
function evidence_item_detail_save(int $itemId, int $userId, string $text): void
{
    $stmt = db()->prepare("SELECT evidence_id FROM private_item_evidence WHERE item_id = ? AND evidence_type = 'item_detail' LIMIT 1");
    $stmt->execute([$itemId]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $up = db()->prepare('UPDATE private_item_evidence SET private_description = ? WHERE evidence_id = ?');
        $up->execute([$text, $existingId]);
    } elseif ($text !== '') {
        $ins = db()->prepare(
            "INSERT INTO private_item_evidence (item_id, claim_id, evidence_type, submitted_by, private_description)
             VALUES (?, NULL, 'item_detail', ?, ?)"
        );
        $ins->execute([$itemId, $userId, $text]);
    }
}