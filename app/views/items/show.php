<p><a href="<?= e(url('/items')) ?>">&laquo; Back to listings</a></p>

<article class="detail">
    <h1><?= e($item['title']) ?></h1>
    <p class="meta">
        <span class="badge badge-<?= e($item['report_type']) ?>"><?= e(ucfirst($item['report_type'])) ?></span>
        <span class="badge badge-<?= e($item['item_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $item['item_status']))) ?></span>
    </p>

    <?php if ($images): ?>
        <div class="gallery">
            <img id="gallery-main" class="gallery-main" src="<?= e(url('uploads/items/' . $images[0]['image_path'])) ?>" alt="Photo 1 of <?= count($images) ?> for <?= e($item['title']) ?>">
            <?php if (count($images) > 1): ?>
                <div class="thumbs">
                    <?php foreach ($images as $n => $img): ?>
                        <a class="gallery-thumb" href="<?= e(url('uploads/items/' . $img['image_path'])) ?>"
                           data-alt="Photo <?= $n + 1 ?> of <?= count($images) ?> for <?= e($item['title']) ?>"
                           <?= $n === 0 ? 'aria-current="true"' : '' ?>>
                            <img src="<?= e(url('uploads/items/' . $img['image_path'])) ?>" alt="Show photo <?= $n + 1 ?>" width="72" height="72" loading="lazy">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="muted">No photos were added to this listing.</p>
    <?php endif; ?>

    <table class="table">
        <tr><th scope="row">Category</th><td><?= e($item['category_name']) ?></td></tr>
        <tr><th scope="row">Location</th><td><?= e($item['location_name']) ?></td></tr>
        <tr><th scope="row"><?= $item['report_type'] === 'found' ? 'Date found' : 'Date lost' ?></th><td><?= e($item['incident_date']) ?></td></tr>
        <tr><th scope="row">Posted by</th><td><?= e($poster) ?></td></tr>
        <tr><th scope="row">Posted on</th><td><?= e(substr((string) $item['created_at'], 0, 10)) ?></td></tr>
    </table>

    <h2>Description</h2>
    <p class="prewrap"><?= e($item['description']) ?></p>

    <?php if ($canEdit): ?>
        <p><a class="btn btn-secondary" href="<?= e(url('/items/' . (int) $item['item_id'] . '/edit')) ?>">Edit this listing</a></p>
    <?php endif; ?>

    <?php if ($canMessage): ?>
        <p><a class="btn btn-secondary" href="<?= e(url('/messages/' . (int) $item['item_id'] . '/' . (int) $item['reporter_id'])) ?>">Message the poster</a></p>
    <?php endif; ?>

        <?php if ($canClaim): ?>
        <p><a class="btn" href="<?= e(url('/items/' . (int) $item['item_id'] . '/claim')) ?>">Claim this item</a></p>
    <?php elseif ($myClaim): ?>
        <p>You already claimed this item (<?= e(str_replace('_', ' ', $myClaim['claim_status'])) ?>).
           <a href="<?= e(url('/claims/' . (int) $myClaim['claim_id'])) ?>">View your claim</a></p>
    <?php endif; ?>
    <?php if ($canReview): ?>
        <p><a class="btn btn-secondary" href="<?= e(url('/review/items/' . (int) $item['item_id'])) ?>">Review claims for this item</a></p>
    <?php endif; ?>
</article>