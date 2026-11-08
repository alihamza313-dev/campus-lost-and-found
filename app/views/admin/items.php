<?php $base = ['q' => $f['q'], 'status' => $f['status']]; ?>
<p><a href="<?= e(url('/admin')) ?>">&laquo; Admin dashboard</a></p>
<h1>Listings</h1>

<form method="get" action="<?= e(url('/admin/items')) ?>" class="filters" role="search">
    <div class="field field-wide"><label for="q">Title</label>
        <input id="q" name="q" type="search" maxlength="100" value="<?= e($f['q']) ?>"></div>
    <div class="field"><label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['open', 'claim_pending', 'ready_for_handover', 'recovered', 'closed', 'removed'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
            <?php endforeach; ?>
        </select></div>
    <div class="filter-actions">
        <button type="submit" class="btn">Search</button>
        <a class="btn btn-secondary" href="<?= e(url('/admin/items')) ?>">Clear</a>
    </div>
</form>

<p class="muted" role="status"><?= (int) $total ?> listing<?= $total === 1 ? '' : 's' ?></p>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th scope="col">Title</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Posted by</th><th scope="col">Remove</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $it): ?>
            <tr>
                <td><a href="<?= e(url('/items/' . (int) $it['item_id'])) ?>"><?= e($it['title']) ?></a></td>
                <td><?= e(ucfirst($it['report_type'])) ?></td>
                <td><span class="badge badge-<?= e($it['item_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $it['item_status']))) ?></span></td>
                <td><?= e($it['poster_name']) ?></td>
                <td>
                    <?php if ($it['item_status'] !== 'removed'): ?>
                        <form method="post" action="<?= e(url('/admin/items/' . (int) $it['item_id'] . '/remove')) ?>" class="inline-edit" data-confirm="Remove this listing? Active claims will be rejected. This cannot be undone.">
                            <?= csrf_field() ?>
                            <label class="visually-hidden" for="rr-<?= (int) $it['item_id'] ?>">Reason</label>
                            <input id="rr-<?= (int) $it['item_id'] ?>" name="reason" type="text" maxlength="255" placeholder="Reason" required>
                            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                        </form>
                    <?php else: ?><span class="muted">Removed</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($page > 1): ?><a href="<?= e(page_url('/admin/items', $base + ['page' => $page - 1])) ?>">&laquo; Previous</a><?php endif; ?>
        <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
        <?php if ($page < $pages): ?><a href="<?= e(page_url('/admin/items', $base + ['page' => $page + 1])) ?>">Next &raquo;</a><?php endif; ?>
    </nav>
<?php endif; ?>