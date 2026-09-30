<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$cart = cartData($db);
$payable = array_filter($cart['lines'], static fn ($line) => $line['available']);
if (!$cart['lines']) {
    redirect('cart.php');
}
if ($cart['unavailable'] > 0) {
    flash('error', t('cart_unavailable_notice'));
    redirect('cart.php');
}

$user = currentUser();
$values = ['delivery_address' => '', 'contact_number' => (string) ($user['contact_number'] ?? '')];
$errors = [];
$formError = null;

if (isPost()) {
    $values['delivery_address'] = postString('delivery_address', 255);
    $values['contact_number'] = postString('contact_number', 30);

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $formError = t('err_csrf_form');
    } else {
        if (mb_strlen($values['delivery_address']) < 10) {
            $errors['delivery_address'] = t('err_address');
        }
        if (!validatePhone($values['contact_number'])) {
            $errors['contact_number'] = t('err_phone');
        }
        if (!$payable) {
            $formError = t('err_cart_empty');
        }
        if (!$errors && !$formError) {
            try {
                $code = placeOrder($db, userId(), $values['delivery_address'], $values['contact_number'], $cart);
                $_SESSION['cart'] = [];
                redirect('orders.php?placed=' . rawurlencode($code));
            } catch (Throwable $e) {
                error_log('Checkout failed: ' . $e->getMessage());
                $formError = t('err_checkout');
            }
        }
    }
}

$pageTitle = t('checkout_title');
require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead(t('checkout_title'), t('checkout_sub')) ?>
        <div class="cart-layout">
            <form class="panel" method="post" data-validate novalidate>
                <?= csrfField() ?>
                <?php if ($formError): ?><?= alertBox('error', $formError) ?><?php endif; ?>
                <?= field(['name' => 'delivery_address', 'label' => t('delivery_address'), 'type' => 'textarea', 'value' => $values['delivery_address'], 'required' => true, 'maxlength' => 255, 'minlength' => 10, 'autocomplete' => 'street-address', 'hint' => t('address_hint'), 'error' => $errors['delivery_address'] ?? null]) ?>
                <?= field(['name' => 'contact_number', 'label' => t('contact_number'), 'type' => 'tel', 'value' => $values['contact_number'], 'required' => true, 'maxlength' => 30, 'autocomplete' => 'tel', 'inputmode' => 'tel', 'pattern' => '[0-9+\(\)\-\s]{7,20}', 'data-pattern-message' => t('err_phone'), 'hint' => t('contact_hint'), 'error' => $errors['contact_number'] ?? null]) ?>
                <button class="btn btn-primary btn-block" type="submit"><?= te('place_order') ?> &middot; <?= esc(peso($cart['total'])) ?></button>
                <p class="field-hint mt-4"><?= te('pay_note') ?></p>
            </form>
            <aside class="summary" aria-labelledby="summary-title">
                <h2 id="summary-title"><?= te('cart_title') ?></h2>
                <div class="summary-items">
                    <?php foreach ($payable as $line): ?>
                        <div class="summary-row"><span><?= (int) $line['qty'] ?> &times; <?= esc($line['dish']['name']) ?></span><span><?= esc(peso($line['line_total'])) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-row"><span><?= te('subtotal') ?></span><span><?= esc(peso($cart['subtotal'])) ?></span></div>
                <div class="summary-row"><span><?= te('delivery_fee') ?></span><span><?= esc(peso($cart['delivery_fee'])) ?></span></div>
                <div class="summary-row is-total"><span><?= te('total') ?></span><span><?= esc(peso($cart['total'])) ?></span></div>
                <a class="btn btn-ghost btn-block mt-4" href="<?= esc(url('cart.php')) ?>"><?= te('edit_cart') ?></a>
            </aside>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
