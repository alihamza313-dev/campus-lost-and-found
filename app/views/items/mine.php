<div class="page-head">
    <h1>My listings</h1>
    <div>
        <a class="btn" href="<?= e(url('/items/new?type=lost')) ?>">I lost something</a>
        <a class="btn btn-secondary" href="<?= e(url('/items/new?type=found')) ?>">I found something</a>
    </div>
</div>

<?php if (!$items): ?>
    <div class="empty">
        <p><strong>You have not posted anything yet.</strong></p>
        <p>Use the buttons above to report a lost or found item.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th scope="col">Item</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col">Photos</th><th scope="col">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><a href="<?= e(url('/items/' . (int) $it['item_id'])) ?>"><strong><?= e($it['title']) ?></strong></a><br><span class="muted"><?= e($it['category_name']) ?> &middot; <?= e($it['location_name']) ?></span></td>
                    <td><span class="badge badge-<?= e($it['report_type']) ?>"><?= e(ucfirst($it['report_type'])) ?></span></td>
                    <td><span class="badge badge-<?= e($it['item_status']) ?>"><?= e(ui_label($it['item_status'])) ?></span></td>
                    <td><?= e(ui_date($it['incident_date'])) ?></td>
                    <td><?= (int) $it['image_count'] ?></td>
                    <td class="actions">
                        <?php if ($it['item_status'] === 'open'): ?>
                            <a class="btn btn-sm btn-secondary" href="<?= e(url('/items/' . (int) $it['item_id'] . '/edit')) ?>">Edit</a>
                            <?php if ($it['report_type'] === 'lost'): ?>
                                <form method="post" action="<?= e(url('/items/' . (int) $it['item_id'] . '/recovered')) ?>" class="inline-form" data-confirm="Mark this item as recovered? This cannot be undone.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-secondary">I got it back</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/items/' . (int) $it['item_id'] . '/close')) ?>" class="inline-form" data-confirm="Close this listing? This cannot be undone.">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-danger">Close</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">No actions</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
