<?php
declare(strict_types=1);

/** Reads a POST field as a trimmed string. Arrays (like title[]=x) become '' instead of causing errors. */
function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Today's date (Y-m-d) in the app's timezone, so "future date" checks match what a student sees. */
function today_local(): string
{
    $tz = new DateTimeZone((string) config('app.timezone', 'Asia/Karachi'));
    return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
}

/* ---------- Single-use form tokens (BR-13, T-17) ---------- */

/** Creates a one-time token and remembers it in the session. */
function form_token_issue(): string
{
    $tokens = $_SESSION['form_tokens'] ?? [];
    $token  = bin2hex(random_bytes(16));
    $tokens[$token] = time();
    if (count($tokens) > 20) {                       // keep the session small
        $tokens = array_slice($tokens, -20, null, true);
    }
    $_SESSION['form_tokens'] = $tokens;
    return $token;
}

function form_token_field(): string
{
    return '<input type="hidden" name="form_token" value="' . e(form_token_issue()) . '">';
}

/** True exactly once per token. The token is deleted whether or not it was valid. */
function form_token_consume(): bool
{
    $sent = $_POST['form_token'] ?? '';
    if (!is_string($sent) || !isset($_SESSION['form_tokens'][$sent])) {
        return false;
    }
    $age = time() - (int) $_SESSION['form_tokens'][$sent];
    unset($_SESSION['form_tokens'][$sent]);
    return $age <= 7200;                              // a form is valid for 2 hours
}

/** Reads a POST field as a string WITHOUT trimming (for passwords). Arrays become ''. */
function post_raw(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? $v : '';
}