<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$filter = (string) ($_GET['filter'] ?? '');
$filter = in_array($filter, ['reported', 'hidden'], true) ? $filter : '';

if (isPost()) {
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        flash('error', t('err_csrf_form'));
    } elseif ($reviewId < 1) {
        flash('error', t('err_invalid'));
    } elseif ($action === 'hide' || $action === 'unhide') {
        $db->prepare('UPDATE reviews SET status = ? WHERE review_id = ?')->execute([$action === 'hide' ? 'hidden' : 'visible', $reviewId]);
        flash('success', $action === 'hide' ? 'Review hidden from the site.' : 'Review is visible again.');
    } elseif ($action === 'dismiss') {
        $db->prepare('DELETE FROM review_reports WHERE review_id = ?')->execute([$reviewId]);
        flash('success', 'Reports dismissed.');
    } elseif ($action === 'delete') {
        $db->prepare('DELETE FROM reviews WHERE review_id = ?')->execute([$reviewId]);
        flash('success', 'Review deleted.');
    }
    redirect('admin/comments.php' . ($filter ? '?filter=' . $filter : ''));
}

$where = $filter === 'reported' ? 'WHERE EXISTS (SELECT 1 FROM review_reports rr WHERE rr.review_id = r.review_id)' : ($filter === 'hidden' ? "WHERE r.status = 'hidden'" : '');
$perPage = 15;
$count = (int) $db->query("SELECT COUNT(*) FROM reviews r {$where}")->fetchColumn();
$pages = max(1, (int) ceil($count / $perPage));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

$reviews = $db->query(
    "SELECT r.*, u.username, d.name AS dish_name,
        (SELECT COUNT(*) FROM review_reports WHERE review_id = r.review_id) AS report_count
     FROM reviews r JOIN users u ON u.user_id = r.user_id JOIN dishes d ON d.dish_id = r.dish_id
     {$where} ORDER BY report_count DESC, r.created_at DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
)->fetchAll();

$reportedTotal = (int) $db->query('SELECT COUNT(DISTINCT review_id) FROM review_reports')->fetchColumn();

$pageTitle = 'Reviews';
require __DIR__ . '/../includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead('Admin') ?>
        <?= adminNav('comments') ?>

        <div class="chips mb-6" role="group" aria-label="Filter reviews">
            <a class="chip<?= $filter === '' ? ' is-active' : '' ?>" href="<?= esc(url('admin/comments.php')) ?>">All</a>
            <a class="chip<?= $filter === 'reported' ? ' is-active' : '' ?>" href="<?= esc(url('admin/comments.php?filter=reported')) ?>">Reported <span class="chip-count"><?= $reportedTotal ?></span></a>
            <a class="chip<?= $filter === 'hidden' ? ' is-active' : '' ?>" href="<?= esc(url('admin/comments.php?filter=hidden')) ?>">Hidden</a>
        </div>

        <?php if (!$reviews): ?>
            <?= emptyState($filter === 'reported' ? 'No reported reviews' : ($filter === 'hidden' ? 'No hidden reviews' : 'No reviews yet'), 'Nothing needs your attention here.', $filter ? url('admin/comments.php') : null, $filter ? 'Show all reviews' : null, 'check') ?>
        <?php else: ?>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Reviews">
            <table class="data-table">
                <thead><tr><th>Dish</th><th>Author</th><th>Rating</th><th>Comment</th><th>Reports</th><th>Status</th><th>Posted</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td><?= esc($r['dish_name']) ?></td>
                        <td>@<?= esc($r['username']) ?></td>
                        <td class="nowrap"><?= (int) $r['rating'] ?> / 5</td>
                        <td class="cell-wrap"><?= esc($r['comment']) ?></td>
                        <td><?= (int) $r['report_count'] ?: '0' ?></td>
                        <td><span class="status status-<?= esc($r['status']) ?>"><?= esc(ucfirst($r['status'])) ?></span></td>
                        <td class="nowrap"><?= esc(formatDate($r['created_at'])) ?></td>
                        <td>
                            <div class="cell-actions">
                                <form method="post" data-once>
                                    <?= csrfField() ?><input type="hidden" name="review_id" value="<?= (int) $r['review_id'] ?>"><input type="hidden" name="action" value="<?= $r['status'] === 'visible' ? 'hide' : 'unhide' ?>">
                                    <button class="btn btn-secondary btn-sm" type="submit"><?= $r['status'] === 'visible' ? 'Hide' : 'Unhide' ?></button>
                                </form>
                                <?php if ((int) $r['report_count'] > 0): ?>
                                <form method="post" data-once>
                                    <?= csrfField() ?><input type="hidden" name="review_id" value="<?= (int) $r['review_id'] ?>"><input type="hidden" name="action" value="dismiss">
                                    <button class="btn btn-ghost btn-sm" type="submit">Dismiss reports</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" data-once<?= confirmAttrs('Delete this review permanently?', 'Delete') ?>>
                                    <?= csrfField() ?><input type="hidden" name="review_id" value="<?= (int) $r['review_id'] ?>"><input type="hidden" name="action" value="delete">
                                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?= pagination($page, $pages, url('admin/comments.php'), $filter ? ['filter' => $filter] : []) ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
