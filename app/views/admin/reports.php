<?php $reasons = REPORT_REASONS; $base = ['status' => $f['status']]; ?>
<p><a href="<?= e(url('/admin')) ?>">&laquo; Admin dashboard</a></p>
<h1>Reports</h1>

<form method="get" action="<?= e(url('/admin/reports')) ?>" class="filters">
    <div class="field">
        <label for="status">Show</label>
        <select id="status" name="status">
            <option value="">Needs attention (open and under review)</option>
            <?php foreach (['open' => 'Open', 'under_review' => 'Under review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= $f['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions"><button type="submit" class="btn">Filter</button></div>
</form>

<p class="muted" role="status"><?= (int) $total ?> report<?= $total === 1 ? '' : 's' ?></p>

<?php if (!$rows): ?>
    <p class="empty">No reports here.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">#</th><th scope="col">About</th><th scope="col">Reason</th><th scope="col">Reported by</th><th scope="col">Status</th><th scope="col">Filed</th><th scope="col"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int) $r['report_id'] ?></td>
                    <td><?php
                        if ($r['message_id'] !== null)   { echo 'Message by ' . e($r['reported_name'] ?? 'user'); }
                        elseif ($r['item_id'] !== null)  { echo 'Listing: ' . e($r['item_title'] ?? ''); }
                        else                             { echo 'User: ' . e($r['reported_name'] ?? ''); }
                    ?></td>
                    <td><?= e($reasons[$r['reason']] ?? $r['reason']) ?></td>
                    <td><?= e($r['reporter_name']) ?></td>
                    <td><span class="badge badge-<?= e($r['report_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $r['report_status']))) ?></span></td>
                    <td><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
                    <td><a class="btn btn-sm" href="<?= e(url('/admin/reports/' . (int) $r['report_id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php if ($page > 1): ?><a href="<?= e(page_url('/admin/reports', $base + ['page' => $page - 1])) ?>">&laquo; Previous</a><?php endif; ?>
            <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?><a href="<?= e(page_url('/admin/reports', $base + ['page' => $page + 1])) ?>">Next &raquo;</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>