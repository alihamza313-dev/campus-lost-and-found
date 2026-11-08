<div class="page-head">
    <h1>Admin dashboard</h1>
</div>

<div class="stat-grid">
    <div class="stat<?= (int) $reports > 0 ? ' stat-alert' : '' ?>"><span class="stat-n"><?= (int) $reports ?></span><span class="stat-l">open reports</span></div>
    <div class="stat"><span class="stat-n"><?= (int) $users['total'] ?></span><span class="stat-l">users</span></div>
    <div class="stat"><span class="stat-n"><?= (int) $users['students'] ?></span><span class="stat-l">students</span></div>
    <div class="stat"><span class="stat-n"><?= (int) $users['custodians'] ?></span><span class="stat-l">custodians</span></div>
    <div class="stat"><span class="stat-n"><?= (int) $users['admins'] ?></span><span class="stat-l">admins</span></div>
    <div class="stat"><span class="stat-n"><?= (int) $users['suspended'] ?></span><span class="stat-l">suspended</span></div>
    <div class="stat"><span class="stat-n"><?= (int) $users['unverified'] ?></span><span class="stat-l">unverified</span></div>
</div>

<h2 class="first-h2">Manage</h2>
<div class="link-grid">
    <a class="tile<?= (int) $reports > 0 ? ' tile-lost' : '' ?>" href="<?= e(url('/admin/reports')) ?>"><strong>Reports<?= (int) $reports > 0 ? ' (' . (int) $reports . ')' : '' ?></strong><span>Review reported listings, messages and users.</span></a>
    <a class="tile" href="<?= e(url('/admin/users')) ?>"><strong>Users</strong><span>Search, suspend and change roles.</span></a>
    <a class="tile" href="<?= e(url('/admin/items')) ?>"><strong>Listings</strong><span>Find and remove listings.</span></a>
    <a class="tile" href="<?= e(url('/admin/staff/new')) ?>"><strong>Create custodian</strong><span>Add a new staff account.</span></a>
    <a class="tile" href="<?= e(url('/admin/lookups')) ?>"><strong>Categories and locations</strong><span>Edit the lists used in listings.</span></a>
    <a class="tile" href="<?= e(url('/admin/audit')) ?>"><strong>Audit log</strong><span>Read-only history of key actions.</span></a>
</div>

<h2>Listings by status</h2>
<?php if (!$items): ?>
    <p class="empty">No listings yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Status</th><th scope="col">Listings</th></tr></thead>
            <tbody>
            <?php foreach ($items as $status => $n): ?>
                <tr><td><span class="badge badge-<?= e($status) ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span></td><td><?= (int) $n ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>