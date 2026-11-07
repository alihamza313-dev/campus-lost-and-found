<p><a href="<?= e(url($isOwner ? '/my/claims' : '/review/items/' . (int) $claim['item_id'])) ?>">&laquo; Back</a></p>

<h1>Claim on &ldquo;<?= e($claim['title']) ?>&rdquo;</h1>
<p><span class="badge badge-<?= e($claim['claim_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $claim['claim_status']))) ?></span></p>

<table class="table">
    <tr><th scope="row">Submitted</th><td><?= e($claim['created_at']) ?></td></tr>
    <tr><th scope="row">Item status</th><td><?= e(ucfirst(str_replace('_', ' ', $claim['item_status']))) ?></td></tr>
    <?php if (!$isOwner): ?>
        <tr><th scope="row">Claimant</th><td><?= e($claim['claimant_name']) ?> (<?= e($claim['claimant_email']) ?>)</td></tr>
    <?php endif; ?>
</table>

<h2>Ownership statement</h2>
<p class="prewrap"><?= e($claim['ownership_details']) ?></p>

<?php if ($proofId): ?>
    <p><a class="btn btn-secondary" href="<?= e(url('/evidence/' . $proofId)) ?>">View proof file</a></p>
<?php endif; ?>

<?php if (!empty($claim['review_reason'])): ?>
    <h2>Decision</h2>
    <p class="prewrap"><?= e($claim['review_reason']) ?></p>
    <p class="muted"><?= e($claim['reviewed_at'] ?? '') ?></p>
<?php endif; ?>

<?php if ($isOwner && $claim['claim_status'] === 'approved'): ?>
    <div class="alert alert-success" role="status">Your claim was approved. A custodian will arrange the handover. Bring your student ID.</div>
<?php endif; ?>

<?php if ($canWithdraw): ?>
    <form method="post" action="<?= e(url('/claims/' . (int) $claim['claim_id'] . '/withdraw')) ?>" data-confirm="Withdraw this claim? You cannot claim this item again.">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger">Withdraw claim</button>
    </form>
<?php endif; ?>