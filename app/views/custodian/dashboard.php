<h1>Custodian dashboard</h1>

<h2>Claims waiting for review</h2>
<?php if (!$queue): ?>
    <p class="empty">No claims are waiting for review.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Item</th><th scope="col">Active claims</th><th scope="col">Waiting since</th><th scope="col"></th></tr></thead>
            <tbody>
            <?php foreach ($queue as $q): ?>
                <tr>
                    <td><?= e($q['title']) ?></td>
                    <td><?= (int) $q['active_claims'] ?></td>
                    <td><?= e(substr((string) $q['oldest'], 0, 16)) ?></td>
                    <td><a class="btn btn-sm" href="<?= e(url('/review/items/' . (int) $q['item_id'])) ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<h2>Handovers</h2>
<?php if (!$handovers): ?>
    <p class="empty">No approved claims are waiting for a handover.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Item</th><th scope="col">Claimant</th><th scope="col">Handover</th><th scope="col">Flags</th><th scope="col"></th></tr></thead>
            <tbody>
            <?php foreach ($handovers as $h): ?>
                <tr>
                    <td><?= e($h['title']) ?></td>
                    <td><?= e($h['claimant_name']) ?></td>
                    <td>
                        <?php if ($h['handover_status'] === 'scheduled'): ?>
                            <span class="badge badge-scheduled">Scheduled</span> <?= e(substr((string) $h['scheduled_at'], 0, 16)) ?>
                        <?php else: ?>
                            <span class="badge badge-pending">Not scheduled</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $h['is_high_value'] ? 'High value ' : '' ?><?= (int) $h['claims_filed'] > 1 ? 'Multiple claims' : '' ?></td>
                    <td><a class="btn btn-sm" href="<?= e(url('/claims/' . (int) $h['claim_id'] . '/handover')) ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>