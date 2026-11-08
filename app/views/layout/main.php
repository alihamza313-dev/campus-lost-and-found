<?php
$flashes = flash_get();
$navUser = current_user();
$unreadNotes = $navUser ? notification_unread_count((int) $navUser['user_id']) : 0;
$unreadMsgs  = $navUser ? message_unread_count((int) $navUser['user_id']) : 0;
$isStaff = $navUser && in_array($navUser['role'], ['custodian', 'admin'], true);
$isAdmin = $navUser && $navUser['role'] === 'admin';

$here = rtrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$active = static function (string $path, bool $prefix = false) use ($here): string {
    $target = rtrim((string) parse_url(url($path), PHP_URL_PATH), '/');
    $hit = $prefix ? ($here === $target || str_starts_with($here, $target . '/')) : ($here === $target);
    return $hit ? ' aria-current="page"' : '';
};
$initial = $navUser ? mb_strtoupper(mb_substr(trim((string) $navUser['full_name']), 0, 1)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f4d45">
    <title><?= e(($title ?? '') !== '' ? $title . ' | ' : '') ?><?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <?php foreach (($scripts ?? []) as $s): ?>
        <script src="<?= e(asset($s)) ?>" defer></script>
    <?php endforeach; ?>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header">
        <div class="container header-bar">
            <a class="brand" href="<?= e(url('/')) ?>">
                <svg class="brand-mark" width="30" height="30" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                    <rect width="32" height="32" rx="8" fill="#1f4d45"/>
                    <circle cx="14" cy="14" r="6" fill="none" stroke="#fff" stroke-width="2.4"/>
                    <path d="M18.5 18.5 25 25" stroke="#f0a36b" stroke-width="2.8" stroke-linecap="round"/>
                </svg>
                <span><?= e(config('app.name')) ?></span>
            </a>

            <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav">Menu</button>

            <nav id="site-nav" class="site-nav" aria-label="Main navigation">
                <?php if ($navUser): ?>
                    <a class="nav-link"<?= $active('/dashboard') ?> href="<?= e(url('/dashboard')) ?>">Home</a>
                    <a class="nav-link"<?= $active('/items', true) ?> href="<?= e(url('/items')) ?>">Browse</a>
                    <a class="nav-link"<?= $active('/my/items') ?> href="<?= e(url('/my/items')) ?>">My listings</a>
                    <a class="nav-link"<?= $active('/my/claims') ?> href="<?= e(url('/my/claims')) ?>">My claims</a>
                    <a class="nav-link"<?= $active('/messages', true) ?> href="<?= e(url('/messages')) ?>">Messages<?php if ($unreadMsgs > 0): ?> <span class="count-badge" aria-label="<?= (int) $unreadMsgs ?> unread"><?= (int) $unreadMsgs ?></span><?php endif; ?></a>
                    <a class="nav-link"<?= $active('/notifications') ?> href="<?= e(url('/notifications')) ?>">Alerts<?php if ($unreadNotes > 0): ?> <span class="count-badge" aria-label="<?= (int) $unreadNotes ?> unread"><?= (int) $unreadNotes ?></span><?php endif; ?></a>
                    <a class="btn btn-sm nav-cta" href="<?= e(url('/items/new')) ?>">Report an item</a>

                    <details class="user-menu">
                        <summary aria-label="Account menu">
                            <span class="avatar" aria-hidden="true"><?= e($initial) ?></span>
                        </summary>
                        <div class="user-menu-panel">
                            <p class="user-menu-id"><strong><?= e($navUser['full_name']) ?></strong><br>
                                <small><?= e($navUser['university_email']) ?> &middot; <?= e(ucfirst($navUser['role'])) ?></small></p>
                            <a class="menu-item" href="<?= e(url('/profile')) ?>">Profile and password</a>
                            <a class="menu-item" href="<?= e(url('/help')) ?>">Help</a>
                            <form method="post" action="<?= e(url('/logout')) ?>" class="menu-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="menu-item">Log out</button>
                            </form>
                        </div>
                    </details>
                <?php else: ?>
                    <a class="nav-link"<?= $active('/help') ?> href="<?= e(url('/help')) ?>">How it works</a>
                    <a class="nav-link"<?= $active('/login') ?> href="<?= e(url('/login')) ?>">Log in</a>
                    <a class="btn btn-sm nav-cta" href="<?= e(url('/register')) ?>">Create account</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <?php if ($isStaff): ?>
        <div class="staffbar">
            <div class="container staffbar-inner">
                <span class="staffbar-label">Staff</span>
                <a<?= $active('/review', true) ?> href="<?= e(url('/review')) ?>">Review claims</a>
                <a<?= $active('/custodian', true) ?> href="<?= e(url('/custodian')) ?>">Handovers</a>
                <?php if ($isAdmin): ?>
                    <a<?= $active('/admin', true) ?> href="<?= e(url('/admin')) ?>">Admin</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($navUser && (int) $navUser['is_suspended']): ?>
        <div class="notice-bar notice-bar-bad" role="status">
            <div class="container notice-inner"><p><strong>Account suspended.</strong> You can browse, but you cannot post, claim, message or report.</p></div>
        </div>
    <?php elseif ($navUser && $navUser['verification_status'] === 'pending'): ?>
        <div class="notice-bar" role="status">
            <div class="container notice-inner">
                <p><strong>Verify your email to get started.</strong> Until then you can browse but not post, claim or message.</p>
                <form method="post" action="<?= e(url('/verify/resend')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-secondary">Resend link</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <main id="main" class="container">
        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <p><strong><?= e(config('app.name')) ?></strong></p>
                    <p class="muted">One trusted place to report lost items, post found items and return them to the right person.</p>
                </div>
                <div>
                    <h2>Use</h2>
                    <ul>
                        <li><a href="<?= e(url('/items')) ?>">Browse listings</a></li>
                        <li><a href="<?= e(url('/items/new?type=lost')) ?>">Report a lost item</a></li>
                        <li><a href="<?= e(url('/items/new?type=found')) ?>">Report a found item</a></li>
                    </ul>
                </div>
                <div>
                    <h2>Help</h2>
                    <ul>
                        <li><a href="<?= e(url('/help')) ?>">How it works</a></li>
                        <li><a href="<?= e(url('/help#faq')) ?>">Questions</a></li>
                    </ul>
                </div>
            </div>
            <p class="footer-note">&copy; <?= e(date('Y')) ?> <?= e(config('app.name')) ?>. Web Technologies course project.</p>
        </div>
    </footer>
</body>
</html>
