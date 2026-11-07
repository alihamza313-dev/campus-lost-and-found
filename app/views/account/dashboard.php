<h1>Welcome, <?= e($user['full_name']) ?></h1>

<?php if ($user['verification_status'] === 'pending'): ?>
    <div class="alert alert-warning" role="status">
        <p>Your email is not verified yet. You can browse, but you cannot post, claim, message or report until you verify it.</p>
        <form method="post" action="<?= e(url('/verify/resend')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary">Send a new verification link</button>
        </form>
    </div>
<?php endif; ?>

<?php if ((int) $user['is_suspended']): ?>
    <div class="alert alert-error" role="alert">
        Your account is suspended. You can browse but cannot post, claim, message or report.
        <?php if (!empty($user['suspension_reason'])): ?>Reason: <?= e($user['suspension_reason']) ?><?php endif; ?>
    </div>
<?php endif; ?>

<p>
    <a class="btn" href="<?= e(url('/items')) ?>">Browse listings</a>
    <?php if (user_can_act($user)): ?>
        <a class="btn btn-secondary" href="<?= e(url('/items/new?type=lost')) ?>">Report a lost item</a>
        <a class="btn btn-secondary" href="<?= e(url('/items/new?type=found')) ?>">Report a found item</a>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('/my/items')) ?>">My listings</a>
</p>

<h2>Your activity</h2>
<table class="table">
    <tr><th scope="row">Listings you posted</th><td><?= (int) $stats['total'] ?></td></tr>
    <tr><th scope="row">Of those, still open</th><td><?= (int) $stats['open'] ?></td></tr>
</table>

<h2>Latest listings</h2>
<?php if (!$latest): ?>
    <p class="empty">Nothing has been posted yet.</p>
<?php else: ?>
    <ul class="plain-list">
        <?php foreach ($latest as $it): ?>
            <li>
                <span class="badge badge-<?= e($it['report_type']) ?>"><?= e(ucfirst($it['report_type'])) ?></span>
                <a href="<?= e(url('/items/' . (int) $it['item_id'])) ?>"><?= e($it['title']) ?></a>
                <span class="muted">&middot; <?= e($it['location_name']) ?> &middot; <?= e($it['incident_date']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Account</h2>
<table class="table">
    <tr><th scope="row">Email</th><td><?= e($user['university_email']) ?></td></tr>
    <tr><th scope="row">Role</th><td><?= e(ucfirst($user['role'])) ?></td></tr>
    <tr><th scope="row">Email status</th><td><?= e(ucfirst($user['verification_status'])) ?></td></tr>
    <tr><th scope="row">Account status</th><td><?= (int) $user['is_suspended'] ? 'Suspended' : e(ucfirst($user['account_status'])) ?></td></tr>
</table>

<p><a href="<?= e(url('/profile')) ?>">Edit profile or change password</a></p>