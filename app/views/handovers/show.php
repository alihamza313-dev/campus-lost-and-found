<?php
$active = $info['item_status'] === 'ready_for_handover';
$sched  = $handover && $handover['handover_status'] === 'scheduled';
$base   = '/claims/' . (int) $info['claim_id'] . '/handover';
?>
<p><a href="<?= e(url('/custodian')) ?>">&laquo; Custodian dashboard</a></p>
<h1>Handover for &ldquo;<?= e($info['title']) ?>&rdquo;</h1>

<table class="table">
    <tr><th scope="row">Claimant</th><td><?= e($info['claimant_name']) ?> (<?= e($info['claimant_email']) ?>)</td></tr>
    <tr><th scope="row">Item status</th><td><?= e(ucfirst(str_replace('_', ' ', $info['item_status']))) ?></td></tr>
    <tr><th scope="row">Claims filed on this item</th><td><?= (int) $info['claims_filed'] ?></td></tr>
    <tr><th scope="row">High value</th><td><?= (int) $info['is_high_value'] ? 'Yes' : 'No' ?></td></tr>
    <tr><th scope="row">Handover</th><td>
        <?php if ($handover): ?>
            <?= e(ucfirst($handover['handover_status'])) ?>, <?= e(substr((string) $handover['scheduled_at'], 0, 16)) ?>
            <?php if (!empty($handover['completed_at'])): ?> (completed <?= e(substr((string) $handover['completed_at'], 0, 16)) ?>)<?php endif; ?>
        <?php else: ?>Not scheduled yet<?php endif; ?>
    </td></tr>
    <?php if ($handover && !empty($handover['notes'])): ?>
        <tr><th scope="row">ID-check notes</th><td class="prewrap"><?= e($handover['notes']) ?></td></tr>
    <?php endif; ?>
</table>

<?php if (!$active): ?>
    <p class="muted">This item is no longer waiting for a handover, so there are no actions available.</p>
<?php else: ?>
    <form method="post" action="<?= e(url($base . '/schedule')) ?>" class="form">
        <?= csrf_field() ?>
        <h2><?= $sched ? 'Reschedule' : 'Schedule' ?> the handover</h2>
        <div class="field">
            <label for="scheduled_at">Date and time</label>
            <input id="scheduled_at" name="scheduled_at" type="datetime-local" min="<?= e($minWhen) ?>" required>
            <p class="hint">The claimant is notified and must bring their student ID.</p>
        </div>
        <button type="submit" class="btn"><?= $sched ? 'Reschedule' : 'Schedule handover' ?></button>
    </form>

    <?php if ($sched): ?>
        <form method="post" action="<?= e(url($base . '/complete')) ?>" class="form">
            <?= csrf_field() ?>
            <h2>Complete the handover</h2>
            <div class="field">
                <label class="choice"><input type="checkbox" name="id_verified" value="1" required> I checked the claimant&rsquo;s student ID in person and it matches.</label>
            </div>
            <div class="field">
                <label for="notes">ID-check notes <?= $needsNotes ? '(required)' : '(optional)' ?></label>
                <textarea id="notes" name="notes" rows="3" maxlength="255" <?= $needsNotes ? 'required' : '' ?>></textarea>
                <?php if ($needsNotes): ?><p class="hint">This item is high value or had several claims, so write what ID you saw.</p><?php endif; ?>
            </div>
            <button type="submit" class="btn" data-confirm="Mark this item as handed over? This cannot be undone.">Confirm handover</button>
        </form>
    <?php endif; ?>

    <form method="post" action="<?= e(url($base . '/revoke')) ?>" class="form">
        <?= csrf_field() ?>
        <h2>Revoke the approval</h2>
        <p class="muted">Use this for a no-show or a wrong decision. Any scheduled handover is cancelled and the item becomes open again. This claim cannot be approved again.</p>
        <div class="field">
            <label for="reason">Reason</label>
            <textarea id="reason" name="reason" rows="3" maxlength="1000" required></textarea>
        </div>
        <button type="submit" class="btn btn-danger" data-confirm="Revoke this approval? This cannot be undone.">Revoke approval</button>
    </form>
<?php endif; ?>