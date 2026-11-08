<?php
$back = $isOwner ? '/my/claims' : '/review/items/' . (int) $claim['item_id'];
$steps = ui_claim_steps($claim['claim_status'], $handover);
?>
<p class="crumbs"><a href="<?= e(url($back)) ?>">&laquo; <?= $isOwner ? 'My claims' : 'Claims for this item' ?></a></p>

<div class="page-head">
    <div>
        <h1>Claim on &ldquo;<?= e($claim['title']) ?>&rdquo;</h1>
        <p><span class="badge badge-<?= e($claim['claim_status']) ?>"><?= e(ui_label($claim['claim_status'])) ?></span></p>
    </div>
</div>

<ol class="steps" aria-label="Claim progress">
    <?php foreach ($steps as [$label, $note, $state]): ?>
        <li class="is-<?= e($state) ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
            <span class="step-label"><?= e($label) ?></span>
            <span class="step-note"><?= e($note) ?></span>
        </li>
    <?php endforeach; ?>
</ol>

<?php if ($isOwner && $claim['claim_status'] === 'approved'): ?>
    <?php if ($handover && $handover['handover_status'] === 'scheduled'): ?>
        <div class="alert alert-success" role="status"><strong>Pickup scheduled for <?= e(substr((string) $handover['scheduled_at'], 0, 16)) ?>.</strong> Bring your student ID. The item is only handed over after your ID is checked.</div>
    <?php elseif ($handover && $handover['handover_status'] === 'completed'): ?>
        <div class="alert alert-success" role="status">This item was handed over on <?= e(substr((string) $handover['completed_at'], 0, 16)) ?>.</div>
    <?php else: ?>
        <div class="alert alert-success" role="status"><strong>Your claim was approved.</strong> A custodian will arrange the handover. Bring your student ID.</div>
    <?php endif; ?>
<?php elseif ($handover && !$isOwner && $handover['handover_status'] === 'scheduled'): ?>
    <div class="alert alert-success" role="status">Handover scheduled for <?= e(substr((string) $handover['scheduled_at'], 0, 16)) ?>.</div>
<?php elseif ($handover && !$isOwner && $handover['handover_status'] === 'completed'): ?>
    <div class="alert alert-success" role="status">Handed over on <?= e(substr((string) $handover['completed_at'], 0, 16)) ?>.</div>
<?php endif; ?>

<div class="item-layout">
    <div>
        <h2>Ownership statement</h2>
        <div class="panel"><p class="prewrap"><?= e($claim['ownership_details']) ?></p></div>

        <?php if ($proofId): ?>
            <p><a class="btn btn-secondary" href="<?= e(url('/evidence/' . $proofId)) ?>">View proof file</a></p>
        <?php endif; ?>

        <?php if (!empty($claim['review_reason'])): ?>
            <h2>Decision</h2>
            <div class="panel">
                <p class="prewrap"><?= e($claim['review_reason']) ?></p>
                <p class="muted"><?= e($claim['reviewed_at'] ?? '') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <aside class="item-side">
        <div class="panel">
            <h2 class="panel-title">Summary</h2>
            <dl class="facts">
                <div><dt>Submitted</dt><dd><?= e(ui_date(substr((string) $claim['created_at'], 0, 10))) ?></dd></div>
                <div><dt>Item status</dt><dd><?= e(ui_label($claim['item_status'])) ?></dd></div>
                <?php if (!$isOwner): ?>
                    <div><dt>Claimant</dt><dd><?= e($claim['claimant_name']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($claim['claimant_email']) ?></dd></div>
                <?php endif; ?>
            </dl>
            <p class="small-links"><a href="<?= e(url('/items/' . (int) $claim['item_id'])) ?>">View the listing</a></p>
        </div>

        <?php if (!$isOwner && $isStaff && $claim['claim_status'] === 'approved'): ?>
            <div class="panel panel-action">
                <h2 class="panel-title">Next step</h2>
                <div class="action-stack">
                    <a class="btn" href="<?= e(url('/claims/' . (int) $claim['claim_id'] . '/handover')) ?>">Manage handover</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canWithdraw): ?>
            <div class="panel">
                <h2 class="panel-title">Changed your mind?</h2>
                <p class="status-help">You can withdraw this claim while it is still being reviewed. You will not be able to claim this item again.</p>
                <form method="post" action="<?= e(url('/claims/' . (int) $claim['claim_id'] . '/withdraw')) ?>" data-confirm="Withdraw this claim? You cannot claim this item again.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-block">Withdraw claim</button>
                </form>
            </div>
        <?php endif; ?>
    </aside>
</div>
