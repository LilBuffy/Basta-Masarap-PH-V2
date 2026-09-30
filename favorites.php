<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$dishes = dishRows($db, 'd.dish_id IN (SELECT dish_id FROM favorites WHERE user_id = ?)', [userId()], 'd.name ASC');
$pageTitle = t('favorites_title');
require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead(t('favorites_title'), t('favorites_sub')) ?>
        <?= accountNav('favorites') ?>

        <?= emptyState(t('no_favorites'), t('no_favorites_text'), url('menu.php'), t('nav_menu'), 'heart', (bool) $dishes) ?>

        <?php if ($dishes): ?>
        <ul class="dish-grid">
            <?php foreach ($dishes as $dish): ?>
                <?= dishCard($dish, ['toggle' => 'favorite', 'active' => true, 'remove_on_off' => true]) ?>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
