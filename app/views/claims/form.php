<p><a href="<?= e(url('/items/' . (int) $item['item_id'])) ?>">&laquo; Back to listing</a></p>
<h1>Claim this item</h1>
<p><strong><?= e($item['title']) ?></strong>
   <span class="muted">&middot; found at <?= e($item['location_name']) ?> on <?= e($item['incident_date']) ?></span></p>

<div class="alert alert-warning" role="note">
    <p>Describe things only the real owner would know, such as contents, marks, stickers or a serial number. The finder may have recorded a private detail, and a custodian will compare it with your statement.</p>
    <p>If your claim is approved, you must show your ID in person when you collect the item.</p>
</div>

<?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/items/' . (int) $item['item_id'] . '/claim')) ?>" enctype="multipart/form-data" class="form form-wide" novalidate>
    <?= csrf_field() ?>
    <?= form_token_field() ?>

    <div class="field">
        <label for="ownership_details">Your ownership statement</label>
        <textarea id="ownership_details" name="ownership_details" rows="6" maxlength="2000" required><?= e($old['ownership_details']) ?></textarea>
        <p class="hint">Private. Only you and the reviewing custodians can read it.</p>
        <?= field_error($errors, 'ownership_details') ?>
    </div>

    <div class="field">
        <label for="proof">Proof file <span class="muted">(optional)</span></label>
        <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
        <p class="hint">For example a receipt or a photo of you with the item. JPG, PNG, WebP or PDF, 2 MB maximum. Private. You must select it again if the form shows an error.</p>
        <?= field_error($errors, 'proof') ?>
    </div>

    <div class="field">
        <label for="proof_description">What does the file show? <span class="muted">(optional)</span></label>
        <input id="proof_description" name="proof_description" type="text" maxlength="500" value="<?= e($old['proof_description']) ?>">
        <?= field_error($errors, 'proof_description') ?>
    </div>

    <button type="submit" class="btn">Submit claim</button>
    <a class="btn btn-secondary" href="<?= e(url('/items/' . (int) $item['item_id'])) ?>">Cancel</a>
</form>