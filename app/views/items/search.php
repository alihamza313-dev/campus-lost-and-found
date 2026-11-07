<?php
$typeLabel = ['lost' => 'Lost', 'found' => 'Found'];
$statusLabel = ['open' => 'Open', 'claim_pending' => 'Claim pending'];
$base = [
    'q' => $f['q'], 'type' => $f['type'], 'status' => $f['status'],
    'category_id' => $f['category_id'], 'location_id' => $f['location_id'],
    'date_from' => $f['date_from'], 'date_to' => $f['date_to'],
];
?>
<h1>Browse listings</h1>

<form method="get" action="<?= e(url('/items')) ?>" class="filters" role="search">
    <div class="field field-wide">
        <label for="q">Keyword</label>
        <input id="q" name="q" type="search" maxlength="100" value="<?= e($f['q']) ?>" placeholder="e.g. calculator, black wallet">
    </div>
    <div class="field">
        <label for="type">Type</label>
        <select id="type" name="type">
            <option value="">All</option>
            <?php foreach ($typeLabel as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= $f['type'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
            <option value="">All</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['category_id'] ?>" <?= $f['category_id'] === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="location_id">Location</label>
        <select id="location_id" name="location_id">
            <option value="">All</option>
            <?php foreach ($locations as $l): ?>
                <option value="<?= (int) $l['location_id'] ?>" <?= $f['location_id'] === (int) $l['location_id'] ? 'selected' : '' ?>><?= e($l['location_name']) ?></option>
            <?php endforeach; ?>
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
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">Open and claim pending</option>
            <?php foreach ($statusLabel as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= $f['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn">Search</button>
        <a class="btn btn-secondary" href="<?= e(url('/items')) ?>">Clear</a>
    </div>
</form>

<p class="muted" role="status"><?= (int) $total ?> listing<?= $total === 1 ? '' : 's' ?> found</p>

<?php if (!$items): ?>
    <p class="empty">No listings match your search. Try fewer words or clear the filters.</p>
<?php else: ?>
    <ul class="results">
        <?php foreach ($items as $it): ?>
            <li class="result">
                <a class="result-thumb" href="<?= e(url('/items/' . (int) $it['item_id'])) ?>" tabindex="-1" aria-hidden="true">
                    <?php if ($it['thumb']): ?>
                        <img src="<?= e(url('uploads/items/' . $it['thumb'])) ?>" alt="" width="96" height="96" loading="lazy">
                    <?php else: ?>
                        <span class="thumb-empty">No image</span>
                    <?php endif; ?>
                </a>
                <div class="result-body">
                    <h2><a href="<?= e(url('/items/' . (int) $it['item_id'])) ?>"><?= e($it['title']) ?></a></h2>
                    <p class="meta">
                        <span class="badge badge-<?= e($it['report_type']) ?>"><?= e(ucfirst($it['report_type'])) ?></span>
                        <span class="badge badge-<?= e($it['item_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $it['item_status']))) ?></span>
                        <?= e($it['category_name']) ?> &middot; <?= e($it['location_name']) ?> &middot; <?= e($it['incident_date']) ?>
                    </p>
                    <p><?= e($it['snippet']) ?><?= mb_strlen($it['snippet']) >= 160 ? '&hellip;' : '' ?></p>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a href="<?= e(page_url('/items', $base + ['page' => $page - 1])) ?>">&laquo; Previous</a>
            <?php endif; ?>
            <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?>
                <a href="<?= e(page_url('/items', $base + ['page' => $page + 1])) ?>">Next &raquo;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>