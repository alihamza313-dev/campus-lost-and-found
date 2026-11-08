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
$mark = '<svg class="brand-mark" width="30" height="30" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
      . '<rect width="32" height="32" rx="7" fill="#0e1a2e"/>'
      . '<circle cx="14" cy="14" r="6" fill="none" stroke="#fff" stroke-width="2.6"/>'
      . '<path d="M18.5 18.5 25 25" stroke="#fbbf24" stroke-width="3" stroke-linecap="round"/></svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d4ed8">
    <meta name="description" content="Report lost items, post found items and get belongings back to the right person on campus.">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230e1a2e'/%3E%3Ccircle cx='14' cy='14' r='6' fill='none' stroke='%23fff' stroke-width='2.6'/%3E%3Cpath d='M18.5 18.5 25 25' stroke='%23fbbf24' stroke-width='3' stroke-linecap='round'/%3E%3C/svg%3E">
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
            <a class="brand" href="<?= e(url($navUser ? '/dashboard' : '/')) ?>">
                <?= $mark ?>
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
                                <small><?= e($navUser['university_email']) ?><br><?= e(ucfirst($navUser['role'])) ?></small></p>
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
            <div class="container notice-inner"><p><strong>Your account is suspended.</strong> You can browse, but you cannot post, claim, message or report.</p></div>
        </div>
    <?php elseif ($navUser && $navUser['verification_status'] === 'pending'): ?>
        <div class="notice-bar" role="status">
            <div class="container notice-inner">
                <p><strong>Check your inbox to verify your email.</strong> Until you do, you can browse but not post, claim or message.</p>
                <form method="post" action="<?= e(url('/verify/resend')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-secondary">Send the link again</button>
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
        <div class="container footer-main">
            <div class="footer-grid">
                <div>
                    <a class="footer-brand" href="<?= e(url($navUser ? '/dashboard' : '/')) ?>">
                        <?= $mark ?>
                        <span><?= e(config('app.name')) ?></span>
                    </a>
                    <p class="footer-about">One place on campus to report lost items, post found items and return them to the right person.</p>
                    <span class="footer-safe">Handover is always in person, with student ID</span>
                </div>
                <div>
                    <h2>Lost and found</h2>
                    <ul>
                        <li><a href="<?= e(url('/items')) ?>">Browse listings</a></li>
                        <li><a href="<?= e(url('/items?type=found')) ?>">Search found items</a></li>
                        <li><a href="<?= e(url('/items/new?type=lost')) ?>">Report a lost item</a></li>
                        <li><a href="<?= e(url('/items/new?type=found')) ?>">Report a found item</a></li>
                    </ul>
                </div>
                <div>
                    <h2>Your account</h2>
                    <ul>
                        <?php if ($navUser): ?>
                            <li><a href="<?= e(url('/dashboard')) ?>">Home</a></li>
                            <li><a href="<?= e(url('/my/items')) ?>">My listings</a></li>
                            <li><a href="<?= e(url('/my/claims')) ?>">My claims</a></li>
                            <li><a href="<?= e(url('/messages')) ?>">Messages</a></li>
                            <li><a href="<?= e(url('/profile')) ?>">Profile and password</a></li>
                        <?php else: ?>
                            <li><a href="<?= e(url('/login')) ?>">Log in</a></li>
                            <li><a href="<?= e(url('/register')) ?>">Create an account</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h2>Help</h2>
                    <ul>
                        <li><a href="<?= e(url('/help')) ?>">How it works</a></li>
                        <li><a href="<?= e(url('/help#faq')) ?>">Common questions</a></li>
                        <li><a href="<?= e(url('/help#staff')) ?>">What custodians do</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bar">
            <div class="container footer-bar-inner">
                <p>&copy; <?= e(date('Y')) ?> <?= e(config('app.name')) ?></p>
                <p>For students and staff with a <?= e(config('app.allowed_email_domain')) ?> email.</p>
            </div>
        </div>
    </footer>
</body>
</html>