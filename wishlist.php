<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$dishes = dishRows($db, 'd.dish_id IN (SELECT dish_id FROM wishlists WHERE user_id = ?)', [userId()], 'd.name ASC');
$pageTitle = t('wishlist_title');
require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead(t('wishlist_title'), t('wishlist_sub')) ?>
        <?= accountNav('wishlist') ?>

        <?= emptyState(t('no_wishlist'), t('no_wishlist_text'), url('menu.php'), t('nav_menu'), 'bookmark', (bool) $dishes) ?>

        <?php if ($dishes): ?>
        <ul class="dish-grid">
            <?php foreach ($dishes as $dish): ?>
                <?= dishCard($dish, ['toggle' => 'wishlist', 'active' => true, 'remove_on_off' => true]) ?>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
