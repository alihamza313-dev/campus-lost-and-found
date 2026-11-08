<?php
$isEdit     = ($mode === 'edit');
$type       = $old['report_type'] ?? 'lost';
$action     = $isEdit ? url('/items/' . (int) $item['item_id'] . '/edit') : url('/items/new');
$showHidden = !$isEdit || $type === 'found';
$existing   = count($images);
?>
<p class="crumbs"><a href="<?= e(url($isEdit ? '/my/items' : '/dashboard')) ?>">&laquo; <?= $isEdit ? 'My listings' : 'Home' ?></a></p>
<h1><?= $isEdit ? 'Edit listing' : 'Report an item' ?></h1>
<p class="lead"><?= $isEdit ? 'Update the details below. The type cannot be changed.' : 'Answer a few questions. It takes about two minutes.' ?></p>

<?php if (!empty($errors['form'])): ?>
    <div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<?php if ($warning): ?>
    <div class="alert alert-warning" role="alert">
        <p><strong>This looks like a listing you already posted</strong> (same type, category, location and date in the last 3 days).</p>
        <p>If it really is a different item, choose your images again and press <strong>Post anyway</strong>.</p>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="form form-wide" novalidate>
    <?= csrf_field() ?>
    <?= form_token_field() ?>
    <?php if ($warning): ?><input type="hidden" name="confirm_duplicate" value="1"><?php endif; ?>

    <div class="form-section">
        <h2>What happened?</h2>
        <?php if ($isEdit): ?>
            <p>This is a <strong><?= $type === 'found' ? 'found' : 'lost' ?> item</strong> listing.</p>
        <?php else: ?>
            <fieldset class="field">
                <legend class="visually-hidden">This item is</legend>
                <div class="type-cards">
                    <label class="type-card">
                        <input type="radio" name="report_type" value="lost" <?= $type === 'lost' ? 'checked' : '' ?>>
                        <strong>I lost it</strong>
                        <span>Post what is missing so finders can contact you.</span>
                    </label>
                    <label class="type-card">
                        <input type="radio" name="report_type" value="found" <?= $type === 'found' ? 'checked' : '' ?>>
                        <strong>I found it</strong>
                        <span>Post what you found so its owner can claim it.</span>
                    </label>
                </div>
                <?= field_error($errors, 'report_type') ?>
            </fieldset>
        <?php endif; ?>

        <div class="field">
            <label for="title">Short title</label>
            <input id="title" name="title" type="text" maxlength="120" required value="<?= e($old['title']) ?>">
            <p class="hint">Say what it is, with one detail. For example &ldquo;Black Casio calculator&rdquo;.</p>
            <?= field_error($errors, 'title') ?>
        </div>

        <div class="field">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">Choose a category</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['category_id'] ?>" <?= (int) $old['category_id'] === (int) $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'category_id') ?>
        </div>
    </div>

    <div class="form-section">
        <h2>Where and when?</h2>
        <div class="field">
            <label for="location_id">Place on campus</label>
            <select id="location_id" name="location_id" required>
                <option value="">Choose a location</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= (int) $l['location_id'] ?>" <?= (int) $old['location_id'] === (int) $l['location_id'] ? 'selected' : '' ?>><?= e($l['location_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'location_id') ?>
        </div>

        <div class="field">
            <label for="incident_date">Date it was lost or found</label>
            <input id="incident_date" name="incident_date" type="date" max="<?= e(today_local()) ?>" required value="<?= e($old['incident_date']) ?>">
            <?= field_error($errors, 'incident_date') ?>
        </div>
    </div>

    <div class="form-section">
        <h2>Describe it</h2>
        <div class="field">
            <label for="description">Public description</label>
            <textarea id="description" name="description" rows="5" maxlength="2000" required><?= e($old['description']) ?></textarea>
            <p class="hint">Colour, brand, size and condition. Everyone can read this, so leave out serial numbers and personal details.</p>
            <?= field_error($errors, 'description') ?>
        </div>

        <?php if ($showHidden): ?>
            <div class="field" id="hidden-detail-box" <?= $type === 'found' ? '' : 'hidden' ?>>
                <label for="hidden_detail">Private detail <span class="muted">(optional)</span></label>
                <textarea id="hidden_detail" name="hidden_detail" rows="3" maxlength="1000"><?= e($old['hidden_detail']) ?></textarea>
                <p class="hint">Something only the real owner would know, such as a serial number or a sticker inside. It is never shown publicly. Only you, custodians and admins can see it.</p>
                <?= field_error($errors, 'hidden_detail') ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-section">
        <h2>Photos <span class="muted">(optional)</span></h2>
        <div class="field">
            <?php if ($existing > 0): ?>
                <div class="thumbs">
                    <?php foreach ($images as $img): ?>
                        <img src="<?= e(url('uploads/items/' . $img['image_path'])) ?>" alt="Image attached to this listing" width="96" height="96">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($existing < 4): ?>
                <label for="images" class="visually-hidden">Choose images</label>
                <input id="images" name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp">
                <p class="hint">Listings with a photo get found faster. JPG, PNG or WebP, 2 MB each. You can add <?= 4 - $existing ?> more.</p>
            <?php else: ?>
                <p class="hint">This listing already has 4 photos.</p>
            <?php endif; ?>
            <?= field_error($errors, 'images') ?>
        </div>
    </div>

    <button type="submit" class="btn btn-lg"><?= $warning ? 'Post anyway' : ($isEdit ? 'Save changes' : 'Post listing') ?></button>
    <a class="btn btn-secondary btn-lg" href="<?= e(url('/my/items')) ?>">Cancel</a>
</form>
