<div class="auth-wrap">
    <div class="auth-info">
        <p class="eyebrow">Campus Lost &amp; Found</p>
        <h1>Welcome back</h1>
        <p class="lead">Log in to check your listings, claims and messages.</p>
        <ul class="tick-list">
            <li>Report something you lost or found</li>
            <li>Track your claims from review to pickup</li>
            <li>Message posters without sharing your phone number</li>
        </ul>
    </div>

    <div class="auth-card">
        <h2>Log in</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/login')) ?>" class="form" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">University email</label>
                <input id="email" name="email" type="email" autocomplete="username" required autofocus value="<?= e($old['email'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-lg btn-block">Log in</button>
        </form>
        <p class="auth-alt">New here? <a href="<?= e(url('/register')) ?>">Create an account</a></p>
    </div>
</div>
