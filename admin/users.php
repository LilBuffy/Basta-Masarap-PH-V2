<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 80);

if (isPost()) {
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        flash('error', t('err_csrf_form'));
    } elseif ($targetId === userId()) {
        flash('error', 'You cannot change your own account here.');
    } else {
        $target = $db->prepare('SELECT u.status, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?');
        $target->execute([$targetId]);
        $row = $target->fetch();
        if (!$row) {
            flash('error', 'That user no longer exists.');
        } elseif ($row['role_name'] === 'admin') {
            flash('error', 'Admin accounts cannot be changed from this page.');
        } elseif ($action === 'toggle_status') {
            $db->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE user_id = ?")->execute([$targetId]);
            flash('success', $row['status'] === 'active' ? 'Account deactivated. They are logged out on their next click.' : 'Account reactivated.');
        } elseif ($action === 'delete') {
            $orders = $db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
            $orders->execute([$targetId]);
            if ((int) $orders->fetchColumn() > 0) {
                flash('error', 'This customer has orders, so the account cannot be deleted. Deactivate it instead.');
            } else {
                $db->prepare('DELETE FROM users WHERE user_id = ?')->execute([$targetId]);
                flash('success', 'Account deleted.');
            }
        }
    }
    redirect('admin/users.php' . ($search !== '' ? '?search=' . rawurlencode($search) : ''));
}

$where = '1=1';
$params = [];
if ($search !== '') {
    $where = "(u.username LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\' OR u.full_name LIKE ? ESCAPE '\\\\')";
    $params = array_fill(0, 3, likePattern($search));
}
$perPage = 20;
$count = $db->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
$count->execute($params);
$pages = max(1, (int) ceil((int) $count->fetchColumn() / $perPage));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

$stmt = $db->prepare(
    "SELECT u.*, r.role_name, (SELECT COUNT(*) FROM orders WHERE user_id = u.user_id) AS order_count
     FROM users u JOIN roles r ON r.role_id = u.role_id WHERE {$where}
     ORDER BY u.created_at DESC, u.user_id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Users';
require __DIR__ . '/../includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead('Admin') ?>
        <?= adminNav('users') ?>

        <form class="menu-tools mb-6" method="get" role="search">
            <div class="search-box">
                <?= icon('search') ?>
                <label class="sr-only" for="user-search">Search users</label>
                <input class="input" type="search" id="user-search" name="search" value="<?= esc($search) ?>" placeholder="Search by name, username, or email" maxlength="80">
            </div>
            <button class="btn btn-secondary" type="submit">Search</button>
        </form>

        <?php if (!$users): ?>
            <?= emptyState('No users found', $search !== '' ? 'Nobody matches that search.' : 'No accounts yet.', $search !== '' ? url('admin/users.php') : null, $search !== '' ? 'Clear search' : null, 'user') ?>
        <?php else: ?>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Users">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Orders</th><th>Status</th><th>Joined</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= esc($u['full_name']) ?></td>
                        <td>@<?= esc($u['username']) ?></td>
                        <td><?= esc($u['email']) ?></td>
                        <td><?= esc(ucfirst($u['role_name'])) ?></td>
                        <td><?= (int) $u['order_count'] ?></td>
                        <td><span class="status status-<?= esc($u['status']) ?>"><?= esc(ucfirst($u['status'])) ?></span></td>
                        <td class="nowrap"><?= esc(formatDate($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['role_name'] !== 'admin'): ?>
                            <div class="cell-actions">
                                <form method="post" data-once<?= $u['status'] === 'active' ? confirmAttrs('Deactivate ' . $u['username'] . '? They will not be able to log in.', 'Deactivate') : '' ?>>
                                    <?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>"><input type="hidden" name="action" value="toggle_status">
                                    <button class="btn btn-secondary btn-sm" type="submit"><?= $u['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                                </form>
                                <form method="post" data-once<?= confirmAttrs('Delete ' . $u['username'] . ' permanently?', 'Delete') ?>>
                                    <?= csrfField() ?><input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>"><input type="hidden" name="action" value="delete">
                                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?= pagination($page, $pages, url('admin/users.php'), $search !== '' ? ['search' => $search] : []) ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
