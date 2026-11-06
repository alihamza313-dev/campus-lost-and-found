<h1>Your profile</h1>

<form method="post" action="<?= e(url('/profile')) ?>" class="form" novalidate>
    <?= csrf_field() ?>
    <h2>Details</h2>

    <div class="field">
        <label for="email_ro">University email</label>
        <input id="email_ro" type="email" value="<?= e($user['university_email']) ?>" disabled>
        <p class="hint">Your email cannot be changed.</p>
    </div>

    <div class="field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" type="text" maxlength="100" required value="<?= e($old['full_name'] ?? $user['full_name']) ?>">
        <?= field_error($errors, 'full_name') ?>
    </div>

    <div class="field">
        <label for="phone">Phone</label>
        <input id="phone" name="phone" type="tel" maxlength="20" value="<?= e($old['phone'] ?? ($user['phone'] ?? '')) ?>">
        <?= field_error($errors, 'phone') ?>
    </div>

    <button type="submit" class="btn">Save changes</button>
</form>

<form method="post" action="<?= e(url('/profile/password')) ?>" class="form" novalidate>
    <?= csrf_field() ?>
    <h2>Change password</h2>

    <div class="field">
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        <?= field_error($errors, 'current_password') ?>
    </div>

    <div class="field">
        <label for="new_password">New password</label>
        <input id="new_password" name="new_password" type="password" autocomplete="new-password" required>
        <?= field_error($errors, 'new_password') ?>
    </div>

    <div class="field">
        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
        <?= field_error($errors, 'confirm_password') ?>
    </div>

    <button type="submit" class="btn">Change password</button>
</form>