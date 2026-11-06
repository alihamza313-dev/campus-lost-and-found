<?php
$flashes = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' | ' : '') ?><?= e(config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="<?= e(url('/')) ?>"><?= e(config('app.name')) ?></a>
            <nav aria-label="Main navigation">
                <a href="<?= e(url('/')) ?>">Home</a>
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