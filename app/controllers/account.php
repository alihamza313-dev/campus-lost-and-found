<?php
declare(strict_types=1);

function dashboard_index(): void
{
    $user = require_login();
    view('account/dashboard', ['title' => 'Dashboard', 'user' => $user]);
}

function verification_resend(): void
{
    $user = require_login();
    require_csrf();

    if ($user['verification_status'] === 'verified') {
        flash_set('success', 'Your email is already verified.');
        redirect('/dashboard');
    }
    if (!rate_allow('verification_email', 'verify:user:' . $user['user_id'])) {
        flash_set('error', 'Too many verification emails requested. Please try again in an hour.');
        redirect('/dashboard');
    }

    $token = create_verification_token((int) $user['user_id']);
    send_verification_email($user['university_email'], $user['full_name'], $token);

    $msg = 'A new verification link was sent to ' . $user['university_email'] . '.';
    if (config('app.debug')) {
        $msg .= ' (Development: see storage/logs/mail.log)';
    }
    flash_set('success', $msg);
    redirect('/dashboard');
}

function profile_form(): void
{
    $user = require_login();
    view('account/profile', ['title' => 'Profile', 'user' => $user, 'errors' => [], 'old' => []]);
}

function profile_update(): void
{
    $user = require_login();
    require_csrf();

    $old = [
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'phone'     => trim((string) ($_POST['phone'] ?? '')),
    ];

    $errors = [];
    if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 100) {
        $errors['full_name'] = 'Enter your full name (2 to 100 characters).';
    }
    if ($old['phone'] !== '' && !preg_match('/^\+?[0-9 \-]{7,20}$/', $old['phone'])) {
        $errors['phone'] = 'Enter a valid phone number or leave it empty.';
    }

    if ($errors) {
        http_response_code(422);
        view('account/profile', ['title' => 'Profile', 'user' => $user, 'errors' => $errors, 'old' => $old]);
        return;
    }

    $stmt = db()->prepare('UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?');
    $stmt->execute([$old['full_name'], $old['phone'] !== '' ? $old['phone'] : null, $user['user_id']]);

    flash_set('success', 'Profile updated.');
    redirect('/profile');
}

function password_update(): void
{
    $user = require_login();
    require_csrf();

    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $errors = [];
    if (!password_verify($current, user_password_hash((int) $user['user_id']))) {
        $errors['current_password'] = 'Current password is incorrect.';
    }
    if ($err = password_error($new)) {
        $errors['new_password'] = $err;
    } elseif ($new !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if ($errors) {
        http_response_code(422);
        view('account/profile', ['title' => 'Profile', 'user' => $user, 'errors' => $errors, 'old' => []]);
        return;
    }

    $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
    $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['user_id']]);

    session_regenerate_id(true);
    flash_set('success', 'Password changed.');
    redirect('/profile');
}