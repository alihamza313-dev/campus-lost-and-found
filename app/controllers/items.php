<?php
declare(strict_types=1);

/* ---------- Helpers ---------- */

function item_read_input(): array
{
    return [
        'report_type'   => post_str('report_type'),
        'title'         => post_str('title'),
        'category_id'   => (int) post_str('category_id'),
        'location_id'   => (int) post_str('location_id'),
        'incident_date' => post_str('incident_date'),
        'description'   => post_str('description'),
        'hidden_detail' => post_str('hidden_detail'),
    ];
}

/** Returns field errors (empty array = valid). $type is 'lost' or 'found'. */
function item_validate(array $in, string $type): array
{
    $errors = [];

    $len = mb_strlen($in['title']);
    if ($len < 3 || $len > 120) {
        $errors['title'] = 'Title must be 3 to 120 characters.';
    }
    if (!category_active_exists($in['category_id'])) {
        $errors['category_id'] = 'Choose a category.';
    }
    if (!location_active_exists($in['location_id'])) {
        $errors['location_id'] = 'Choose a campus location.';
    }

    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $in['incident_date']);
    if ($d === false || $d->format('Y-m-d') !== $in['incident_date']) {
        $errors['incident_date'] = 'Enter a valid date.';
    } elseif ($in['incident_date'] > today_local()) {
        $errors['incident_date'] = 'The date cannot be in the future.';
    } elseif ($in['incident_date'] < (new DateTimeImmutable(today_local()))->modify('-365 days')->format('Y-m-d')) {
        $errors['incident_date'] = 'The date cannot be more than a year ago.';
    }

    $dl = mb_strlen($in['description']);
    if ($dl < 10 || $dl > 2000) {
        $errors['description'] = 'Description must be 10 to 2000 characters.';
    }
    if ($type === 'found' && mb_strlen($in['hidden_detail']) > 1000) {
        $errors['hidden_detail'] = 'The private detail can be at most 1000 characters.';
    }
    return $errors;
}

function item_render_form(string $mode, array $old, array $errors = [], bool $warning = false, ?array $item = null, array $images = []): void
{
    view('items/form', [
        'title'      => $mode === 'edit' ? 'Edit listing' : 'Report an item',
        'mode'       => $mode,
        'old'        => $old,
        'errors'     => $errors,
        'warning'    => $warning,
        'item'       => $item,
        'images'     => $images,
        'categories' => category_list_active(),
        'locations'  => location_list_active(),
        'scripts'    => ['js/item-form.js'],
    ]);
}

/* ---------- Create ---------- */

function item_new_form(): void
{
    require_verified();
    $type = (($_GET['type'] ?? '') === 'found') ? 'found' : 'lost';
    item_render_form('create', [
        'report_type' => $type, 'title' => '', 'category_id' => 0, 'location_id' => 0,
        'incident_date' => today_local(), 'description' => '', 'hidden_detail' => '',
    ]);
}

function item_create_submit(): void
{
    $user = require_verified();            // T-02: unverified or suspended users stop here, even with a direct POST
    require_csrf();
    $uid = (int) $user['user_id'];

    if (!form_token_consume()) {           // T-17: a second submit of the same form lands here
        flash_set('warning', 'That form was already submitted or has expired. Check "My listings" to see whether your post went through.');
        redirect('/my/items');
    }

    $in     = item_read_input();
    $type   = in_array($in['report_type'], ['lost', 'found'], true) ? $in['report_type'] : '';
    $errors = ($type === '') ? ['report_type' => 'Choose whether the item is lost or found.'] : [];
    $errors += item_validate($in, $type);
    if ($type !== 'found') {
        $in['hidden_detail'] = '';         // only found items may have a private detail
    }

    $files = image_collect_uploads('images');
    if ($err = image_validate_all($files, 0)) {
        $errors['images'] = $err;
    }

    $status  = 200;
    $warning = false;
    if (!$errors) {
        if (item_duplicate_identical($uid, $type, $in['category_id'], $in['location_id'], $in['incident_date'], $in['title'])) {
            $errors['form'] = 'You already posted an identical listing in the last 24 hours. Check "My listings".';
        } elseif (item_duplicate_similar($uid, $type, $in['category_id'], $in['location_id'], $in['incident_date'])
                  && post_str('confirm_duplicate') !== '1') {
            $warning = true;
        }
    }
    if (!$errors && !$warning && rate_limited('listing', 'listing:user:' . $uid)) {
        $errors['form'] = 'You have reached the daily limit of ' . config('limits.listing.max') . ' listings. Please try again tomorrow.';
        $status = 429;
    }
    if ($errors || $warning) {
        if ($errors && $status === 200) {
            $status = 422;
        }
        http_response_code($status);
        item_render_form('create', $in, $errors, $warning);
        return;
    }

    // Write the (re-encoded) image files first. If anything later fails, they are deleted again.
    $stored = [];
    try {
        foreach ($files as $f) {
            $stored[] = image_process($f);
        }
    } catch (RuntimeException $e) {
        image_delete_files($stored);
        http_response_code(422);
        item_render_form('create', $in, ['images' => $e->getMessage()]);
        return;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $itemId = item_create($uid, $type, $in['title'], $in['category_id'], $in['location_id'], $in['description'], $in['incident_date']);
        if ($type === 'found' && $in['hidden_detail'] !== '') {
            evidence_item_detail_save($itemId, $uid, $in['hidden_detail']);
        }
        foreach ($stored as $name) {
            item_image_add($itemId, $name);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        image_delete_files($stored);
        throw $e;
    }

    rate_hit('listing', 'listing:user:' . $uid);
    flash_set('success', 'Your listing was posted.');
    redirect('/my/items');
}

/* ---------- Edit (FR-08, BR-3) ---------- */

function item_edit_form(int $id): void
{
    $user = require_verified();
    $uid  = (int) $user['user_id'];

    $item = item_find_owned($id, $uid);
    if ($item === null) {
        abort(404);                        // not yours or does not exist: same answer, nothing leaks
    }
    if ($item['item_status'] !== 'open') {
        flash_set('error', 'Only open listings can be edited.');
        redirect('/my/items');
    }

    $old = [
        'report_type'   => $item['report_type'],
        'title'         => $item['title'],
        'category_id'   => (int) $item['category_id'],
        'location_id'   => (int) $item['location_id'],
        'incident_date' => $item['incident_date'],
        'description'   => $item['description'],
        'hidden_detail' => $item['report_type'] === 'found' ? (evidence_item_detail_get($id) ?? '') : '',
    ];
    item_render_form('edit', $old, [], false, $item, item_images_for($id));
}

function item_edit_submit(int $id): void
{
    $user = require_verified();
    require_csrf();
    $uid = (int) $user['user_id'];

    $item = item_find_owned($id, $uid);
    if ($item === null) {
        abort(404);
    }
    if (!form_token_consume()) {
        flash_set('warning', 'That form was already submitted or has expired.');
        redirect('/my/items');
    }
    if ($item['item_status'] !== 'open') {  // T-04
        flash_set('error', 'This listing can no longer be edited. Only open listings can be changed.');
        redirect('/my/items');
    }

    $type = $item['report_type'];
    $in   = item_read_input();
    $in['report_type'] = $type;
    if ($type !== 'found') {
        $in['hidden_detail'] = '';
    }
    $errors = item_validate($in, $type);

    $files    = image_collect_uploads('images');
    $existing = item_image_count($id);
    if ($err = image_validate_all($files, $existing)) {
        $errors['images'] = $err;
    }
    if ($errors) {
        http_response_code(422);
        item_render_form('edit', $in, $errors, false, $item, item_images_for($id));
        return;
    }

    $stored = [];
    try {
        foreach ($files as $f) {
            $stored[] = image_process($f);
        }
    } catch (RuntimeException $e) {
        image_delete_files($stored);
        http_response_code(422);
        item_render_form('edit', $in, ['images' => $e->getMessage()], false, $item, item_images_for($id));
        return;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Lock the row and re-check "mine AND still open" inside the transaction.
        // If a claim arrived while the form was open, this fails and nothing is changed.
        if (item_lock_open_owned($id, $uid) === null || item_image_count($id) + count($stored) > IMAGE_MAX_COUNT) {
            $pdo->rollBack();
            image_delete_files($stored);
            flash_set('error', 'This listing could not be updated. It may no longer be open, or it already has too many images.');
            redirect('/my/items');
        }

        item_update_details($id, $uid, $in['title'], $in['category_id'], $in['location_id'], $in['description'], $in['incident_date']);
        if ($type === 'found') {
            evidence_item_detail_save($id, $uid, $in['hidden_detail']);
        }
        foreach ($stored as $name) {
            item_image_add($id, $name);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        image_delete_files($stored);
        throw $e;
    }

    flash_set('success', 'Listing updated.');
    redirect('/my/items');
}

/* ---------- Close / mark recovered (poster only) ---------- */

/**
 * One guarded UPDATE: the row only changes if it is the poster's AND still open (and lost, for "recovered").
 * Zero affected rows means the request was out of date. The audit row is in the same transaction.
 */
function item_set_status_by_owner(int $id, string $newStatus, string $action, string $okMessage, bool $lostOnly): void
{
    $user = require_verified();
    require_csrf();
    $uid = (int) $user['user_id'];

    $sql = "UPDATE items SET item_status = ? WHERE item_id = ? AND reporter_id = ? AND item_status = 'open'"
         . ($lostOnly ? " AND report_type = 'lost'" : '');

    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newStatus, $id, $uid]);

    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        flash_set('error', 'This listing could not be changed. It must be yours and still open.');
        redirect('/my/items');
    }
    audit_log($uid, $action, 'item', $id, 'open -> ' . $newStatus);
    $pdo->commit();

    flash_set('success', $okMessage);
    redirect('/my/items');
}

function item_close_submit(int $id): void
{
    item_set_status_by_owner($id, 'closed', 'item_closed', 'Listing closed.', false);
}

function item_recovered_submit(int $id): void
{
    item_set_status_by_owner($id, 'recovered', 'item_recovered', 'Marked as recovered. Glad you got it back.', true);
}

/* ---------- My listings ---------- */

function my_items_index(): void
{
    $user = require_login();
    view('items/mine', ['title' => 'My listings', 'items' => items_by_reporter((int) $user['user_id']), 'user' => $user]);
}