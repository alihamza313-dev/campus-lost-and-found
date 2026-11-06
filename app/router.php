<?php
declare(strict_types=1);

function dispatch(array $routes): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    // Remove the base folder (/campus-lost-found) taken from APP_URL.
    $base = rtrim((string) parse_url(config('app.url'), PHP_URL_PATH), '/');
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    $path = '/' . trim($path, '/');

    foreach ($routes as [$routeMethod, $pattern, $handler]) {
        if ($routeMethod !== $method) {
            continue;
        }
        $regex = '#^' . preg_replace('#\{\w+\}#', '(\d+)', $pattern) . '$#';
        if (preg_match($regex, $path, $matches)) {
            array_shift($matches);
            if (!function_exists($handler)) {
                throw new RuntimeException("Route handler $handler not found");
            }
            $handler(...array_map('intval', $matches));
            return;
        }
    }

    abort(404);
}