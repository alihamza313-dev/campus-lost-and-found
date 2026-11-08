<?php
$canAct  = user_can_act($user);
$isStaff = in_array($user['role'], ['custodian', 'admin'], true);
$active  = array_filter($claims, static fn ($c) => in_array($c['claim_status'], ['pending', 'under_review', 'approved'], true));
$todo = [];
if ($user['verification_status'] === 'pending') {
    $todo[] = ['warn', 'Verify your email so you can post, claim and message.', null, null];
}
foreach ($active as $c) {
    $text = $c['claim_status'] === 'approved'
        ? 'Your claim on "' . $c['title'] . '" was approved. Bring your student ID to collect it.'
        : 'Your claim on "' . $c['title'] . '" is ' . str_replace('_', ' ', $c['claim_status']) . '.';
    $todo[] = [$c['claim_status'] === 'approved' ? 'ok' : 'warn', $text, '/claims/' . (int) $c['claim_id'], 'View claim'];
}
if ($unreadMsgs > 0) {
    $todo[] = ['warn', $unreadMsgs . ' unread message' . ($unreadMsgs === 1 ? '' : 's') . '.', '/messages', 'Read'];
}
if ($unreadNotes > 0) {
    $todo[] = ['warn', $unreadNotes . ' unread alert' . ($unreadNotes === 1 ? '' : 's') . '.', '/notifications', 'Read'];
}
?>
<div class="page-head">
    <div>
        <h1>Hello, <?= e(explode(' ', trim((string) $user['full_name']))[0]) ?></h1>
        <p class="muted">Lost something? Search first. It may already be here.</p>
    </div>
</div>

<form class="dash-search" method="get" action="<?= e(url('/items')) ?>" role="search">
    <input type="hidden" name="type" value="found">
    <div class="searchbar">
        <label for="dash-q" class="visually-hidden">Search found items</label>
        <input id="dash-q" name="q" type="search" maxlength="100" placeholder="Search found items, for example &ldquo;black wallet&rdquo;">
        <button type="submit" class="btn">Search</button>
    </div>
    <p class="quick">Or browse <a href="<?= e(url('/items?type=found')) ?>">all found items</a> or <a href="<?= e(url('/items?type=lost')) ?>">items people are looking for</a>.</p>
</form>

<div class="action-tiles">
    <?php if ($canAct): ?>
        <a class="tile tile-lost" href="<?= e(url('/items/new?type=lost')) ?>"><strong>I lost something</strong><span>Tell us what it is so finders can reach you.</span></a>
        <a class="tile tile-found" href="<?= e(url('/items/new?type=found')) ?>"><strong>I found something</strong><span>Post it so the owner can claim it.</span></a>
    <?php else: ?>
        <span class="tile"><strong>Posting is locked</strong><span>Verify your email, or contact an administrator.</span></span>
        <span class="tile"><strong>Claiming is locked</strong><span>It needs the same verification as posting.</span></span>
    <?php endif; ?>
</div>

<div class="dash-grid">
    <div>
        <h2 class="first-h2">Needs your attention</h2>
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
            <ul class="feed">
                <?php foreach ($latest as $it): ?>
                    <li>
                        <span class="badge badge-<?= e($it['report_type']) ?>"><?= e(ucfirst($it['report_type'])) ?></span>
                        <a href="<?= e(url('/items/' . (int) $it['item_id'])) ?>"><?= e($it['title']) ?></a>
                        <span class="muted"><?= e($it['location_name']) ?>, <?= e(ui_date($it['incident_date'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><a href="<?= e(url('/items')) ?>">See all listings</a></p>
        <?php endif; ?>
    </div>

    <aside>
        <div class="panel">
            <h2 class="panel-title">Your activity</h2>
            <div class="mini-stats">
                <div><strong><?= (int) $stats['total'] ?></strong><span>Listings</span></div>
                <div><strong><?= (int) $stats['open'] ?></strong><span>Open</span></div>
                <div><strong><?= count($active) ?></strong><span>Claims</span></div>
            </div>
            <p class="small-links"><a href="<?= e(url('/my/items')) ?>">My listings</a> and <a href="<?= e(url('/my/claims')) ?>">my claims</a></p>
        </div>

        <?php if ($isStaff): ?>
            <div class="panel panel-action">
                <h2 class="panel-title">Staff tools</h2>
                <div class="action-stack">
                    <a class="btn" href="<?= e(url('/review')) ?>">Review claims</a>
                    <a class="btn btn-secondary" href="<?= e(url('/custodian')) ?>">Handovers</a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a class="btn btn-secondary" href="<?= e(url('/admin')) ?>">Admin dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h2 class="panel-title">Your account</h2>
            <dl class="facts">
                <div><dt>Email</dt><dd><?= e($user['university_email']) ?></dd></div>
                <div><dt>Role</dt><dd><?= e(ucfirst($user['role'])) ?></dd></div>
                <div><dt>Status</dt><dd><?= (int) $user['is_suspended'] ? 'Suspended' : e(ucfirst($user['verification_status'])) ?></dd></div>
            </dl>
            <p class="small-links"><a href="<?= e(url('/profile')) ?>">Edit profile or password</a></p>
        </div>
    </aside>
</div>