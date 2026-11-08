<?php
$base = [
    'q' => $f['q'], 'type' => $f['type'], 'status' => $f['status'],
    'category_id' => $f['category_id'], 'location_id' => $f['location_id'],
    'date_from' => $f['date_from'], 'date_to' => $f['date_to'],
];
$without = static fn (string ...$keys): array => array_diff_key($base, array_flip($keys));

$catName = '';
foreach ($categories as $c) { if ((int) $c['category_id'] === $f['category_id']) { $catName = $c['category_name']; } }
$locName = '';
foreach ($locations as $l) { if ((int) $l['location_id'] === $f['location_id']) { $locName = $l['location_name']; } }

$chips = [];
if ($f['q'] !== '')        { $chips[] = ['Keyword: ' . $f['q'], page_url('/items', $without('q'))]; }
if ($catName !== '')       { $chips[] = ['Category: ' . $catName, page_url('/items', $without('category_id'))]; }
if ($locName !== '')       { $chips[] = ['Location: ' . $locName, page_url('/items', $without('location_id'))]; }
if ($f['status'] !== '')   { $chips[] = ['Status: ' . ui_label($f['status']), page_url('/items', $without('status'))]; }
if ($f['date_from'] !== '') { $chips[] = ['From ' . ui_date($f['date_from']), page_url('/items', $without('date_from'))]; }
if ($f['date_to'] !== '')   { $chips[] = ['Until ' . ui_date($f['date_to']), page_url('/items', $without('date_to'))]; }

$advancedOpen = $catName !== '' || $locName !== '' || $f['status'] !== '' || $f['date_from'] !== '' || $f['date_to'] !== '';
$tabs = ['' => 'All', 'found' => 'Found items', 'lost' => 'Lost items'];
?>
<div class="page-head">
    <div>
        <h1>Browse listings</h1>
        <p class="muted">Lost something? Check the <strong>Found items</strong> tab first. Found something? Check <strong>Lost items</strong> to see if someone is looking for it.</p>
    </div>
</div>

<form method="get" action="<?= e(url('/items')) ?>" role="search" class="search-form">
    <?php if ($f['type'] !== ''): ?><input type="hidden" name="type" value="<?= e($f['type']) ?>"><?php endif; ?>

    <div class="searchbar">
        <label for="q" class="visually-hidden">Search listings</label>
        <input id="q" name="q" type="search" maxlength="100" value="<?= e($f['q']) ?>" placeholder="Try &ldquo;black wallet&rdquo; or &ldquo;calculator&rdquo;">
        <button type="submit" class="btn">Search</button>
    </div>

    <nav class="type-tabs" aria-label="Listing type">
        <?php foreach ($tabs as $v => $label): ?>
            <a href="<?= e(page_url('/items', ['type' => $v] + $without('type'))) ?>"<?= $f['type'] === $v ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <details class="more-filters"<?= $advancedOpen ? ' open' : '' ?>>
        <summary>More filters</summary>
        <div class="filters-grid">
            <div class="field">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="">Any category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['category_id'] ?>"<?= $f['category_id'] === (int) $c['category_id'] ? ' selected' : '' ?>><?= e($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="location_id">Where on campus</label>
                <select id="location_id" name="location_id">
                    <option value="">Anywhere</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= (int) $l['location_id'] ?>"<?= $f['location_id'] === (int) $l['location_id'] ? ' selected' : '' ?>><?= e($l['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Open or claim pending</option>
                    <option value="open"<?= $f['status'] === 'open' ? ' selected' : '' ?>>Open</option>
                    <option value="claim_pending"<?= $f['status'] === 'claim_pending' ? ' selected' : '' ?>>Claim pending</option>
                </select>
            </div>
            <div class="field">
                <label for="date_from">From date</label>
                <input id="date_from" name="date_from" type="date" value="<?= e($f['date_from']) ?>">
            </div>
            <div class="field">
                <label for="date_to">To date</label>
                <input id="date_to" name="date_to" type="date" value="<?= e($f['date_to']) ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-secondary">Apply filters</button>
            </div>
        </div>
    </details>
</form>

<?php if ($chips): ?>
    <div class="chips" aria-label="Active filters">
        <?php foreach ($chips as [$label, $href]): ?>
            <a class="chip" href="<?= e($href) ?>" aria-label="Remove filter <?= e($label) ?>"><?= e($label) ?> <span aria-hidden="true">&times;</span></a>
        <?php endforeach; ?>
        <a class="chip" href="<?= e(url('/items')) ?>">Clear all</a>
    </div>
<?php endif; ?>

<p class="muted" role="status"><?= (int) $total ?> listing<?= $total === 1 ? '' : 's' ?> found</p>

<?php if (!$items): ?>
    <div class="empty">
        <p><strong>Nothing matches that search.</strong></p>
        <p>Try fewer words, remove a filter, or post it yourself so others can find it.</p>
        <p>
            <a class="btn btn-secondary" href="<?= e(url('/items')) ?>">Clear search</a>
            <a class="btn" href="<?= e(url('/items/new?type=lost')) ?>">Report a lost item</a>
        </p>
    </div>
<?php else: ?>
    <ul class="listing-grid">
        <?php foreach ($items as $it): ?>
            <li class="listing-card">
                <div class="listing-thumb">
                    <?php if ($it['thumb']): ?>
                        <img src="<?= e(url('uploads/items/' . $it['thumb'])) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span>No photo</span>
                    <?php endif; ?>
                </div>
                <span class="listing-flag badge badge-<?= e($it['report_type']) ?>"><?= e(ucfirst($it['report_type'])) ?></span>
                <div class="listing-body">
                    <h2 class="listing-title"><a href="<?= e(url('/items/' . (int) $it['item_id'])) ?>"><?= e($it['title']) ?></a></h2>
                    <p class="listing-meta"><?= e($it['location_name']) ?> &middot; <?= e(ui_date($it['incident_date'])) ?></p>
                    <?php if ($it['item_status'] !== 'open'): ?>
                        <p class="listing-meta"><span class="badge badge-<?= e($it['item_status']) ?>"><?= e(ui_label($it['item_status'])) ?></span></p>
                    <?php endif; ?>
                    <p class="listing-snippet"><?= e($it['snippet']) ?></p>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a class="btn btn-sm btn-secondary" href="<?= e(page_url('/items', $base + ['page' => $page - 1])) ?>">&laquo; Previous</a>
            <?php endif; ?>
            <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-sm btn-secondary" href="<?= e(page_url('/items', $base + ['page' => $page + 1])) ?>">Next &raquo;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
