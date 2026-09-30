<?php
require_once __DIR__ . '/includes/bootstrap.php';

$dishId = (int) ($_GET['id'] ?? 0);
$dish = $dishId > 0 ? (dishRows($db, 'd.dish_id = ?', [$dishId])[0] ?? null) : null;

if (!$dish) {
    renderErrorPage(404, t('err_dish_missing_title'), t('err_dish_missing_text'));
}

$reaction = null;
$isFavorite = false;
$isWishlisted = false;
$myReview = null;
if (isLoggedIn()) {
    $uid = userId();
    $r = $db->prepare('SELECT type FROM reactions WHERE user_id = ? AND dish_id = ?');
    $r->execute([$uid, $dishId]);
    $reaction = $r->fetchColumn() ?: null;
    $isFavorite = in_array($dishId, userDishIds($db, 'favorites'), true);
    $isWishlisted = in_array($dishId, userDishIds($db, 'wishlists'), true);
    $mr = $db->prepare('SELECT * FROM reviews WHERE user_id = ? AND dish_id = ?');
    $mr->execute([$uid, $dishId]);
    $myReview = $mr->fetch() ?: null;
}

$stmt = $db->prepare(
    "SELECT r.review_id, r.user_id, r.rating, r.comment, r.is_edited, r.created_at, u.username
     FROM reviews r JOIN users u ON u.user_id = r.user_id
     WHERE r.dish_id = ? AND r.status = 'visible' ORDER BY r.created_at DESC"
);
$stmt->execute([$dishId]);
$reviews = $stmt->fetchAll();

$available = (bool) $dish['is_available'];
$pageTitle = $dish['name'];
$pageDescription = dishText($dish, 'desc');

function ratingInput(string $prefix, int $selected = 0): string
{
    $out = '<fieldset class="field"><legend class="sr-only">' . te('rating_group') . '</legend>';
    $out .= '<span class="field-label-visual" aria-hidden="true"><strong>' . te('your_rating') . '</strong></span>';
    $out .= '<div class="rating-input">';
    for ($i = 5; $i >= 1; $i--) {
        $id = $prefix . '-' . $i;
        $out .= '<input type="radio" name="rating" id="' . $id . '" value="' . $i . '"' . ($i === $selected ? ' checked' : '') . '>'
            . '<label for="' . $id . '"><span class="sr-only">' . te('rating_star', $i) . '</span>' . icon('star') . '</label>';
    }
    return $out . '</div><p class="field-error" data-rating-error hidden>' . te('js_rating') . '</p></fieldset>';
}

require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <nav aria-label="<?= te('nav_menu') ?>">
            <ol class="crumbs">
                <li><a href="<?= esc(url('menu.php')) ?>"><?= te('nav_menu') ?></a></li>
                <li><a href="<?= esc(url('menu.php?category=' . rawurlencode($dish['category_slug']))) ?>"><?= esc(categoryName(['name_en' => $dish['cat_en'], 'name_fil' => $dish['cat_fil']])) ?></a></li>
            </ol>
        </nav>

        <div class="dish-detail">
            <div class="dish-detail-media"><?= dishImage($dish, true, true) ?></div>
            <div>
                <h1><?= esc($dish['name']) ?></h1>
                <div class="dish-detail-meta">
                    <?= ratingSummary($dish['avg_rating'], (int) $dish['review_count']) ?>
                    <span class="price"><?= esc(peso($dish['price'])) ?></span>
                </div>
                <p class="dish-detail-desc"><?= esc(dishText($dish, 'desc')) ?></p>

                <?php if ($dish['ingredients'] !== ''): ?>
                <dl class="detail-list">
                    <dt><?= te('ingredients') ?></dt>
                    <dd><?= esc($dish['ingredients']) ?></dd>
                </dl>
                <?php endif; ?>

                <?php if ($available): ?>
                    <button type="button" class="btn btn-primary btn-block add-btn" data-add-cart="<?= (int) $dish['dish_id'] ?>">
                        <span class="add-idle"><?= icon('plus') ?><span><?= te('add_to_cart') ?> &middot; <?= esc(peso($dish['price'])) ?></span></span>
                        <span class="add-done" aria-hidden="true"><?= icon('check') ?><span><?= te('added') ?></span></span>
                    </button>
                <?php else: ?>
                    <p class="alert alert-error" role="status"><?= icon('alert') ?><span><?= te('sold_out_note') ?></span></p>
                <?php endif; ?>

                <div class="action-row mt-6">
                    <button type="button" class="btn btn-secondary" data-react="like" data-dish-id="<?= $dishId ?>" aria-pressed="<?= $reaction === 'like' ? 'true' : 'false' ?>"><?= icon('thumb-up') ?><?= te('like') ?> <span class="count" data-count="like"><?= (int) $dish['likes'] ?></span></button>
                    <button type="button" class="btn btn-secondary" data-react="dislike" data-dish-id="<?= $dishId ?>" aria-pressed="<?= $reaction === 'dislike' ? 'true' : 'false' ?>"><?= icon('thumb-down') ?><?= te('dislike') ?> <span class="count" data-count="dislike"><?= (int) $dish['dislikes'] ?></span></button>
                    <button type="button" class="btn btn-secondary" data-toggle="favorite" data-dish-id="<?= $dishId ?>" data-label-on="<?= te('remove_favorite') ?>" data-label-off="<?= te('add_favorite') ?>" aria-pressed="<?= $isFavorite ? 'true' : 'false' ?>"><?= icon('heart') ?><span data-toggle-label><?= esc($isFavorite ? t('remove_favorite') : t('add_favorite')) ?></span></button>
                    <button type="button" class="btn btn-secondary" data-toggle="wishlist" data-dish-id="<?= $dishId ?>" data-label-on="<?= te('remove_wishlist') ?>" data-label-off="<?= te('add_wishlist') ?>" aria-pressed="<?= $isWishlisted ? 'true' : 'false' ?>"><?= icon('bookmark') ?><span data-toggle-label><?= esc($isWishlisted ? t('remove_wishlist') : t('add_wishlist')) ?></span></button>
                </div>
            </div>
        </div>

        <div class="reviews-section">
            <h2><?= te('reviews') ?> <span class="muted">(<?= (int) $dish['review_count'] ?>)</span></h2>

            <?php if (!isLoggedIn()): ?>
                <p class="mb-6"><a class="link" href="<?= esc(url('login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''))) ?>"><?= te('login_to_review') ?></a></p>
            <?php elseif (!$myReview): ?>
                <form class="panel mb-6" data-review-form="create" data-dish-id="<?= $dishId ?>" novalidate>
                    <h3><?= te('write_review') ?></h3>
                    <?= ratingInput('new') ?>
                    <?= field(['name' => 'comment', 'id' => 'new-comment', 'label' => t('comment'), 'type' => 'textarea', 'required' => true, 'maxlength' => 1000, 'placeholder' => t('comment_placeholder')]) ?>
                    <button class="btn btn-primary" type="submit"><?= te('submit_review') ?></button>
                </form>
            <?php else: ?>
                <div class="panel review-mine mb-6">
                    <div id="review-view">
                        <div class="review-head"><h3><?= te('your_review') ?></h3><span class="review-date"><?= esc(timeAgo($myReview['created_at'])) ?><?= $myReview['is_edited'] ? ', ' . te('edited') : '' ?></span></div>
                        <?= starsRow((int) $myReview['rating']) ?>
                        <p class="mt-2"><?= esc($myReview['comment']) ?></p>
                        <?php if ($myReview['status'] === 'hidden'): ?><p class="field-hint mt-2"><?= te('review_hidden_note') ?></p><?php endif; ?>
                        <div class="row mt-4">
                            <button type="button" class="btn btn-secondary btn-sm" data-review-edit-toggle><?= icon('edit') ?><?= te('edit_review') ?></button>
                            <button type="button" class="btn btn-danger btn-sm" data-review-delete="<?= (int) $myReview['review_id'] ?>" data-confirm="<?= te('delete_review_confirm') ?>" data-confirm-label="<?= te('delete_review') ?>"><?= icon('trash') ?><?= te('delete_review') ?></button>
                        </div>
                    </div>
                    <form id="review-edit" hidden data-review-form="edit" data-review-id="<?= (int) $myReview['review_id'] ?>" novalidate>
                        <h3><?= te('edit_review') ?></h3>
                        <?= ratingInput('edit', (int) $myReview['rating']) ?>
                        <?= field(['name' => 'comment', 'id' => 'edit-comment', 'label' => t('comment'), 'type' => 'textarea', 'required' => true, 'maxlength' => 1000, 'value' => $myReview['comment']]) ?>
                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit"><?= te('save_review') ?></button>
                            <button class="btn btn-ghost" type="button" data-review-edit-toggle><?= te('cancel') ?></button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php $others = array_filter($reviews, static fn ($rev) => (int) $rev['user_id'] !== userId()); ?>
            <?php if (!$reviews): ?>
                <p class="muted"><?= te('no_reviews') ?></p>
            <?php endif; ?>
            <?php foreach ($others as $rev): ?>
                <article class="review">
                    <div class="review-head">
                        <span class="review-user"><?= esc($rev['username']) ?></span>
                        <span class="review-date"><?= esc(timeAgo($rev['created_at'])) ?><?= $rev['is_edited'] ? ', ' . te('edited') : '' ?></span>
                    </div>
                    <?= starsRow((int) $rev['rating']) ?><span class="sr-only"><?= te('rating_star', (int) $rev['rating']) ?></span>
                    <p><?= esc($rev['comment']) ?></p>
                    <?php if (isLoggedIn()): ?>
                    <div class="review-actions">
                        <button type="button" class="btn btn-ghost btn-sm" data-report-review="<?= (int) $rev['review_id'] ?>" data-done-label="<?= te('reported') ?>"><?= icon('flag') ?><span data-report-label><?= te('report') ?></span></button>
                    </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
