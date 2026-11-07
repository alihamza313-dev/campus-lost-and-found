<?php
declare(strict_types=1);

function items_search(): void
{
    require_login();

    $q = mb_substr(get_str('q'), 0, 100);
    preg_match_all('/[\p{L}\p{N}]+/u', $q, $m);
    $words = array_slice(array_values(array_unique(array_map('mb_strtolower', $m[0]))), 0, 5);

    $validDate = function (string $d): string {
        $x = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
        return ($x !== false && $x->format('Y-m-d') === $d) ? $d : '';
    };

    $type   = get_str('type');
    $status = get_str('status');
    $f = [
        'q'           => $q,
        'words'       => $words,
        'type'        => in_array($type, ['lost', 'found'], true) ? $type : '',
        'status'      => in_array($status, ITEM_PUBLIC_STATUSES, true) ? $status : '',
        'category_id' => max(0, (int) get_str('category_id')),
        'location_id' => max(0, (int) get_str('location_id')),
        'date_from'   => $validDate(get_str('date_from')),
        'date_to'     => $validDate(get_str('date_to')),
    ];
    if ($f['date_from'] !== '' && $f['date_to'] !== '' && $f['date_from'] > $f['date_to']) {
        [$f['date_from'], $f['date_to']] = [$f['date_to'], $f['date_from']];
    }

    $perPage = 12;
    $total   = items_search_count($f);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min($pages, max(1, (int) get_str('page')));

    view('items/search', [
        'title'      => 'Browse listings',
        'f'          => $f,
        'items'      => items_search_page($f, $page, $perPage),
        'total'      => $total,
        'page'       => $page,
        'pages'      => $pages,
        'categories' => category_list_active(),
        'locations'  => location_list_active(),
    ]);
}

function item_show(int $id): void
{
    $user = require_login();
    $item = item_find_detail($id);
    if ($item === null) {
        abort(404);
    }

    $isOwner = (int) $item['reporter_id'] === (int) $user['user_id'];
    $isStaff = in_array($user['role'], ['custodian', 'admin'], true);
    $public  = in_array($item['item_status'], ITEM_PUBLIC_STATUSES, true);

    if (!$public && !$isOwner && !$isStaff) {
        abort(404);
    }
    if ($item['item_status'] === 'removed' && $user['role'] !== 'admin') {
        abort(404);
    }

    $firstName = explode(' ', trim((string) $item['poster_name']))[0];

    view('items/show', [
        'title'     => $item['title'],
        'item'      => $item,
        'images'    => item_images_for($id),
        'poster'    => $firstName,
        'isOwner'   => $isOwner,
        'canEdit'   => $isOwner && $item['item_status'] === 'open' && user_can_act($user),
        'canClaim'  => claim_eligibility($user, $item) === null,
        'myClaim'   => $isOwner ? null : claim_has($id, (int) $user['user_id']),
        'canReview' => $isStaff && user_can_act($user) && $item['report_type'] === 'found' && !$isOwner,
        'scripts'   => ['js/gallery.js'],
    ]);
}