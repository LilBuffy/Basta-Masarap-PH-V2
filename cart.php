<?php
require_once __DIR__ . '/includes/bootstrap.php';

$cart = cartData($db);
$pageTitle = t('cart_title');
require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead(t('cart_title')) ?>

        <div<?= $cart['lines'] ? ' hidden' : '' ?> data-empty-state>
            <?= emptyState(t('cart_empty'), t('cart_empty_text'), url('menu.php'), t('nav_menu'), 'cart') ?>
        </div>

        <?php if ($cart['lines']): ?>
        <div class="cart-layout" data-cart-layout>
            <div>
                <div class="alert alert-error" role="alert" data-unavailable-notice<?= $cart['unavailable'] > 0 ? '' : ' hidden' ?>><?= icon('alert') ?><p><?= te('cart_unavailable_notice') ?></p></div>
                <div class="cart-lines">
                    <?php foreach ($cart['lines'] as $line): $dish = $line['dish']; ?>
                    <div class="cart-line<?= $line['available'] ? '' : ' is-off' ?>" data-dish-id="<?= (int) $dish['dish_id'] ?>">
                        <div class="cart-line-img"><?= dishImage($dish) ?></div>
                        <div class="cart-line-info">
                            <h2 class="cart-line-name"><a href="<?= esc(url('dish.php?id=' . (int) $dish['dish_id'])) ?>"><?= esc($dish['name']) ?></a></h2>
                            <p class="cart-line-unit"><?= te('each', peso($dish['price'])) ?></p>
                            <?php if (!$line['available']): ?><p class="cart-line-warn"><?= te('cart_unavailable_line') ?></p><?php endif; ?>
                        </div>
                        <?php if ($line['available']): ?>
                        <div class="stepper" role="group" aria-label="<?= esc($dish['name']) ?>">
                            <button type="button" data-qty-step data-delta="-1" aria-label="<?= te('qty_decrease') ?>"<?= $line['qty'] <= 1 ? ' disabled' : '' ?>><?= icon('minus') ?></button>
                            <output data-line-qty><?= (int) $line['qty'] ?></output>
                            <button type="button" data-qty-step data-delta="1" aria-label="<?= te('qty_increase') ?>"<?= $line['qty'] >= CART_MAX_QTY ? ' disabled' : '' ?>><?= icon('plus') ?></button>
                        </div>
                        <?php else: ?><span></span><?php endif; ?>
                        <div class="cart-line-total" data-line-total><?= $line['available'] ? esc(peso($line['line_total'])) : '' ?></div>
                        <button type="button" class="remove-btn" data-remove-line aria-label="<?= te('remove_item', $dish['name']) ?>"><?= icon('trash') ?></button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <aside class="summary" aria-labelledby="summary-title">
                <h2 id="summary-title"><?= te('total') ?></h2>
                <div class="summary-row"><span><?= te('subtotal') ?></span><span data-summary="subtotal"><?= esc(peso($cart['subtotal'])) ?></span></div>
                <div class="summary-row"><span><?= te('delivery_fee') ?></span><span data-summary="fee"><?= esc(peso($cart['delivery_fee'])) ?></span></div>
                <div class="summary-row is-total"><span><?= te('total') ?></span><span data-summary="total"><?= esc(peso($cart['total'])) ?></span></div>
                <a class="btn btn-primary btn-block" href="<?= esc(url('checkout.php')) ?>" data-checkout<?= $cart['unavailable'] > 0 ? ' aria-disabled="true" tabindex="-1"' : '' ?>><?= te('checkout') ?></a>
                <a class="btn btn-ghost btn-block mt-2" href="<?= esc(url('menu.php')) ?>"><?= te('continue_shopping') ?></a>
            </aside>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
