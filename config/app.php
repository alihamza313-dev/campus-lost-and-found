<?php
declare(strict_types=1);

return [
    'name'                 => env('APP_NAME', 'Campus Lost & Found'),
    'env'                  => env('APP_ENV', 'production'),
    'debug'                => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url'                  => rtrim((string) env('APP_URL', 'http://clf.local'), '/'),
    'allowed_email_domain' => strtolower((string) env('ALLOWED_EMAIL_DOMAIN', 'pucit.edu.pk')),

    'session_idle_minutes' => 30,   // logged out after this much inactivity
    'verify_token_hours'   => 24,   // email verification link lifetime
    'password_min_length'  => 8,

    'db' => [
        'host'    => env('DB_HOST', 'localhost'),
        'name'    => env('DB_NAME', 'clf_db'),
        'user'    => env('DB_USER', ''),
        'pass'    => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
];