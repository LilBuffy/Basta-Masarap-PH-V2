<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$uid = userId();

if (isPost()) {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        flash('error', t('err_csrf_form'));
    } elseif ($orderId > 0 && $action === 'cancel') {
        $own = $db->prepare('SELECT 1 FROM orders WHERE order_id = ? AND user_id = ?');
        $own->execute([$orderId, $uid]);
        if ($own->fetchColumn() && setOrderStatus($db, $orderId, 'cancelled', 'pending')) {
            flash('success', t('order_cancelled'));
        } else {
            flash('error', t('order_cannot_cancel'));
        }
    } elseif ($orderId > 0 && $action === 'reorder') {
        if (reorderIntoCart($db, $orderId, $uid) > 0) {
            flash('success', t('reorder_done'));
            redirect('cart.php');
        }
        flash('error', t('reorder_none'));
    }
    redirect('orders.php' . (isset($_GET['page']) ? '?page=' . (int) $_GET['page'] : ''));
}

$perPage = 8;
$total = $db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$total->execute([$uid]);
$total = (int) $total->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

$stmt = $db->prepare(
    'SELECT o.*, del.delivery_code FROM orders o LEFT JOIN deliveries del ON del.order_id = o.order_id
     WHERE o.user_id = ? ORDER BY o.created_at DESC, o.order_id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
);
$stmt->execute([$uid]);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'order_id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = $db->prepare("SELECT order_id, dish_name, quantity, line_total FROM order_items WHERE order_id IN ({$in}) ORDER BY order_item_id");
    $itemStmt->execute($ids);
    foreach ($itemStmt->fetchAll() as $item) {
        $itemsByOrder[$item['order_id']][] = $item;
    }
}

$placed = (string) ($_GET['placed'] ?? '');
$placedValid = $placed !== '' && in_array($placed, array_column($orders, 'order_code'), true);

$pageTitle = t('order_history');
require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead(t('order_history'), t('order_history_sub')) ?>
        <?= accountNav('orders') ?>

        <?php if ($placedValid): ?>
            <div class="alert alert-success" role="status"><?= icon('check') ?><p><strong><?= te('order_placed_title') ?>.</strong> <?= te('order_placed_text', $placed) ?></p></div>
        <?php endif; ?>

        <?php if (!$orders): ?>
            <?= emptyState(t('no_orders'), t('no_orders_text'), url('menu.php'), t('hero_cta'), 'package') ?>
        <?php endif; ?>

        <?php foreach ($orders as $order):
            $status = $order['status'];
            $step = array_search($status, ORDER_FLOW, true);
            $isNew = $placedValid && $order['order_code'] === $placed;
        ?>
        <article class="order-card<?= $isNew ? ' is-new' : '' ?>">
            <div class="order-top">
                <div>
                    <p class="order-code"><?= te('order_id') ?> <?= esc($order['order_code']) ?></p>
                    <p class="order-meta"><?= te('placed_on', formatDate($order['created_at'], true)) ?><?= $order['delivery_code'] ? ' &middot; ' . esc($order['delivery_code']) : '' ?></p>
                </div>
                <?= statusPill($status) ?>
            </div>

            <?php if ($status !== 'cancelled'): ?>
            <ol class="tracker" aria-label="<?= esc(statusLabel($status)) ?>">
                <?php foreach (ORDER_FLOW as $i => $flowStatus): ?>
                    <li class="<?= $i < $step ? 'is-done' : ($i === $step ? 'is-current' : '') ?>"<?= $i === $step ? ' aria-current="step"' : '' ?>><?= esc(statusLabel($flowStatus)) ?></li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>

            <ul class="order-items">
                <?php foreach ($itemsByOrder[$order['order_id']] ?? [] as $item): ?>
                    <li><span><?= (int) $item['quantity'] ?> &times; <?= esc($item['dish_name']) ?></span><span><?= esc(peso($item['line_total'])) ?></span></li>
                <?php endforeach; ?>
                <li><span class="muted"><?= te('delivery_fee') ?></span><span class="muted"><?= esc(peso($order['delivery_fee'])) ?></span></li>
            </ul>

            <p class="order-meta mb-4"><?= te('deliver_to') ?>: <?= esc($order['delivery_address']) ?></p>

            <div class="order-foot">
                <span class="order-total"><?= te('total') ?> <?= esc(peso($order['total'])) ?></span>
                <div class="row">
                    <?php if ($status === 'pending'): ?>
                    <form method="post" data-once<?= confirmAttrs(t('cancel_order_confirm'), t('cancel_order')) ?>>
                        <?= csrfField() ?><input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>"><input type="hidden" name="action" value="cancel">
                        <button class="btn btn-danger btn-sm" type="submit"><?= te('cancel_order') ?></button>
                    </form>
                    <?php endif; ?>
                    <form method="post" data-once>
                        <?= csrfField() ?><input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>"><input type="hidden" name="action" value="reorder">
                        <button class="btn btn-secondary btn-sm" type="submit"><?= te('reorder') ?></button>
                    </form>
                </div>
            </div>
        </article>
        <?php endforeach; ?>

        <?= pagination($page, $pages, url('orders.php')) ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
