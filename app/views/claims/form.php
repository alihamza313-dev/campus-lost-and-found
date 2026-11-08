<p class="crumbs"><a href="<?= e(url('/items/' . (int) $item['item_id'])) ?>">&laquo; Back to the listing</a></p>

<div class="item-layout">
    <div>
        <h1>Claim this item</h1>
        <p class="lead"><?= e($item['title']) ?> <span class="muted">&middot; found at <?= e($item['location_name']) ?> on <?= e(ui_date($item['incident_date'])) ?></span></p>

        <?php if (!empty($errors['form'])): ?>
            <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/items/' . (int) $item['item_id'] . '/claim')) ?>" enctype="multipart/form-data" class="form form-wide" novalidate>
            <?= csrf_field() ?>
            <?= form_token_field() ?>

            <div class="form-section">
                <h2>1. Prove it is yours</h2>
                <div class="field">
                    <label for="ownership_details">What can you tell us about it?</label>
                    <textarea id="ownership_details" name="ownership_details" rows="6" maxlength="2000" required><?= e($old['ownership_details']) ?></textarea>
                    <p class="hint">Private. Only you and the reviewing custodians can read this.</p>
                    <?= field_error($errors, 'ownership_details') ?>
                </div>
            </div>

            <div class="form-section">
                <h2>2. Add proof <span class="muted">(optional)</span></h2>
                <div class="field">
                    <label for="proof">File</label>
                    <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
                    <p class="hint">A receipt, or a photo of you with the item. JPG, PNG, WebP or PDF, 2 MB maximum. Private. If the form shows an error you need to choose the file again.</p>
                    <?= field_error($errors, 'proof') ?>
                </div>
                <div class="field">
                    <label for="proof_description">What does the file show?</label>
                    <input id="proof_description" name="proof_description" type="text" maxlength="500" value="<?= e($old['proof_description']) ?>">
                    <?= field_error($errors, 'proof_description') ?>
                </div>
            </div>

            <button type="submit" class="btn btn-lg">Submit claim</button>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('/items/' . (int) $item['item_id'])) ?>">Cancel</a>
        </form>
    </div>

    <aside class="item-side">
        <div class="panel panel-action">
            <h2 class="panel-title">What makes a strong claim</h2>
            <ul class="tick-list">
                <li>Contents, such as cards, keys or notes inside</li>
                <li>Marks, stickers, scratches or engravings</li>
                <li>A serial number or lock-screen detail</li>
                <li>Anything a stranger could not guess from the photo</li>
            </ul>
            <p class="status-help">If approved, you show your student ID in person to collect the item. Nothing is handed over before that.</p>
        </div>
    </aside>
</div>
