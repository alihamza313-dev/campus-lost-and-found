<h1>Messages</h1>
<?php if (!$threads): ?>
    <p class="empty">You have no conversations yet. Open a listing and use the message button to start one.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Listing</th><th scope="col">With</th><th scope="col">Last message</th><th scope="col"></th></tr></thead>
            <tbody>
            <?php foreach ($threads as $t): ?>
                <tr>
                    <td><?= e($t['item_status'] === 'removed' ? 'Removed listing' : $t['title']) ?>
                        <?php if (in_array($t['item_status'], ['recovered', 'closed', 'removed'], true)): ?>
                            <span class="badge badge-<?= e($t['item_status']) ?>"><?= e(ucfirst($t['item_status'])) ?></span>
                        <?php endif; ?></td>
                    <td><?= e(explode(' ', trim((string) $t['other_name']))[0]) ?></td>
                    <td><?= e(substr((string) $t['last_at'], 0, 16)) ?>
                        <?php if ((int) $t['unread'] > 0): ?><span class="count-badge"><?= (int) $t['unread'] ?> new</span><?php endif; ?></td>
                    <td><a class="btn btn-sm" href="<?= e(url('/messages/' . (int) $t['item_id'] . '/' . (int) $t['other_id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>