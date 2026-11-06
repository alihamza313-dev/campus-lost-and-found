<?php
declare(strict_types=1);

function home_index(): void
{
    view('pages/home', ['title' => 'Home']);
}

function health_check(): void
{
    if (!config('app.debug')) {
        abort(404);
    }

    $version = db()->query('SELECT VERSION()')->fetchColumn();
    $tables  = (int) db()->query(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
    )->fetchColumn();

    view('pages/health', [
        'title'   => 'Health check',
        'version' => $version,
        'tables'  => $tables,
        'php'     => PHP_VERSION,
    ]);
}