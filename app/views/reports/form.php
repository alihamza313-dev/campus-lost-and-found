<p><a href="<?= e(url($target['back'])) ?>">&laquo; Back</a></p>
<h1>Report a problem</h1>
<p><strong><?= e($target['label']) ?></strong></p>
<p class="muted">Reports go to the moderators. Please report only real problems. False reports can lead to action against your account.</p>

<?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url($action)) ?>" class="form" novalidate>
    <?= csrf_field() ?>
    <?= form_token_field() ?>

    <div class="field">
        <label for="reason">Reason</label>
        <select id="reason" name="reason" required>
            <option value="">Choose a reason</option>
            <?php foreach (REPORT_REASONS as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= $old['reason'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'reason') ?>
    </div>

    <div class="field">
        <label for="details">Details <span class="muted">(optional)</span></label>
        <textarea id="details" name="details" rows="4" maxlength="1000"><?= e($old['details']) ?></textarea>
        <?= field_error($errors, 'details') ?>
    </div>

    <button type="submit" class="btn">Send report</button>
    <a class="btn btn-secondary" href="<?= e(url($target['back'])) ?>">Cancel</a>
</form>