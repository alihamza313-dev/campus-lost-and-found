<?php
declare(strict_types=1);

/* ---------- Environment and config ---------- */

function load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $_ENV[trim($key)] = $value;
    }
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

/** config('app.debug') reads config/app.php -> 'debug'. Dots go deeper: config('app.db.host'). */
function config(string $key, mixed $default = null): mixed
{
    static $loaded = [];
    $parts = explode('.', $key);
    $file  = array_shift($parts);

    if (!isset($loaded[$file])) {
        $path = BASE_PATH . "/config/$file.php";
        $loaded[$file] = is_file($path) ? require $path : [];
    }

    $value = $loaded[$file];
    foreach ($parts as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/* ---------- Output and URLs ---------- */

/** Escape for HTML output. Use on EVERY dynamic value in a view. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return config('app.url') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/* ---------- Views ---------- */

/** Renders app/views/{$name}.php inside layout/main.php. */
function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require BASE_PATH . "/app/views/$name.php";
    $content = ob_get_clean();
    require BASE_PATH . '/app/views/layout/main.php';
}

function abort(int $code): never
{
    http_response_code($code);
    $file = BASE_PATH . "/app/views/errors/$code.php";
    if (is_file($file)) {
        view("errors/$code", ['title' => (string) $code]);
    } else {
        echo 'Error ' . $code;
    }
    exit;
}

/* ---------- Flash messages (one-time notices) ---------- */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

/* ---------- Logging ---------- */

function log_error(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents(BASE_PATH . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
}

/* ---------- Request helpers (Module 3) ---------- */

/** Client IP. REMOTE_ADDR only: headers like X-Forwarded-For can be faked by the client. */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Stops the request with 403 if the CSRF token is missing or wrong. Call at the top of every POST handler. */
function require_csrf(): void
{
    if (!csrf_verify()) {
        abort(403);
    }
}

/** Renders an inline error message under a form field, or nothing. */
function field_error(array $errors, string $key): string
{
    if (empty($errors[$key])) {
        return '';
    }
    return '<p class="field-error" id="err-' . e($key) . '">' . e($errors[$key]) . '</p>';
}