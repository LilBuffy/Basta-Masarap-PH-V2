<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$input = ajaxInput();
$action = $input['action'] ?? '';
$dishId = filter_var($input['dish_id'] ?? null, FILTER_VALIDATE_INT);
if (!$dishId || $dishId < 1 || !in_array($action, ['add', 'update', 'remove'], true)) {
    jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
}

normalizeCart();

$respond = static function (array $extra = []) use ($db): never {
    $cart = cartData($db);
    jsonResponse(array_merge([
        'success' => true,
        'cart_count' => $cart['count'],
        'subtotal' => peso($cart['subtotal']),
        'delivery_fee' => peso($cart['delivery_fee']),
        'total' => peso($cart['total']),
        'unavailable' => $cart['unavailable'],
        'max_qty' => CART_MAX_QTY,
        'empty' => $cart['count'] === 0,
    ], $extra));
};

if ($action === 'add') {
    $stmt = $db->prepare('SELECT is_available FROM dishes WHERE dish_id = ?');
    $stmt->execute([$dishId]);
    $available = $stmt->fetchColumn();
    if ($available === false) {
        jsonResponse(['success' => false, 'message' => t('err_dish_gone')], 404);
    }
    if (!$available) {
        jsonResponse(['success' => false, 'message' => t('err_dish_off')], 409);
    }
    $current = $_SESSION['cart'][$dishId] ?? 0;
    if ($current >= CART_MAX_QTY) {
        jsonResponse(['success' => false, 'message' => t('err_cart_max', CART_MAX_QTY)], 409);
    }
    if ($current === 0 && count($_SESSION['cart']) >= CART_MAX_LINES) {
        jsonResponse(['success' => false, 'message' => t('err_cart_lines')], 409);
    }
    $_SESSION['cart'][$dishId] = $current + 1;
    $respond(['qty' => $_SESSION['cart'][$dishId]]);
}

if (!isset($_SESSION['cart'][$dishId])) {
    $respond(['removed' => true, 'qty' => 0, 'line_total' => peso(0)]);
}

if ($action === 'remove') {
    unset($_SESSION['cart'][$dishId]);
    $respond(['removed' => true, 'qty' => 0, 'line_total' => peso(0)]);
}

$delta = (int) ($input['delta'] ?? 0) <=> 0;
$qty = max(0, min(CART_MAX_QTY, $_SESSION['cart'][$dishId] + $delta));
if ($qty === 0) {
    unset($_SESSION['cart'][$dishId]);
    $respond(['removed' => true, 'qty' => 0, 'line_total' => peso(0)]);
}
$_SESSION['cart'][$dishId] = $qty;

$price = $db->prepare('SELECT price FROM dishes WHERE dish_id = ?');
$price->execute([$dishId]);
$respond(['qty' => $qty, 'line_total' => peso((float) $price->fetchColumn() * $qty)]);
