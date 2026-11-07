<?php $id = (int) $u['user_id']; ?>
<p><a href="<?= e(url('/admin/users')) ?>">&laquo; All users</a></p>
<h1><?= e($u['full_name']) ?></h1>

<table class="table">
    <tr><th scope="row">Email</th><td><?= e($u['university_email']) ?></td></tr>
    <tr><th scope="row">Roll number</th><td><?= e($u['student_id'] ?? '') ?></td></tr>
    <tr><th scope="row">Phone</th><td><?= e($u['phone'] ?? '') ?></td></tr>
    <tr><th scope="row">Role</th><td><?= e(ucfirst($u['role'])) ?></td></tr>
    <tr><th scope="row">Email status</th><td><?= e(ucfirst($u['verification_status'])) ?></td></tr>
    <tr><th scope="row">Account status</th><td><?= (int) $u['is_suspended'] ? 'Suspended' : ($u['account_status'] === 'suspended' ? 'Suspension ended (reactivate to clear)' : e(ucfirst($u['account_status']))) ?></td></tr>
    <?php if ($u['account_status'] === 'suspended'): ?>
        <tr><th scope="row">Suspended until</th><td><?= $u['suspended_until'] ? e($u['suspended_until']) : 'Until further notice' ?></td></tr>
        <tr><th scope="row">Reason</th><td class="prewrap"><?= e($u['suspension_reason'] ?? '') ?></td></tr>
    <?php endif; ?>
    <tr><th scope="row">Joined</th><td><?= e(substr((string) $u['created_at'], 0, 16)) ?></td></tr>
</table>

<?php if ($u['account_status'] === 'active'): ?>
    <form method="post" action="<?= e(url('/admin/users/' . $id . '/suspend')) ?>" class="form">
        <?= csrf_field() ?>
        <h2>Suspend</h2>
        <p class="muted">A suspended user can browse but cannot post, claim, message or report. Their pending claims are withdrawn.</p>
        <div class="field">
            <label for="reason">Reason</label>
            <textarea id="reason" name="reason" rows="3" maxlength="255" required></textarea>
        </div>
        <div class="field">
            <label for="days">Length</label>
            <select id="days" name="days">
                <option value="7">7 days</option>
                <option value="30">30 days</option>
                <option value="90">90 days</option>
                <option value="0">Until further notice</option>
            </select>
        </div>
        <button type="submit" class="btn btn-danger" data-confirm="Suspend this user? Their pending claims will be withdrawn.">Suspend user</button>
    </form>
<?php elseif ($u['account_status'] === 'suspended'): ?>
    <form method="post" action="<?= e(url('/admin/users/' . $id . '/reactivate')) ?>" class="form">
        <?= csrf_field() ?>
        <h2>Reactivate</h2>
        <button type="submit" class="btn" data-confirm="Reactivate this account?">Reactivate user</button>
    </form>
<?php endif; ?>

<?php if ($u['account_status'] !== 'deactivated'): ?>
    <form method="post" action="<?= e(url('/admin/users/' . $id . '/role')) ?>" class="form">
        <?= csrf_field() ?>
        <h2>Role</h2>
        <div class="field">
            <label for="role">Change role to</label>
            <select id="role" name="role">
                <?php foreach (['student', 'custodian', 'admin'] as $r): ?>
                    <option value="<?= e($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary" data-confirm="Change this user's role?">Change role</button>
    </form>
<?php endif; ?>

<h2>Recent history</h2>
<?php if (!$history): ?>
    <p class="muted">Nothing recorded for this user yet.</p>
<?php else: ?>
    <ul class="plain-list">
        <?php foreach ($history as $h): ?>
            <li><strong><?= e($h['action']) ?></strong> by <?= e($h['actor_name'] ?? 'System') ?>
                <span class="muted">&middot; <?= e(substr((string) $h['created_at'], 0, 16)) ?></span>
                <?php if (!empty($h['details'])): ?><br><span class="prewrap"><?= e($h['details']) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>