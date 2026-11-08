<h1>Admin dashboard</h1>

<div class="stat-grid">
    <div class="stat"><span class="stat-n"><?= (int) $users['total'] ?></span> users</div>
    <div class="stat"><span class="stat-n"><?= (int) $users['students'] ?></span> students</div>
    <div class="stat"><span class="stat-n"><?= (int) $users['custodians'] ?></span> custodians</div>
    <div class="stat"><span class="stat-n"><?= (int) $users['admins'] ?></span> admins</div>
    <div class="stat"><span class="stat-n"><?= (int) $users['suspended'] ?></span> suspended</div>
    <div class="stat"><span class="stat-n"><?= (int) $users['unverified'] ?></span> unverified</div>
    <div class="stat"><span class="stat-n"><?= (int) $reports ?></span> open reports</div>
</div>

<h2>Listings by status</h2>
<?php if (!$items): ?>
    <p class="empty">No listings yet.</p>
<?php else: ?>
    <table class="table">
        <?php foreach ($items as $status => $n): ?>
            <tr><th scope="row"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></th><td><?= (int) $n ?></td></tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<h2>Manage</h2>
<p>
    <a class="btn" href="<?= e(url('/admin/reports')) ?>">Reports<?= (int) $reports > 0 ? ' (' . (int) $reports . ')' : '' ?></a>
    <a class="btn btn-secondary" href="<?= e(url('/admin/users')) ?>">Users</a>
    <a class="btn btn-secondary" href="<?= e(url('/admin/items')) ?>">Listings</a>
    <a class="btn btn-secondary" href="<?= e(url('/admin/staff/new')) ?>">Create custodian</a>
    <a class="btn btn-secondary" href="<?= e(url('/admin/lookups')) ?>">Categories and locations</a>
    <a class="btn btn-secondary" href="<?= e(url('/admin/audit')) ?>">Audit log</a>
</p>