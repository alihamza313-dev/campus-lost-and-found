<p><a href="<?= e(url('/admin')) ?>">&laquo; Admin dashboard</a></p>
<h1>Categories and locations</h1>
<p class="muted">Items are never deleted from these lists. Deactivate a name to hide it from new listings. Old listings keep it.</p>

<?php foreach ($sets as $kind => $rows):
    $base = $kind === 'categories' ? '/admin/categories' : '/admin/locations';
    $label = $kind === 'categories' ? 'Categories' : 'Campus locations';
?>
    <h2><?= e($label) ?></h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th scope="col">Name</th><th scope="col">Used by</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <form method="post" action="<?= e(url($base . '/' . (int) $r['id'] . '/rename')) ?>" class="inline-edit">
                            <?= csrf_field() ?>
                            <label class="visually-hidden" for="n-<?= e($kind) ?>-<?= (int) $r['id'] ?>">Name</label>
                            <input id="n-<?= e($kind) ?>-<?= (int) $r['id'] ?>" name="name" type="text" maxlength="100" value="<?= e($r['name']) ?>" required>
                            <button type="submit" class="btn btn-sm btn-secondary">Rename</button>
                        </form>
                    </td>
                    <td><?= (int) $r['uses'] ?> listing<?= (int) $r['uses'] === 1 ? '' : 's' ?></td>
                    <td><?= (int) $r['is_active'] ? 'Active' : 'Inactive' ?></td>
                    <td>
                        <form method="post" action="<?= e(url($base . '/' . (int) $r['id'] . '/active')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="active" value="<?= (int) $r['is_active'] ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-sm <?= (int) $r['is_active'] ? 'btn-danger' : 'btn-secondary' ?>"><?= (int) $r['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="post" action="<?= e(url($base . '/new')) ?>" class="inline-edit add-row">
        <?= csrf_field() ?>
        <label class="visually-hidden" for="add-<?= e($kind) ?>">New name</label>
        <input id="add-<?= e($kind) ?>" name="name" type="text" maxlength="100" placeholder="New name" required>
        <button type="submit" class="btn btn-sm">Add</button>
    </form>
<?php endforeach; ?>