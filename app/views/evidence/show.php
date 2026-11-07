<p><a href="<?= e(url($back[0])) ?>">&laquo; <?= e($back[1]) ?></a></p>

<h1><?= $ev['evidence_type'] === 'item_detail' ? 'Finder&rsquo;s private detail' : 'Claim proof' ?></h1>
<p class="muted">For &ldquo;<?= e($ev['item_title']) ?>&rdquo;. This view was recorded in the audit log.</p>

<h2>Description</h2>
<p class="prewrap"><?= e($ev['private_description']) ?></p>

<?php if (!empty($ev['file_reference'])): ?>
    <p><a class="btn" href="<?= e(url('/evidence/' . (int) $ev['evidence_id'] . '/file')) ?>" target="_blank" rel="noopener">Open attached file</a></p>
<?php endif; ?>