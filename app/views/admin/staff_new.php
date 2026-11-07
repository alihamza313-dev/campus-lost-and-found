<p><a href="<?= e(url('/admin')) ?>">&laquo; Admin dashboard</a></p>
<h1>Create a custodian</h1>
<p class="muted">Set a starting password and give it to the person in private. They must verify their email with the link we send before they can act, and they can change the password from their profile.</p>

<form method="post" action="<?= e(url('/admin/staff/new')) ?>" class="form" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" type="text" maxlength="100" required value="<?= e($old['full_name'] ?? '') ?>">
        <?= field_error($errors, 'full_name') ?>
    </div>
    <div class="field">
        <label for="email">University email</label>
        <input id="email" name="email" type="email" maxlength="150" required value="<?= e($old['email'] ?? '') ?>">
        <?= field_error($errors, 'email') ?>
    </div>
    <div class="field">
        <label for="password">Starting password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <?= field_error($errors, 'password') ?>
    </div>
    <div class="field">
        <label for="password_confirm">Repeat password</label>
        <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
        <?= field_error($errors, 'password_confirm') ?>
    </div>
    <button type="submit" class="btn">Create custodian</button>
</form>