<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$filter = (string) ($_GET['status'] ?? '');
$filter = in_array($filter, ORDER_STATUSES, true) ? $filter : '';

if (isPost()) {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        flash('error', t('err_csrf_form'));
    } elseif ($orderId < 1 || !in_array($newStatus, ORDER_STATUSES, true)) {
        flash('error', t('err_invalid'));
    } else {
        $exists = $db->prepare('SELECT 1 FROM orders WHERE order_id = ?');
        $exists->execute([$orderId]);
        if ($exists->fetchColumn()) {
            setOrderStatus($db, $orderId, $newStatus);
            flash('success', 'Order marked as ' . mb_strtolower(statusLabel($newStatus)) . '.');
        } else {
            flash('error', 'That order no longer exists.');
        }
    }
    redirect('admin/orders.php' . ($filter ? '?status=' . $filter : ''));
}

$counts = array_column($db->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status')->fetchAll(), 'n', 'status');
$perPage = 12;
$total = (int) ($filter ? ($counts[$filter] ?? 0) : array_sum($counts));
$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

$stmt = $db->prepare(
    'SELECT o.*, u.username, u.full_name, del.delivery_code
     FROM orders o JOIN users u ON u.user_id = o.user_id LEFT JOIN deliveries del ON del.order_id = o.order_id
     ' . ($filter ? 'WHERE o.status = ?' : '') . ' ORDER BY o.created_at DESC, o.order_id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
);
$stmt->execute($filter ? [$filter] : []);
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

$pageTitle = 'Orders';
require __DIR__ . '/../includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead('Admin') ?>
        <?= adminNav('orders') ?>

        <div class="chips mb-6" role="group" aria-label="Filter by status">
            <a class="chip<?= $filter === '' ? ' is-active' : '' ?>" href="<?= esc(url('admin/orders.php')) ?>">All <span class="chip-count"><?= array_sum($counts) ?></span></a>
            <?php foreach (ORDER_STATUSES as $s): ?>
                <a class="chip<?= $filter === $s ? ' is-active' : '' ?>" href="<?= esc(url('admin/orders.php?status=' . $s)) ?>"><?= esc(statusLabel($s)) ?> <span class="chip-count"><?= (int) ($counts[$s] ?? 0) ?></span></a>
            <?php endforeach; ?>
        </div>

        <?php if (!$orders): ?>
            <?= emptyState($filter ? 'No ' . mb_strtolower(statusLabel($filter)) . ' orders' : 'No orders yet', 'Nothing to show here right now.', $filter ? url('admin/orders.php') : null, $filter ? 'Show all orders' : null, 'package') ?>
        <?php endif; ?>

        <?php foreach ($orders as $order): ?>
        <article class="admin-order">
            <div class="admin-order-top">
                <div>
                    <p class="order-code"><?= esc($order['order_code']) ?> <?= statusPill($order['status']) ?></p>
                    <p class="order-meta"><?= esc(formatDate($order['created_at'], true)) ?><?= $order['delivery_code'] ? ' &middot; ' . esc($order['delivery_code']) : '' ?></p>
                    <p class="detail-block mt-2"><?= esc($order['full_name']) ?> (@<?= esc($order['username']) ?>) &middot; <?= esc($order['contact_number']) ?></p>
                    <p class="detail-block"><?= esc($order['delivery_address']) ?></p>
                </div>
                <form class="admin-order-form" method="post" data-once>
                    <?= csrfField() ?><input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                    <label class="sr-only" for="status-<?= (int) $order['order_id'] ?>">Status for <?= esc($order['order_code']) ?></label>
                    <select class="input" id="status-<?= (int) $order['order_id'] ?>" name="status">
                        <?php foreach (ORDER_STATUSES as $s): ?>
                            <option value="<?= $s ?>"<?= $order['status'] === $s ? ' selected' : '' ?>><?= esc(statusLabel($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-sm" type="submit">Update</button>
                </form>
            </div>
            <ul class="order-items">
                <?php foreach ($itemsByOrder[$order['order_id']] ?? [] as $it): ?>
                    <li><span><?= (int) $it['quantity'] ?> &times; <?= esc($it['dish_name']) ?></span><span><?= esc(peso($it['line_total'])) ?></span></li>
                <?php endforeach; ?>
                <li><span class="muted">Delivery fee</span><span class="muted"><?= esc(peso($order['delivery_fee'])) ?></span></li>
            </ul>
            <p class="order-total text-right">Total <?= esc(peso($order['total'])) ?></p>
        </article>
        <?php endforeach; ?>

        <?= pagination($page, $pages, url('admin/orders.php'), $filter ? ['status' => $filter] : []) ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
