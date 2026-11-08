<div class="auth-wrap">
    <div class="auth-info">
        <p class="eyebrow">Campus Lost &amp; Found</p>
        <h1>Create your account</h1>
        <p class="lead">Use your university email. It takes about a minute.</p>
        <ul class="tick-list">
            <li>Verify your email once, then you can post, claim and message</li>
            <li>Your phone number is optional and never shown publicly</li>
            <li>Only university emails are accepted, so every user is a real member of campus</li>
        </ul>
    </div>

    <div class="auth-card">
        <h2>Your details</h2>
        <form method="post" action="<?= e(url('/register')) ?>" class="form" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" type="text" maxlength="100" required autocomplete="name" value="<?= e($old['full_name'] ?? '') ?>">
                <?= field_error($errors, 'full_name') ?>
            </div>

            <div class="field">
                <label for="email">University email</label>
                <input id="email" name="email" type="email" maxlength="150" required autocomplete="username" placeholder="rollno@<?= e(config('app.allowed_email_domain')) ?>" value="<?= e($old['email'] ?? '') ?>">
                <?= field_error($errors, 'email') ?>
            </div>

            <div class="field">
                <label for="student_id">Roll number <span class="muted">(optional)</span></label>
                <input id="student_id" name="student_id" type="text" maxlength="20" value="<?= e($old['student_id'] ?? '') ?>">
                <?= field_error($errors, 'student_id') ?>
            </div>

            <div class="field">
                <label for="phone">Phone <span class="muted">(optional)</span></label>
                <input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?= e($old['phone'] ?? '') ?>">
                <?= field_error($errors, 'phone') ?>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
                <p class="hint">At least <?= e(config('app.password_min_length')) ?> characters.</p>
                <?= field_error($errors, 'password') ?>
            </div>

            <div class="field">
                <label for="password_confirm">Confirm password</label>
                <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
                <?= field_error($errors, 'password_confirm') ?>
            </div>

            <button type="submit" class="btn btn-lg btn-block">Create account</button>
        </form>
        <p class="auth-alt">Already registered? <a href="<?= e(url('/login')) ?>">Log in</a></p>
    </div>
</div>
