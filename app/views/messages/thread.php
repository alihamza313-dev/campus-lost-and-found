<?php $itemTitle = $item['item_status'] === 'removed' ? 'Removed listing' : $item['title']; ?>
<p><a href="<?= e(url('/messages')) ?>">&laquo; All messages</a></p>
<h1>Conversation about &ldquo;<?= e($itemTitle) ?>&rdquo;</h1>
<p class="meta">With <?= e($otherName) ?>
    <?php if (in_array($item['item_status'], ITEM_PUBLIC_STATUSES, true)): ?>
        &middot; <a href="<?= e(url('/items/' . (int) $item['item_id'])) ?>">View listing</a>
    <?php endif; ?>
</p>

<div class="thread" aria-label="Messages">
    <?php if (!$messages): ?>
        <p class="empty">No messages yet. Write the first one below.</p>
    <?php endif; ?>
    <?php foreach ($messages as $m): $mine = (int) $m['sender_id'] === (int) $me; ?>
        <div class="bubble <?= $mine ? 'bubble-mine' : 'bubble-theirs' ?>">
            <?php if ($m['message_status'] === 'removed'): ?>
                <p class="muted"><em>This message was removed by a moderator.</em></p>
            <?php else: ?>
                <p class="prewrap"><?= e($m['message_text']) ?></p>
            <?php endif; ?>
            <small class="muted"><?= $mine ? 'You' : e($otherName) ?> &middot; <?= e(substr((string) $m['created_at'], 0, 16)) ?></small>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($canSend): ?>
    <form method="post" action="<?= e(url('/messages/' . (int) $item['item_id'] . '/' . (int) $otherId)) ?>" class="form form-wide" novalidate>
        <?= csrf_field() ?>
        <?= form_token_field() ?>
        <div class="field">
            <label for="message_text">Your message</label>
            <textarea id="message_text" name="message_text" rows="4" maxlength="2000" required><?= e($old) ?></textarea>
            <?php if ($error): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            <p class="hint">Do not share passwords or payment details. Sensitive proof belongs in a claim, not in a message.</p>
        </div>
        <button type="submit" class="btn">Send message</button>
    </form>
<?php else: ?>
    <div class="alert alert-warning" role="status"><?= e($ruleError) ?></div>
<?php endif; ?>