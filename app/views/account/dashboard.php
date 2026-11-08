<?php
$canAct = user_can_act($user);
$active = array_filter($claims, static fn ($c) => in_array($c['claim_status'], ['pending', 'under_review', 'approved'], true));
$todo = [];
if ($user['verification_status'] === 'pending') {
    $todo[] = ['warn', 'Verify your email to post, claim and message.', null, null];
}
foreach ($active as $c) {
    $text = $c['claim_status'] === 'approved'
        ? 'Your claim on "' . $c['title'] . '" was approved. Bring your student ID for the handover.'
        : 'Your claim on "' . $c['title'] . '" is ' . str_replace('_', ' ', $c['claim_status']) . '.';
    $todo[] = [$c['claim_status'] === 'approved' ? 'ok' : 'warn', $text, '/claims/' . (int) $c['claim_id'], 'View'];
}
if ($unreadMsgs > 0) {
    $todo[] = ['warn', $unreadMsgs . ' unread message' . ($unreadMsgs === 1 ? '' : 's') . '.', '/messages', 'Open'];
}
if ($unreadNotes > 0) {
    $todo[] = ['warn', $unreadNotes . ' unread alert' . ($unreadNotes === 1 ? '' : 's') . '.', '/notifications', 'Open'];
}
?>
<div class="page-head">
    <div>
        <h1>Hello, <?= e(explode(' ', trim((string) $user['full_name']))[0]) ?></h1>
        <p class="muted">What would you like to do?</p>
    </div>
</div>

<div class="action-tiles">
    <?php if ($canAct): ?>
        <a class="tile tile-lost" href="<?= e(url('/items/new?type=lost')) ?>"><strong>I lost something</strong><span>Report it so finders can reach you</span></a>
        <a class="tile tile-found" href="<?= e(url('/items/new?type=found')) ?>"><strong>I found something</strong><span>Post it so the owner can claim it</span></a>
    <?php else: ?>
        <span class="tile"><strong>Posting is locked</strong><span>Verify your email or contact an administrator</span></span>
        <span class="tile"><strong>Claiming is locked</strong><span>Same reason as posting</span></span>
    <?php endif; ?>
    <a class="tile tile-primary" href="<?= e(url('/items')) ?>"><strong>Search listings</strong><span>Check if your item was found</span></a>
</div>

<div class="dash-grid">
    <div>
        <h2>Needs your attention</h2>
        <?php if (!$todo): ?>
            <p class="caught-up">You are all caught up. Nothing needs action right now.</p>
        <?php else: ?>
            <ul class="todo">
                <?php foreach ($todo as [$kind, $text, $href, $label]): ?>
                    <li class="todo-<?= e($kind) ?>">
                        <p><?= e($text) ?></p>
                        <?php if ($href): ?><a class="btn btn-sm btn-secondary" href="<?= e(url($href)) ?>"><?= e($label) ?></a><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h2>Latest on campus</h2>
        <?php if (!$latest): ?>
            <p class="empty">Nothing has been posted yet. Be the first to report an item.</p>
        <?php else: ?>
            <ul class="mini-list">
                <?php foreach ($latest as $it): ?>
                    <li>
                        <span class="badge badge-<?= e($it['report_type']) ?>"><?= e(ucfirst($it['report_type'])) ?></span>
                        <a href="<?= e(url('/items/' . (int) $it['item_id'])) ?>"><?= e($it['title']) ?></a>
                        <span class="muted">&middot; <?= e($it['location_name']) ?> &middot; <?= e($it['incident_date']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><a href="<?= e(url('/items')) ?>">See all listings &raquo;</a></p>
        <?php endif; ?>
    </div>

    <aside>
        <div class="panel">
            <h2 class="panel-title">Your activity</h2>
            <dl class="facts">
                <div><dt>Listings posted</dt><dd><?= (int) $stats['total'] ?></dd></div>
                <div><dt>Still open</dt><dd><?= (int) $stats['open'] ?></dd></div>
                <div><dt>Active claims</dt><dd><?= count($active) ?></dd></div>
            </dl>
            <p class="small-links"><a href="<?= e(url('/my/items')) ?>">My listings</a> &middot; <a href="<?= e(url('/my/claims')) ?>">My claims</a></p>
        </div>
        <div class="panel">
            <h2 class="panel-title">Account</h2>
            <dl class="facts">
                <div><dt>Email</dt><dd><?= e($user['university_email']) ?></dd></div>
                <div><dt>Role</dt><dd><?= e(ucfirst($user['role'])) ?></dd></div>
                <div><dt>Status</dt><dd><?= (int) $user['is_suspended'] ? 'Suspended' : e(ucfirst($user['verification_status'])) ?></dd></div>
            </dl>
            <p class="small-links"><a href="<?= e(url('/profile')) ?>">Edit profile or password</a></p>
        </div>
    </aside>
</div>
