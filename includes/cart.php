<?php

const CART_MAX_QTY = 20;
const CART_MAX_LINES = 30;

function normalizeCart(): void
{
    $clean = [];
    foreach ($_SESSION['cart'] ?? [] as $dishId => $qty) {
        $dishId = (int) $dishId;
        $qty = (int) $qty;
        if ($dishId > 0 && $qty > 0) {
            $clean[$dishId] = min($qty, CART_MAX_QTY);
        }
    }
    $_SESSION['cart'] = array_slice($clean, 0, CART_MAX_LINES, true);
}

function cartCount(): int
{
    normalizeCart();
    return array_sum($_SESSION['cart']);
}

function cartData(PDO $db): array
{
    normalizeCart();
    $lines = [];
    $subtotal = 0.0;
    $unavailable = 0;
    $count = 0;

    if ($_SESSION['cart']) {
        $ids = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT dish_id, name, price, is_available, image_path FROM dishes WHERE dish_id IN ({$placeholders})");
        $stmt->execute($ids);
        $byId = [];
        foreach ($stmt->fetchAll() as $row) {
            $byId[(int) $row['dish_id']] = $row;
        }
        foreach ($_SESSION['cart'] as $dishId => $qty) {
            if (!isset($byId[$dishId])) {
                unset($_SESSION['cart'][$dishId]);
                continue;
            }
            $dish = $byId[$dishId];
            $available = (bool) $dish['is_available'];
            $lineTotal = (float) $dish['price'] * $qty;
            if ($available) {
                $subtotal += $lineTotal;
            } else {
                $unavailable++;
            }
            $count += $qty;
            $lines[] = ['dish' => $dish, 'qty' => $qty, 'line_total' => $lineTotal, 'available' => $available];
        }
    }

    $hasPayable = $subtotal > 0;
    $fee = $hasPayable ? deliveryFee() : 0.0;

    return [
        'lines' => $lines,
        'subtotal' => $subtotal,
        'delivery_fee' => $fee,
        'total' => $subtotal + $fee,
        'unavailable' => $unavailable,
        'count' => $count,
    ];
}
