<p><a href="<?= e(url('/review')) ?>">&laquo; Review queue</a></p>
<h1>Claims for &ldquo;<?= e($item['title']) ?>&rdquo;</h1>
<p class="meta">
    <span class="badge badge-<?= e($item['item_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $item['item_status']))) ?></span>
    <?= e($item['category_name']) ?> &middot; <?= e($item['location_name']) ?> &middot; found <?= e($item['incident_date']) ?>
    &middot; posted by <?= e($item['poster_name']) ?>
</p>

<?php if ($detailId): ?>
    <p><a class="btn btn-secondary" href="<?= e(url('/evidence/' . $detailId)) ?>">View the finder&rsquo;s private detail</a>
       <span class="muted">Every view of private evidence is logged.</span></p>
<?php else: ?>
    <p class="muted">The finder did not record a private detail for this item.</p>
<?php endif; ?>

<?php if (!$claims): ?>
    <p class="empty">There are no claims on this item.</p>
<?php endif; ?>

<?php foreach ($claims as $c):
    $active = in_array($c['claim_status'], ['pending', 'under_review'], true);
    $mine   = (int) $c['claimant_id'] === (int) $me['user_id'];
?>
    <section class="review-claim">
        <h2>Claim #<?= (int) $c['claim_id'] ?>
            <span class="badge badge-<?= e($c['claim_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $c['claim_status']))) ?></span></h2>
        <p class="meta">By <?= e($c['claimant_name']) ?> (<?= e($c['claimant_email']) ?>) &middot; submitted <?= e($c['created_at']) ?></p>

        <h3>Ownership statement</h3>
        <p class="prewrap"><?= e($c['ownership_details']) ?></p>

        <?php if ($c['proof_id']): ?>
            <p><a class="btn btn-sm btn-secondary" href="<?= e(url('/evidence/' . (int) $c['proof_id'])) ?>">View proof file</a></p>
        <?php else: ?>
            <p class="muted">No proof file attached.</p>
        <?php endif; ?>

        <?php if (!empty($c['review_reason'])): ?>
            <p><strong>Decision reason:</strong> <?= e($c['review_reason']) ?></p>
        <?php endif; ?>

                <?php if ($c['claim_status'] === 'approved' && (int) $item['reporter_id'] !== (int) $me['user_id'] && !$mine): ?>
            <p><a class="btn btn-sm" href="<?= e(url('/claims/' . (int) $c['claim_id'] . '/handover')) ?>">Manage handover</a></p>
        <?php endif; ?>

        <?php if ($active && $mine): ?>
            <p class="muted">You cannot review your own claim.</p>
        <?php elseif ($active): ?>
            <form method="post" action="<?= e(url('/claims/' . (int) $c['claim_id'] . '/approve')) ?>" class="form">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="reason-<?= (int) $c['claim_id'] ?>">Written reason (required for both decisions)</label>
                    <textarea id="reason-<?= (int) $c['claim_id'] ?>" name="reason" rows="3" maxlength="1000" required></textarea>
                </div>
                <div class="field">
                    <label class="choice"><input type="checkbox" name="high_value" value="1" <?= (int) $item['is_high_value'] ? 'checked' : '' ?>> Mark this item as high value (if approved, a written ID check will be required at handover)</label>
                </div>
                <button type="submit" class="btn" data-confirm="Approve this claim? All other active claims on this item will be rejected.">Approve this claim</button>
                <button type="submit" class="btn btn-danger" formaction="<?= e(url('/claims/' . (int) $c['claim_id'] . '/reject')) ?>">Reject this claim</button>
            </form>
        <?php endif; ?>
    </section>
<?php endforeach; ?>