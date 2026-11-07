<?php $base = ['q' => $f['q'], 'role' => $f['role'], 'status' => $f['status']]; ?>
<div class="page-head">
    <h1>Users</h1>
    <a class="btn" href="<?= e(url('/admin/staff/new')) ?>">Create custodian</a>
</div>

<form method="get" action="<?= e(url('/admin/users')) ?>" class="filters" role="search">
    <div class="field field-wide">
        <label for="q">Name, email or roll number</label>
        <input id="q" name="q" type="search" maxlength="100" value="<?= e($f['q']) ?>">
    </div>
    <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="">All</option>
            <?php foreach (['student', 'custodian', 'admin'] as $r): ?>
                <option value="<?= e($r) ?>" <?= $f['role'] === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['active' => 'Active', 'suspended' => 'Suspended now', 'deactivated' => 'Deactivated', 'unverified' => 'Email unverified'] as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= $f['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn">Search</button>
        <a class="btn btn-secondary" href="<?= e(url('/admin/users')) ?>">Clear</a>
    </div>
</form>

<p class="muted" role="status"><?= (int) $total ?> user<?= $total === 1 ? '' : 's' ?></p>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Email</th><th scope="col">Account</th><th scope="col"></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['full_name']) ?></td>
                <td><?= e($u['university_email']) ?></td>
                <td><?= e(ucfirst($u['role'])) ?></td>
                <td><?= e(ucfirst($u['verification_status'])) ?></td>
                <td><?php
                    if ((int) $u['is_suspended']) { echo 'Suspended'; }
                    elseif ($u['account_status'] === 'suspended') { echo 'Suspension ended'; }
                    else { echo e(ucfirst($u['account_status'])); }
                ?></td>
                <td><a class="btn btn-sm btn-secondary" href="<?= e(url('/admin/users/' . (int) $u['user_id'])) ?>">Manage</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($page > 1): ?><a href="<?= e(page_url('/admin/users', $base + ['page' => $page - 1])) ?>">&laquo; Previous</a><?php endif; ?>
        <span>Page <?= (int) $page ?> of <?= (int) $pages ?></span>
        <?php if ($page < $pages): ?><a href="<?= e(page_url('/admin/users', $base + ['page' => $page + 1])) ?>">Next &raquo;</a><?php endif; ?>
    </nav>
<?php endif; ?>