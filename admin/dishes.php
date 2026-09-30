<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

const DISH_TOGGLES = ['is_available' => 'Available', 'is_featured' => 'Featured', 'is_popular' => 'Popular'];

$categories = $db->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
$categoryIds = array_map('intval', array_column($categories, 'category_id'));
$wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

function storeDishImage(array $file): ?string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE ? 'That image is too large. Use one under 4 MB.' : 'The image upload failed. Try again.');
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException('That image is too large. Use one under 4 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]]) || $info[0] > 6000 || $info[1] > 6000) {
        throw new RuntimeException('Use a JPG, PNG, or WebP image no larger than 6000 pixels on a side.');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], APP_ROOT . '/assets/images/dishes/' . $name)) {
        throw new RuntimeException('The image could not be saved. Check that the folder is writable.');
    }
    return 'assets/images/dishes/' . $name;
}

function removeDishImage(PDO $db, ?string $path, int $exceptDishId = 0): void
{
    if (!$path || !preg_match('#^assets/images/dishes/[a-f0-9]{24}\.(jpg|png|webp)$#', $path)) {
        return;
    }
    $used = $db->prepare('SELECT COUNT(*) FROM dishes WHERE image_path = ? AND dish_id != ?');
    $used->execute([$path, $exceptDishId]);
    if ((int) $used->fetchColumn() === 0 && is_file(APP_ROOT . '/' . $path)) {
        @unlink(APP_ROOT . '/' . $path);
    }
}

$errors = [];
$formError = null;
$mode = isset($_GET['new']) ? 'new' : (isset($_GET['edit']) ? 'edit' : 'list');
$editDish = null;
if ($mode === 'edit') {
    $stmt = $db->prepare('SELECT * FROM dishes WHERE dish_id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editDish = $stmt->fetch() ?: null;
    if (!$editDish) {
        flash('error', 'That dish no longer exists.');
        redirect('admin/dishes.php');
    }
}
$values = [
    'name' => $editDish['name'] ?? '', 'category_id' => (string) ($editDish['category_id'] ?? ($categoryIds[0] ?? '')),
    'desc_en' => $editDish['desc_en'] ?? '', 'desc_fil' => $editDish['desc_fil'] ?? '',
    'ingredients' => $editDish['ingredients'] ?? '', 'price' => isset($editDish['price']) ? (string) (float) $editDish['price'] : '',
];

if (isPost()) {
    $action = (string) ($_POST['action'] ?? '');
    $dishId = (int) ($_POST['dish_id'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        if ($wantsJson) {
            jsonResponse(['success' => false, 'message' => t('err_csrf')], 403);
        }
        $formError = t('err_csrf_form');
    } elseif ($action === 'toggle') {
        $field = (string) ($_POST['field'] ?? '');
        if (!isset(DISH_TOGGLES[$field]) || $dishId < 1) {
            jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
        }
        $db->prepare("UPDATE dishes SET {$field} = 1 - {$field} WHERE dish_id = ?")->execute([$dishId]);
        $now = $db->prepare("SELECT {$field} FROM dishes WHERE dish_id = ?");
        $now->execute([$dishId]);
        $value = $now->fetchColumn();
        if ($value === false) {
            jsonResponse(['success' => false, 'message' => 'That dish no longer exists.'], 404);
        }
        jsonResponse(['success' => true, 'value' => (bool) $value, 'message' => DISH_TOGGLES[$field] . ': ' . ($value ? 'on' : 'off')]);
    } elseif ($action === 'delete') {
        $row = $db->prepare('SELECT image_path FROM dishes WHERE dish_id = ?');
        $row->execute([$dishId]);
        $path = $row->fetchColumn();
        try {
            $db->prepare('DELETE FROM dishes WHERE dish_id = ?')->execute([$dishId]);
            removeDishImage($db, is_string($path) ? $path : null);
            flash('success', 'Dish deleted.');
        } catch (PDOException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
            flash('error', 'This dish is part of past orders, so it cannot be deleted. Turn off Available instead.');
        }
        redirect('admin/dishes.php');
    } elseif (in_array($action, ['create', 'update'], true)) {
        $values = [
            'name' => postString('name', 121), 'category_id' => postString('category_id', 10), 'desc_en' => postString('desc_en', 1000),
            'desc_fil' => postString('desc_fil', 1000), 'ingredients' => postString('ingredients', 500), 'price' => postString('price', 12),
        ];
        if ($values['name'] === '' || mb_strlen($values['name']) > 120) {
            $errors['name'] = 'Enter a name up to 120 characters.';
        }
        if (!in_array((int) $values['category_id'], $categoryIds, true)) {
            $errors['category_id'] = 'Choose a category.';
        }
        if ($values['desc_en'] === '') {
            $errors['desc_en'] = 'Add an English description.';
        }
        if ($values['desc_fil'] === '') {
            $errors['desc_fil'] = 'Add a Filipino description.';
        }
        if (!is_numeric($values['price']) || (float) $values['price'] < 1 || (float) $values['price'] > 99999) {
            $errors['price'] = 'Enter a price between 1 and 99,999.';
        }

        $newImage = null;
        if (!$errors) {
            try {
                $newImage = storeDishImage($_FILES['image'] ?? []);
            } catch (RuntimeException $e) {
                $errors['image'] = $e->getMessage();
            }
        }

        if (!$errors) {
            $price = round((float) $values['price'], 2);
            if ($action === 'create') {
                $db->prepare('INSERT INTO dishes (category_id, name, desc_en, desc_fil, ingredients, price, image_path, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, 1)')
                   ->execute([(int) $values['category_id'], $values['name'], $values['desc_en'], $values['desc_fil'], $values['ingredients'], $price, $newImage ?? '']);
                flash('success', 'Dish added.');
            } else {
                $old = $db->prepare('SELECT image_path FROM dishes WHERE dish_id = ?');
                $old->execute([$dishId]);
                $oldPath = $old->fetchColumn();
                if ($oldPath === false) {
                    flash('error', 'That dish no longer exists.');
                    redirect('admin/dishes.php');
                }
                $db->prepare('UPDATE dishes SET category_id = ?, name = ?, desc_en = ?, desc_fil = ?, ingredients = ?, price = ?, image_path = ? WHERE dish_id = ?')
                   ->execute([(int) $values['category_id'], $values['name'], $values['desc_en'], $values['desc_fil'], $values['ingredients'], $price, $newImage ?? $oldPath, $dishId]);
                if ($newImage) {
                    removeDishImage($db, (string) $oldPath, $dishId);
                }
                flash('success', 'Dish updated.');
            }
            redirect('admin/dishes.php');
        }
        $mode = $action === 'create' ? 'new' : 'edit';
        if ($mode === 'edit') {
            $stmt = $db->prepare('SELECT * FROM dishes WHERE dish_id = ?');
            $stmt->execute([$dishId]);
            $editDish = $stmt->fetch() ?: null;
        }
    }
}

$dishes = $mode === 'list' ? $db->query('SELECT d.*, c.name_en AS cat_name FROM dishes d JOIN categories c ON c.category_id = d.category_id ORDER BY d.name ASC')->fetchAll() : [];
$catOptions = [];
foreach ($categories as $cat) {
    $catOptions[$cat['category_id']] = $cat['name_en'];
}

$pageTitle = $mode === 'list' ? 'Dishes' : ($mode === 'new' ? 'Add dish' : 'Edit dish');
require __DIR__ . '/../includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead('Admin', '', $mode === 'list' ? '<a class="btn btn-primary btn-sm" href="' . esc(url('admin/dishes.php?new=1')) . '">' . icon('plus') . 'Add dish</a>' : '') ?>
        <?= adminNav('dishes') ?>

        <?php if ($mode !== 'list'): ?>
        <div class="panel panel-narrow">
            <h2><?= $mode === 'new' ? 'Add dish' : 'Edit ' . esc($editDish['name'] ?? 'dish') ?></h2>
            <?php if ($formError): ?><?= alertBox('error', $formError) ?><?php endif; ?>
            <form method="post" enctype="multipart/form-data" data-validate data-once novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="<?= $mode === 'new' ? 'create' : 'update' ?>">
                <?php if ($mode === 'edit'): ?><input type="hidden" name="dish_id" value="<?= (int) $editDish['dish_id'] ?>"><?php endif; ?>
                <div class="field-row">
                    <?= field(['name' => 'name', 'label' => 'Name', 'value' => $values['name'], 'required' => true, 'maxlength' => 120, 'error' => $errors['name'] ?? null]) ?>
                    <?= field(['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'options' => $catOptions, 'value' => $values['category_id'], 'required' => true, 'error' => $errors['category_id'] ?? null]) ?>
                </div>
                <?= field(['name' => 'desc_en', 'label' => 'Description (English)', 'type' => 'textarea', 'value' => $values['desc_en'], 'required' => true, 'maxlength' => 1000, 'error' => $errors['desc_en'] ?? null]) ?>
                <?= field(['name' => 'desc_fil', 'label' => 'Description (Filipino)', 'type' => 'textarea', 'value' => $values['desc_fil'], 'required' => true, 'maxlength' => 1000, 'error' => $errors['desc_fil'] ?? null]) ?>
                <?= field(['name' => 'ingredients', 'label' => 'Ingredients', 'value' => $values['ingredients'], 'maxlength' => 500, 'hint' => 'Separate with commas.']) ?>
                <div class="field-row">
                    <?= field(['name' => 'price', 'label' => 'Price (PHP)', 'type' => 'number', 'value' => $values['price'], 'required' => true, 'min' => '1', 'max' => '99999', 'step' => '0.01', 'inputmode' => 'decimal', 'data-range-message' => 'Enter a price between 1 and 99,999.', 'error' => $errors['price'] ?? null]) ?>
                    <div>
                        <?php if ($mode === 'edit' && dishImageUrl($editDish)): ?>
                            <div class="file-current"><img src="<?= esc(dishImageUrl($editDish)) ?>" alt="" width="96" height="72"><span class="muted">Current photo</span></div>
                        <?php endif; ?>
                        <?= field(['name' => 'image', 'label' => $mode === 'edit' ? 'Replace photo' : 'Photo', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'hint' => 'JPG, PNG, or WebP, up to 4 MB.', 'error' => $errors['image'] ?? null]) ?>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><?= $mode === 'new' ? 'Add dish' : 'Save changes' ?></button>
                    <a class="btn btn-ghost" href="<?= esc(url('admin/dishes.php')) ?>">Cancel</a>
                </div>
            </form>
        </div>
        <?php else: ?>
            <?php if (!$dishes): ?>
                <?= emptyState('No dishes yet', 'Add your first dish to start the menu.', url('admin/dishes.php?new=1'), 'Add dish') ?>
            <?php else: ?>
            <div class="table-wrap" tabindex="0" role="region" aria-label="Dishes">
                <table class="data-table">
                    <thead><tr><th>Photo</th><th>Name</th><th>Category</th><th>Price</th><?php foreach (DISH_TOGGLES as $label): ?><th><?= esc($label) ?></th><?php endforeach; ?><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($dishes as $d): ?>
                        <tr>
                            <td><?php if ($img = dishImageUrl($d)): ?><img class="thumb" src="<?= esc($img) ?>" alt="" width="48" height="36" loading="lazy"><?php else: ?><span class="thumb thumb-empty"><?= icon('bowl') ?></span><?php endif; ?></td>
                            <td><?= esc($d['name']) ?></td>
                            <td><?= esc($d['cat_name']) ?></td>
                            <td><?= esc(peso($d['price'])) ?></td>
                            <?php foreach (DISH_TOGGLES as $field => $label): ?>
                            <td>
                                <form method="post" data-async>
                                    <?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="field" value="<?= $field ?>"><input type="hidden" name="dish_id" value="<?= (int) $d['dish_id'] ?>">
                                    <button class="switch" type="submit" role="switch" data-async-control aria-checked="<?= $d[$field] ? 'true' : 'false' ?>" aria-label="<?= esc($label . ': ' . $d['name']) ?>"></button>
                                </form>
                            </td>
                            <?php endforeach; ?>
                            <td>
                                <div class="cell-actions">
                                    <a class="btn btn-secondary btn-sm" href="<?= esc(url('admin/dishes.php?edit=' . (int) $d['dish_id'])) ?>">Edit</a>
                                    <form method="post" data-once<?= confirmAttrs('Delete ' . $d['name'] . '? This cannot be undone.', 'Delete') ?>>
                                        <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="dish_id" value="<?= (int) $d['dish_id'] ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
