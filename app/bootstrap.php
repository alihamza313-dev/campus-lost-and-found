<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/helpers.php';
load_env(BASE_PATH . '/.env');
require BASE_PATH . '/app/database.php';
require BASE_PATH . '/app/router.php';

/* ---------- Error handling ---------- */
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $ex): void {
    log_error(get_class($ex) . ': ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $ex->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    if (config('app.debug')) {
        echo '<pre>' . e(get_class($ex) . ': ' . $ex->getMessage()) . '</pre>';
    } else {
        echo '<h1>Something went wrong</h1><p>Please try again later.</p>';
    }
});

/* ---------- Load services, models, controllers ---------- */
foreach (['services', 'models', 'controllers'] as $dir) {
    foreach (glob(BASE_PATH . "/app/$dir/*.php") as $file) {
        require $file;
    }
}

/* ---------- Web-only setup: headers and session ---------- */
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('clf_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}