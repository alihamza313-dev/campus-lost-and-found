<?php
$id      = (int) $r['report_id'];
$reasons = REPORT_REASONS;
$status  = $r['report_status'];
$canAct  = !$involved;
?>
<p><a href="<?= e(url('/admin/reports')) ?>">&laquo; All reports</a></p>
<h1>Report #<?= $id ?>
    <span class="badge badge-<?= e($status) ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span></h1>

<?php if ($involved): ?>
    <div class="alert alert-warning" role="alert">This report is about you, so another admin must handle it. You can read it but not act on it.</div>
<?php endif; ?>

<table class="table">
    <tr><th scope="row">Reason</th><td><?= e($reasons[$r['reason']] ?? $r['reason']) ?></td></tr>
    <tr><th scope="row">Details</th><td class="prewrap"><?= e($r['details'] ?? '') ?></td></tr>
    <tr><th scope="row">Reported by</th><td><?= e($r['reporter_name']) ?> (<?= e($r['reporter_email']) ?>)</td></tr>
    <tr><th scope="row">Filed</th><td><?= e((string) $r['created_at']) ?></td></tr>
    <?php if ($r['reported_user_id'] !== null): ?>
        <tr><th scope="row">Reported user</th><td>
            <a href="<?= e(url('/admin/users/' . (int) $r['reported_user_id'])) ?>"><?= e($r['reported_name']) ?></a>
            (<?= e($r['reported_email']) ?>, <?= e($r['reported_role']) ?>, <?= e($r['reported_account_status']) ?>)</td></tr>
    <?php endif; ?>
    <?php if ($r['item_id'] !== null): ?>
        <tr><th scope="row">Listing</th><td>
            <a href="<?= e(url('/items/' . (int) $r['item_id'])) ?>"><?= e($r['item_title']) ?></a>
            (<?= e(str_replace('_', ' ', (string) $r['item_status'])) ?>)</td></tr>
    <?php endif; ?>
    <?php if ($kind === 'message'): ?>
        <tr><th scope="row">Reported message</th><td class="prewrap"><?= e($r['message_text']) ?>
            <?php if ($r['message_status'] === 'removed'): ?><br><em class="muted">Already removed.</em><?php endif; ?></td></tr>
    <?php endif; ?>
    <?php if ($r['moderator_name']): ?>
        <tr><th scope="row">Handled by</th><td><?= e($r['moderator_name']) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($r['resolution_notes'])): ?>
        <tr><th scope="row">Notes</th><td class="prewrap"><?= e($r['resolution_notes']) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($r['resolved_at'])): ?>
        <tr><th scope="row">Closed</th><td><?= e((string) $r['resolved_at']) ?></td></tr>
    <?php endif; ?>
</table>

<?php if ($canAct && $status === 'open'): ?>
    <form method="post" action="<?= e(url('/admin/reports/' . $id . '/review')) ?>" class="form">
        <?= csrf_field() ?>
        <h2>Start the review</h2>
        <p class="muted">Take this report before you decide. It then shows as under review.</p>
        <button type="submit" class="btn">Start review</button>
    </form>
<?php endif; ?>

<?php if ($canAct && $status === 'under_review'): ?>

    <?php if ($kind === 'item' && $r['item_status'] !== 'removed'): ?>
        <form method="post" action="<?= e(url('/admin/reports/' . $id . '/remove-item')) ?>" class="form">
            <?= csrf_field() ?>
            <h2>Remove the listing</h2>
            <p class="muted">Active claims on it are rejected, a scheduled handover is cancelled, and this report is resolved.</p>
            <div class="field"><label for="ri-reason">Reason</label>
                <input id="ri-reason" name="reason" type="text" maxlength="255" required></div>
            <button type="submit" class="btn btn-danger" data-confirm="Remove this listing? This cannot be undone.">Remove listing</button>
        </form>
    <?php endif; ?>

    <?php if ($kind === 'message' && $r['message_status'] !== 'removed'): ?>
        <form method="post" action="<?= e(url('/admin/reports/' . $id . '/remove-message')) ?>" class="form">
            <?= csrf_field() ?>
            <h2>Remove the message</h2>
            <div class="field"><label for="rm-reason">Reason</label>
                <input id="rm-reason" name="reason" type="text" maxlength="255" required></div>
            <button type="submit" class="btn btn-danger" data-confirm="Remove this message? This cannot be undone.">Remove message</button>
        </form>
    <?php endif; ?>

    <?php if ($r['reported_user_id'] !== null && $r['reported_account_status'] === 'active'): ?>
        <form method="post" action="<?= e(url('/admin/reports/' . $id . '/suspend')) ?>" class="form">
            <?= csrf_field() ?>
            <h2>Suspend the reported user</h2>
            <p class="muted">Their pending claims are withdrawn and this report is resolved.</p>
            <div class="field"><label for="su-reason">Reason</label>
                <input id="su-reason" name="reason" type="text" maxlength="255" required></div>
            <div class="field"><label for="su-days">Length</label>
                <select id="su-days" name="days">
                    <option value="7">7 days</option><option value="30">30 days</option>
                    <option value="90">90 days</option><option value="0">Until further notice</option>
                </select></div>
            <button type="submit" class="btn btn-danger" data-confirm="Suspend this user?">Suspend user</button>
        </form>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/admin/reports/' . $id . '/resolve')) ?>" class="form">
        <?= csrf_field() ?>
        <h2>Resolve without further action</h2>
        <div class="field"><label for="rs-notes">Notes</label>
            <textarea id="rs-notes" name="notes" rows="3" maxlength="1000" required></textarea></div>
        <button type="submit" class="btn btn-secondary" data-confirm="Mark this report as resolved?">Resolve</button>
    </form>

    <form method="post" action="<?= e(url('/admin/reports/' . $id . '/dismiss')) ?>" class="form">
        <?= csrf_field() ?>
        <h2>Dismiss</h2>
        <p class="muted">Use this when the report is not a real problem.</p>
        <div class="field"><label for="di-notes">Notes</label>
            <textarea id="di-notes" name="notes" rows="3" maxlength="1000" required></textarea></div>
        <button type="submit" class="btn btn-secondary" data-confirm="Dismiss this report?">Dismiss</button>
    </form>
<?php endif; ?>