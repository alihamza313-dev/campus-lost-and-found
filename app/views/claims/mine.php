<h1>My claims</h1>
<?php if (!$claims): ?>
    <p class="empty">You have not claimed anything yet. Browse found items and use "Claim this item" on yours.</p>
    <p><a class="btn" href="<?= e(url('/items?type=found')) ?>">Browse found items</a></p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Item</th><th scope="col">Claim status</th><th scope="col">Submitted</th><th scope="col"></th></tr></thead>
            <tbody>
            <?php foreach ($claims as $c): ?>
                <tr>
                    <td><?= e($c['title']) ?></td>
                    <td><span class="badge badge-<?= e($c['claim_status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $c['claim_status']))) ?></span></td>
                    <td><?= e(substr((string) $c['created_at'], 0, 10)) ?></td>
                    <td><a class="btn btn-sm btn-secondary" href="<?= e(url('/claims/' . (int) $c['claim_id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>