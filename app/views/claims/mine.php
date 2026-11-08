<div class="page-head">
    <h1>My claims</h1>
    <a class="btn btn-secondary" href="<?= e(url('/items?type=found')) ?>">Browse found items</a>
</div>

<?php if (!$claims): ?>
    <div class="empty">
        <p><strong>You have not claimed anything yet.</strong></p>
        <p>Open a found item that looks like yours and choose &ldquo;Claim this item&rdquo;.</p>
        <p><a class="btn" href="<?= e(url('/items?type=found')) ?>">Search found items</a></p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Item</th><th scope="col">Status</th><th scope="col">What happens next</th><th scope="col">Submitted</th><th scope="col"><span class="visually-hidden">Open</span></th></tr></thead>
            <tbody>
            <?php foreach ($claims as $c): ?>
                <tr>
                    <td><strong><?= e($c['title']) ?></strong></td>
                    <td><span class="badge badge-<?= e($c['claim_status']) ?>"><?= e(ui_label($c['claim_status'])) ?></span></td>
                    <td><?= e(ui_claim_next($c['claim_status'])) ?></td>
                    <td><?= e(ui_date(substr((string) $c['created_at'], 0, 10))) ?></td>
                    <td><a class="btn btn-sm btn-secondary" href="<?= e(url('/claims/' . (int) $c['claim_id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
