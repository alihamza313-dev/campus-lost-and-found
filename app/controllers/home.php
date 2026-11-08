<?php
declare(strict_types=1);

function home_index(): void
{
    // Signed-in people land on their dashboard, guests see the landing page.
    if (current_user() !== null) {
        redirect('/dashboard');
    }
    view('pages/home', ['title' => 'Find what you lost. Return what you found.']);
}

function help_index(): void
{
    view('pages/help', ['title' => 'How it works']);
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
