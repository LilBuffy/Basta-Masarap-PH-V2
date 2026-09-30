<?php

function placeOrder(PDO $db, int $userId, string $address, string $contact, array $cart): string
{
    $lastError = null;
    for ($attempt = 0; $attempt < 4; $attempt++) {
        try {
            $db->beginTransaction();

            $orderCode = generateCode($db, 'ORD', 'orders', 'order_code');
            $db->prepare(
                'INSERT INTO orders (order_code, user_id, delivery_address, contact_number, subtotal, delivery_fee, total, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
            )->execute([$orderCode, $userId, $address, $contact, $cart['subtotal'], $cart['delivery_fee'], $cart['total']]);
            $orderId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO order_items (order_id, dish_id, dish_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($cart['lines'] as $line) {
                if (!$line['available']) {
                    continue;
                }
                $itemStmt->execute([$orderId, $line['dish']['dish_id'], $line['dish']['name'], $line['dish']['price'], $line['qty'], $line['line_total']]);
            }

            $deliveryCode = generateCode($db, 'DEL', 'deliveries', 'delivery_code');
            $db->prepare('INSERT INTO deliveries (delivery_code, order_id, status) VALUES (?, ?, "pending")')
               ->execute([$deliveryCode, $orderId]);

            $db->commit();
            return $orderCode;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $lastError = $e;
            if ((string) $e->getCode() !== '23000') {
                break;
            }
        }
    }
    throw $lastError ?? new RuntimeException('Order could not be placed');
}

function setOrderStatus(PDO $db, int $orderId, string $status, ?string $onlyFrom = null): bool
{
    $db->beginTransaction();
    try {
        $sql = 'UPDATE orders SET status = ? WHERE order_id = ?' . ($onlyFrom !== null ? ' AND status = ?' : '');
        $stmt = $db->prepare($sql);
        $stmt->execute($onlyFrom !== null ? [$status, $orderId, $onlyFrom] : [$status, $orderId]);
        $changed = $stmt->rowCount() > 0;
        if ($changed || $onlyFrom === null) {
            $db->prepare('UPDATE deliveries SET status = ? WHERE order_id = ?')->execute([$status, $orderId]);
        }
        $db->commit();
        return $changed;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function reorderIntoCart(PDO $db, int $orderId, int $userId): int
{
    $stmt = $db->prepare(
        'SELECT oi.dish_id, oi.quantity, d.is_available
         FROM order_items oi
         JOIN orders o ON o.order_id = oi.order_id
         JOIN dishes d ON d.dish_id = oi.dish_id
         WHERE oi.order_id = ? AND o.user_id = ?'
    );
    $stmt->execute([$orderId, $userId]);
    $added = 0;
    foreach ($stmt->fetchAll() as $row) {
        if (!$row['is_available']) {
            continue;
        }
        $dishId = (int) $row['dish_id'];
        $_SESSION['cart'][$dishId] = min(CART_MAX_QTY, ($_SESSION['cart'][$dishId] ?? 0) + (int) $row['quantity']);
        $added++;
    }
    normalizeCart();
    return $added;
}
