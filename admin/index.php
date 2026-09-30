<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$one = static fn (string $sql): int => (int) $db->query($sql)->fetchColumn();
$revenue = (float) $db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$stats = [
    ['Orders waiting', $one("SELECT COUNT(*) FROM orders WHERE status = 'pending'"), url('admin/orders.php?status=pending'), true],
    ['In progress', $one("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','preparing','out_for_delivery')"), url('admin/orders.php'), false],
    ['Orders today', $one('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()'), url('admin/orders.php'), false],
    ['Reported reviews', $one('SELECT COUNT(DISTINCT review_id) FROM review_reports'), url('admin/comments.php?filter=reported'), true],
    ['Unavailable dishes', $one('SELECT COUNT(*) FROM dishes WHERE is_available = 0'), url('admin/dishes.php'), false],
    ['Customers', $one("SELECT COUNT(*) FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = 'customer')"), url('admin/users.php'), false],
];

$recent = $db->query(
    'SELECT o.order_code, o.total, o.status, o.created_at, u.username
     FROM orders o JOIN users u ON u.user_id = o.user_id ORDER BY o.created_at DESC, o.order_id DESC LIMIT 8'
)->fetchAll();

$pageTitle = 'Overview';
require __DIR__ . '/../includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead('Admin', 'Revenue to date, excluding cancelled orders: ' . peso($revenue)) ?>
        <?= adminNav('index') ?>

        <div class="stat-grid">
            <?php foreach ($stats as [$label, $value, $href, $flag]): ?>
                <a class="stat<?= $flag && $value > 0 ? ' is-attention' : '' ?>" href="<?= esc($href) ?>">
                    <span class="stat-value"><?= $value ?></span>
                    <span class="stat-label"><?= esc($label) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="section-head"><h2>Latest orders</h2><a class="link" href="<?= esc(url('admin/orders.php')) ?>">All orders</a></div>
        <?php if (!$recent): ?>
            <?= emptyState('No orders yet', 'Orders will appear here as soon as customers check out.', null, null, 'package') ?>
        <?php else: ?>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Latest orders">
            <table class="data-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Placed</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $o): ?>
                    <tr>
                        <td><?= esc($o['order_code']) ?></td>
                        <td><?= esc($o['username']) ?></td>
                        <td><?= esc(peso($o['total'])) ?></td>
                        <td><?= statusPill($o['status']) ?></td>
                        <td class="nowrap"><?= esc(formatDate($o['created_at'], true)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
