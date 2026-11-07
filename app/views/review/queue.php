<h1>Review claims</h1>
<?php if (!$items): ?>
    <p class="empty">No claims are waiting for review.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Item</th><th scope="col">Active claims</th><th scope="col">Waiting since</th><th scope="col">Flags</th><th scope="col"></th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= e($it['title']) ?></td>
                    <td><?= (int) $it['active_claims'] ?></td>
                    <td><?= e(substr((string) $it['oldest'], 0, 16)) ?></td>
                    <td><?= (int) $it['is_high_value'] ? 'High value' : '' ?><?= (int) $it['active_claims'] > 1 ? ' Multiple claims' : '' ?></td>
                    <td><a class="btn btn-sm" href="<?= e(url('/review/items/' . (int) $it['item_id'])) ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>