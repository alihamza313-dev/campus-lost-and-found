<?php
declare(strict_types=1);

/* ---------- Validation helpers ---------- */

function valid_university_email(string $email): bool
{
    if (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    // Exact domain match: "x@pucit.edu.pk" passes, "x@pucit.edu.pk.evil.com" and "x@evil-pucit.edu.pk" fail.
    $domain = strtolower(substr((string) strrchr($email, '@'), 1));
    return $domain === config('app.allowed_email_domain');
}

/** Returns an error message, or null if the password is acceptable. */
function password_error(string $password): ?string
{
    $min = (int) config('app.password_min_length');
    if (mb_strlen($password) < $min) {
        return "Password must be at least $min characters.";
    }
    if (strlen($password) > 72) {   // bcrypt only uses the first 72 bytes
        return 'Password is too long (maximum 72 bytes).';
    }
    return null;
}

/* ---------- Who is logged in? ---------- */

/**
 * Returns the current user, read FRESH from the database (BR-1), or null.
 * The session stores only user_id, never role or status.
 */
function current_user(): ?array
{
    static $loaded = false;
    static $user   = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    $idleLimit = (int) config('app.session_idle_minutes') * 60;
    if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $idleLimit) {
        logout_user();
        return null;
    }

    $row = user_find_by_id((int) $id);
    if ($row === null || $row['account_status'] === 'deactivated') {
        logout_user();
        return null;
    }

    $_SESSION['last_activity'] = time();
    $user = $row;
    return $user;
}

/** Verified, not deactivated, and not currently suspended: may post, claim, message and report. */
function user_can_act(array $user): bool
{
    return $user['verification_status'] === 'verified'
        && $user['account_status'] !== 'deactivated'
        && !(int) $user['is_suspended'];
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash_set('error', 'Please log in to continue.');
        redirect('/login');
    }
    return $user;
}

/** For actions that need a verified, active account. */
function require_verified(): array
{
    $user = require_login();
    if (!user_can_act($user)) {
        flash_set('error', 'You need a verified email and an active account to do that.');
        redirect('/dashboard');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        abort(403);
    }
    return $user;
}

/* ---------- Session start and end ---------- */

function login_user(int $userId): void
{
    session_regenerate_id(true);          // new session ID after login prevents session fixation
    $_SESSION['user_id']       = $userId;
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);       // fresh CSRF token for the new session
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/* ---------- Email verification tokens ---------- */

/** Creates a token, stores only its SHA-256 hash, and returns the raw token (for the email). */
function create_verification_token(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hours = (int) config('app.verify_token_hours');

    $stmt = db()->prepare(
        "INSERT INTO email_verifications (user_id, token_hash, purpose, expires_at)
         VALUES (?, ?, 'verify_email', DATE_ADD(NOW(), INTERVAL $hours HOUR))"
    );
    $stmt->execute([$userId, hash('sha256', $token)]);
    return $token;
}

function send_verification_email(string $toEmail, string $fullName, string $token): void
{
    $link  = url('/verify') . '?token=' . $token;
    $hours = (int) config('app.verify_token_hours');

    send_mail(
        $toEmail,
        'Verify your email: ' . config('app.name'),
        "Hello $fullName,\n\nPlease verify your email by opening this link (valid for $hours hours, one use only):\n\n$link\n\nIf you did not create this account, ignore this message."
    );
}