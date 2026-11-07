<?php
declare(strict_types=1);

/** Reads a GET field as a trimmed string. Arrays become '' instead of causing errors. */
function get_str(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Builds /path?a=1&b=2 and drops empty values, so URLs stay short. */
function page_url(string $path, array $params): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return url($path) . ($params ? '?' . http_build_query($params) : '');
}