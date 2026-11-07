<div class="page-head">
    <h1>Notifications</h1>
    <?php if ($notes): ?>
        <form method="post" action="<?= e(url('/notifications/read-all')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary">Mark all as read</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$notes): ?>
    <p class="empty">You have no notifications yet.</p>
<?php else: ?>
    <ul class="notes">
        <?php foreach ($notes as $n):
            $link = notification_link($n, $uid);
            $unread = !(int) $n['is_read'];
        ?>
            <li class="note <?= $unread ? 'note-unread' : '' ?>">
                <div>
                    <p><?= $unread ? '<strong>' : '' ?><?= e($n['notification_text']) ?><?= $unread ? '</strong>' : '' ?></p>
                    <p class="muted"><?= e(substr((string) $n['created_at'], 0, 16)) ?>
                        <?php if ($link): ?>&middot; <a href="<?= e(url($link)) ?>">Open</a><?php endif; ?></p>
                </div>
                <?php if ($unread): ?>
                    <form method="post" action="<?= e(url('/notifications/' . (int) $n['notification_id'] . '/read')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-secondary">Mark read</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>