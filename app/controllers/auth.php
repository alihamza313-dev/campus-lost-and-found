<?php
declare(strict_types=1);

/* ---------- Register ---------- */

function register_form(): void
{
    if (current_user()) {
        redirect('/dashboard');
    }
    view('auth/register', ['title' => 'Register', 'old' => [], 'errors' => []]);
}

function register_submit(): void
{
    if (current_user()) {
        redirect('/dashboard');
    }
    require_csrf();

    $old = [
        'full_name'  => post_str('full_name'),
        'email'      => strtolower(post_str('email')),
        'student_id' => post_str('student_id'),
        'phone'      => post_str('phone'),
    ];
    $password = post_raw('password');
    $confirm  = post_raw('password_confirm');

    $errors = [];
    if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 100) {
        $errors['full_name'] = 'Enter your full name (2 to 100 characters).';
    }
    if (!valid_university_email($old['email'])) {
        $errors['email'] = 'Use your university email ending in @' . config('app.allowed_email_domain') . '.';
    }
    if ($old['student_id'] !== '' && !preg_match('/^[A-Za-z0-9\-]{3,20}$/', $old['student_id'])) {
        $errors['student_id'] = 'Roll number may contain letters, digits and dashes only (3 to 20 characters).';
    }
    if ($old['phone'] !== '' && !preg_match('/^\+?[0-9 \-]{7,20}$/', $old['phone'])) {
        $errors['phone'] = 'Enter a valid phone number or leave it empty.';
    }
    if ($err = password_error($password)) {
        $errors['password'] = $err;
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    // Uniqueness checks (the UNIQUE keys in MySQL are the final guard, see the catch below)
    if (!isset($errors['email']) && user_find_by_email($old['email'])) {
        $errors['email'] = 'This email is already registered.';
    }
    if (!isset($errors['student_id']) && $old['student_id'] !== '' && user_student_id_exists($old['student_id'])) {
        $errors['student_id'] = 'This roll number is already registered.';
    }

    if (!$errors && !rate_allow('register_ip', 'register:ip:' . client_ip())) {
        $errors['email'] = 'Too many registrations from your network. Please try again in an hour.';
        http_response_code(429);
    }

    if ($errors) {
        if (http_response_code() === 200) {
            http_response_code(422);
        }
        view('auth/register', ['title' => 'Register', 'old' => $old, 'errors' => $errors]);
        return;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $userId = user_create(
            $old['full_name'],
            $old['email'],
            password_hash($password, PASSWORD_DEFAULT),
            $old['student_id'] !== '' ? $old['student_id'] : null,
            $old['phone'] !== '' ? $old['phone'] : null
        );
        $token = create_verification_token($userId);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {   // two people registered the same email at the same instant
            http_response_code(422);
            view('auth/register', ['title' => 'Register', 'old' => $old,
                'errors' => ['email' => 'This email or roll number is already registered.']]);
            return;
        }
        throw $e;
    }

    rate_hit('verification_email', 'verify:user:' . $userId);
    send_verification_email($old['email'], $old['full_name'], $token);

    $msg = 'Account created. We sent a verification link to ' . $old['email'] . '.';
    if (config('app.debug')) {
        $msg .= ' (Development: the email is in storage/logs/mail.log)';
    }
    flash_set('success', $msg);
    redirect('/login');
}

/* ---------- Login / logout ---------- */

function login_form(): void
{
    if (current_user()) {
        redirect('/dashboard');
    }
    view('auth/login', ['title' => 'Log in', 'old' => [], 'error' => null]);
}

function login_submit(): void
{
    if (current_user()) {
        redirect('/dashboard');
    }
    require_csrf();

    $email    = strtolower(post_str('email'));
    $password = post_raw('password');

    // Two counters: one per email (works even for emails that do not exist) and one per IP (T-15).
    $emailKey = 'login:email:' . hash('sha256', $email);
    $ipKey    = 'login:ip:' . client_ip();

    if (rate_limited('login_fail', $emailKey) || rate_limited('login_fail', $ipKey)) {
        http_response_code(429);
        view('auth/login', ['title' => 'Log in', 'old' => ['email' => $email],
            'error' => 'Too many failed attempts. Please wait 15 minutes and try again.']);
        return;
    }

    $user = user_find_by_email($email);

    // Always run password_verify, even for unknown emails, so response time does not reveal which emails exist.
    $hash  = $user['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
    $valid = password_verify($password, $hash);

    if ($user === null || !$valid || $user['account_status'] === 'deactivated') {
        rate_hit('login_fail', $emailKey);
        rate_hit('login_fail', $ipKey);
        http_response_code(401);
        view('auth/login', ['title' => 'Log in', 'old' => ['email' => $email],
            'error' => 'Invalid email or password.']);
        return;
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
    }

    login_user((int) $user['user_id']);
    redirect('/dashboard');
}

function logout_submit(): void
{
    require_csrf();
    logout_user();
    flash_set('success', 'You have been logged out.');
    redirect('/');
}

/* ---------- Email verification ---------- */

function verify_email(): void
{
    $token = get_str('token');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        flash_set('error', 'This verification link is invalid.');
        redirect('/login');
    }
    $hash = hash('sha256', $token);

    $pdo = db();
    $pdo->beginTransaction();

    // One guarded UPDATE does all three checks at once: exists, unused, not expired.
    // Zero affected rows means the link is wrong, already used or expired (single-use token).
    $stmt = $pdo->prepare(
        "UPDATE email_verifications SET used_at = NOW()
         WHERE token_hash = ? AND purpose = 'verify_email' AND used_at IS NULL AND expires_at > NOW()"
    );
    $stmt->execute([$hash]);

    if ($stmt->rowCount() !== 1) {
        $pdo->rollBack();
        flash_set('error', 'This verification link is invalid, expired or already used. Log in to request a new one.');
        redirect('/login');
    }

    $stmt = $pdo->prepare('SELECT user_id FROM email_verifications WHERE token_hash = ?');
    $stmt->execute([$hash]);
    $userId = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE users SET verification_status = 'verified' WHERE user_id = ? AND verification_status = 'pending'");
    $stmt->execute([$userId]);
    $pdo->commit();

    flash_set('success', 'Your email is verified. You can now post, claim, message and report.');
    redirect(current_user() ? '/dashboard' : '/login');
}