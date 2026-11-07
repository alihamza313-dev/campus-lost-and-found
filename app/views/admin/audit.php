<?php $base = ['action' => $f['action'], 'target_type' => $f['target_type'], 'actor_id' => $f['actor_id']]; ?>
<p><a href="<?= e(url('/admin')) ?>">&laquo; Admin dashboard</a></p>
<h1>Audit log</h1>
<p class="muted">Read-only history of important actions. Every view of private evidence is recorded here.</p>

<form method="get" action="<?= e(url('/admin/audit')) ?>" class="filters">
    <div class="field">
        <label for="action">Action</label>
        <select id="action" name="action">
            <option value="">All</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= e($a) ?>" <?= $f['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="target_type">Target type</label>
        <select id="target_type" name="target_type">
            <option value="">All</option>
            <?php foreach (['user', 'item', 'claim', 'handover', 'report', 'message', 'evidence'] as $t): ?>
                <option value="<?= e($t) ?>" <?= $f['target_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="actor_id">Actor user ID</label>
        <input id="actor_id" name="actor_id" type="number" min="1" value="<?= $f['actor_id'] > 0 ? (int) $f['actor_id'] : '' ?>">
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn">Filter</button>
        <a class="btn btn-secondary" href="<?= e(url('/admin/audit')) ?>">Clear</a>
    </div>
</form>

<p class="muted" role="status"><?= (int) $total ?> record<?= $total === 1 ? '' : 's' ?></p>

<?php if (!$rows): ?>
    <p class="empty">No records match.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">When</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Target</th><th scope="col">Details</th><th scope="col">IP</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e((string) $r['created_at']) ?></td>
                    <td><?= $r['actor_id'] === null ? 'System' : e($r['actor_name'] ?? 'User') . ' (#' . (int) $r['actor_id'] . ')' ?></td>
                    <td><?= e($r['action']) ?></td>
                    <td><?= e($r['target_type']) ?> #<?= (int) $r['target_id'] ?></td>
                    <td class="prewrap"><?= e($r['details'] ?? '') ?></td>
                    <td><?= e($r['ip_address'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php if ($page > 1): ?><a href="<?= e(page_url('/admin/audit', $base + ['page' => $page - 1])) ?>">&laquo; Newer</a><?php endif; ?>
            <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?><a href="<?= e(page_url('/admin/audit', $base + ['page' => $page + 1])) ?>">Older &raquo;</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>