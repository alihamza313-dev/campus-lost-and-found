<?php
$id       = (int) $item['item_id'];
$isFound  = $item['report_type'] === 'found';
$verified = ($user['verification_status'] ?? '') === 'verified';
$canReport = !$isOwner && user_can_act($user) && in_array($item['item_status'], ITEM_PUBLIC_STATUSES, true);
?>
<p class="crumbs"><a href="<?= e(url('/items')) ?>">&laquo; All listings</a></p>

<div class="item-layout">
    <article>
        <header class="item-head">
            <span class="badge badge-<?= e($item['report_type']) ?>"><?= $isFound ? 'Found' : 'Lost' ?></span>
            <span class="badge badge-<?= e($item['item_status']) ?>"><?= e(ui_label($item['item_status'])) ?></span>
            <h1><?= e($item['title']) ?></h1>
            <p class="meta"><?= e($item['location_name']) ?> &middot; <?= $isFound ? 'found' : 'lost' ?> on <?= e(ui_date($item['incident_date'])) ?></p>
        </header>

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
            <div class="no-photo">No photo was added to this listing.</div>
        <?php endif; ?>

        <h2>Description</h2>
        <p class="prewrap"><?= e($item['description']) ?></p>

        <h2>Details</h2>
        <div class="panel">
            <dl class="facts">
                <div><dt>Category</dt><dd><?= e($item['category_name']) ?></dd></div>
                <div><dt>Location</dt><dd><?= e($item['location_name']) ?></dd></div>
                <div><dt><?= $isFound ? 'Date found' : 'Date lost' ?></dt><dd><?= e(ui_date($item['incident_date'])) ?></dd></div>
                <div><dt>Posted by</dt><dd><?= e($poster) ?></dd></div>
                <div><dt>Posted on</dt><dd><?= e(ui_date(substr((string) $item['created_at'], 0, 10))) ?></dd></div>
            </dl>
        </div>
    </article>

    <aside class="item-side">
        <?php if ($isOwner): ?>
            <div class="panel panel-action">
                <h2 class="panel-title">This is your listing</h2>
                <p class="status-help"><?= e(ui_item_status_help($item['item_status'], $item['report_type'])) ?></p>
                <div class="action-stack">
                    <?php if ($canEdit): ?>
                        <a class="btn" href="<?= e(url('/items/' . $id . '/edit')) ?>">Edit this listing</a>
                    <?php endif; ?>
                    <a class="btn btn-secondary" href="<?= e(url('/my/items')) ?>">Manage my listings</a>
                </div>
                <?php if ($isFound): ?>
                    <p class="small-links">People who think it is theirs will submit a claim. Custodians review claims, so you do not have to.</p>
                <?php endif; ?>
            </div>

        <?php elseif ($canClaim): ?>
            <div class="panel panel-action">
                <h2 class="panel-title">Is this yours?</h2>
                <p class="status-help">Submit a claim and describe something only the owner would know. A custodian checks it, then arranges pickup.</p>
                <div class="action-stack">
                    <a class="btn btn-lg" href="<?= e(url('/items/' . $id . '/claim')) ?>">Claim this item</a>
                    <?php if ($canMessage): ?>
                        <a class="btn btn-secondary" href="<?= e(url('/messages/' . $id . '/' . (int) $item['reporter_id'])) ?>">Message the poster</a>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($myClaim): ?>
            <div class="panel panel-action">
                <h2 class="panel-title">You claimed this item</h2>
                <p><span class="badge badge-<?= e($myClaim['claim_status']) ?>"><?= e(ui_label($myClaim['claim_status'])) ?></span></p>
                <p class="status-help"><?= e(ui_claim_next($myClaim['claim_status'])) ?></p>
                <div class="action-stack">
                    <a class="btn" href="<?= e(url('/claims/' . (int) $myClaim['claim_id'])) ?>">View my claim</a>
                </div>
            </div>

        <?php else: ?>
            <div class="panel">
                <h2 class="panel-title"><?= $isFound ? 'About this item' : 'Seen this item?' ?></h2>
                <p class="status-help"><?= e(ui_item_status_help($item['item_status'], $item['report_type'])) ?></p>
                <?php if (!$verified): ?>
                    <p class="status-help"><strong>Verify your email</strong> to claim items or send messages.</p>
                <?php elseif ($isFound && !$canReview): ?>
                    <p class="status-help">You cannot claim this item right now.</p>
                <?php endif; ?>
                <?php if ($canMessage): ?>
                    <div class="action-stack">
                        <a class="btn" href="<?= e(url('/messages/' . $id . '/' . (int) $item['reporter_id'])) ?>">Message the poster</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($canReview): ?>
            <div class="panel">
                <h2 class="panel-title">Staff</h2>
                <div class="action-stack">
                    <a class="btn btn-secondary" href="<?= e(url('/review/items/' . $id)) ?>">Review claims for this item</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canReport): ?>
            <p class="small-links">
                Something wrong? <a href="<?= e(url('/items/' . $id . '/report')) ?>">Report this listing</a>
                &middot; <a href="<?= e(url('/users/' . (int) $item['reporter_id'] . '/report')) ?>">Report the poster</a>
            </p>
        <?php endif; ?>
    </aside>
</div>
