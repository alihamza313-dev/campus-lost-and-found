<?php
$flashes = flash_get();
$navUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        <div class="container header-inner">
            <a class="brand" href="<?= e(url('/')) ?>"><?= e(config('app.name')) ?></a>
            <nav aria-label="Main navigation">
                <a href="<?= e(url('/')) ?>">Home</a>
                <?php if ($navUser): ?>
                    <a href="<?= e(url('/items')) ?>">Browse</a>
                    <a href="<?= e(url('/dashboard')) ?>">Dashboard</a>
                    <a href="<?= e(url('/items/new')) ?>">Report item</a>
                    <a href="<?= e(url('/my/items')) ?>">My listings</a>
                    <a href="<?= e(url('/profile')) ?>">Profile</a>
                    <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="link-button">Log out</button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(url('/login')) ?>">Log in</a>
                    <a href="<?= e(url('/register')) ?>">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main id="main" class="container">
        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <small>&copy; <?= e(date('Y')) ?> <?= e(config('app.name')) ?>. Web Technologies course project.</small>
        </div>
    </footer>
</body>
</html>