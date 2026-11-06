<h1>Log in</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/login')) ?>" class="form" novalidate>
    <?= csrf_field() ?>

    <div class="field">
        <label for="email">University email</label>
        <input id="email" name="email" type="email" autocomplete="username" required value="<?= e($old['email'] ?? '') ?>">
    </div>

    <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>

    <button type="submit" class="btn">Log in</button>
    <p>No account yet? <a href="<?= e(url('/register')) ?>">Register</a></p>
</form>