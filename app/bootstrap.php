<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/helpers.php';
load_env(BASE_PATH . '/.env');
require BASE_PATH . '/app/database.php';
require BASE_PATH . '/app/router.php';

/* ---------- Error handling ---------- */
// Never print PHP errors to the browser. We log them ourselves.
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Turn warnings/notices into exceptions so nothing fails silently.
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $ex): void {
    log_error(get_class($ex) . ': ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    if (config('app.debug')) {
        echo '<pre>' . e(get_class($ex) . ': ' . $ex->getMessage()) . '</pre>';
    } else {
        echo '<h1>Something went wrong</h1><p>Please try again later.</p>';
    }
});

/* ---------- Basic security headers ---------- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

/* ---------- Session ---------- */
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

/* ---------- Load all controllers ---------- */
foreach (glob(BASE_PATH . '/app/controllers/*.php') as $controllerFile) {
    require $controllerFile;
}