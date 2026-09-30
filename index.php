<?php
require_once __DIR__ . '/includes/bootstrap.php';

$featured = dishRows($db, 'd.is_featured = 1 AND d.is_available = 1', [], 'd.dish_id DESC', 6);
$popular = dishRows($db, 'd.is_popular = 1 AND d.is_available = 1', [], 'd.dish_id DESC', 6);
$categories = $db->query(
    'SELECT c.*, (SELECT COUNT(*) FROM dishes d WHERE d.category_id = c.category_id AND d.is_available = 1) AS dish_count
     FROM categories c ORDER BY c.sort_order'
)->fetchAll();
$reviews = $db->query(
    "SELECT r.rating, r.comment, u.username, d.name AS dish_name, d.dish_id
     FROM reviews r JOIN users u ON u.user_id = r.user_id JOIN dishes d ON d.dish_id = r.dish_id
     WHERE r.status = 'visible' ORDER BY r.created_at DESC LIMIT 3"
)->fetchAll();

$heroDish = null;
foreach ($featured as $candidate) {
    if (dishImageUrl($candidate)) {
        $heroDish = $candidate;
        break;
    }
}
$saved = userDishIds($db, 'wishlists');
$hours = siteHours();

$pageTitle = null;
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <h1><?= esc(mb_strtoupper(siteName())) ?></h1>
            <p class="hero-lead"><?= esc(siteTagline()) ?></p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="<?= esc(url('menu.php')) ?>"><?= te('hero_cta') ?></a>
                <a class="btn btn-secondary" href="<?= esc(url('menu.php')) ?>"><?= te('hero_view_menu') ?></a>
            </div>
            <div class="hero-facts">
                <?php if ($hours !== ''): ?><p><?= esc($hours) ?></p><?php endif; ?>
                <p><?= te('hero_fee', peso(deliveryFee())) ?></p>
            </div>
        </div>
        <?php if ($heroDish): ?>
        <figure class="hero-dish">
            <div class="hero-dish-media"><?= dishImage($heroDish, true, true) ?></div>
            <figcaption>
                <span class="hero-dish-name"><a href="<?= esc(url('dish.php?id=' . (int) $heroDish['dish_id'])) ?>"><?= esc($heroDish['name']) ?></a></span>
                <span class="hero-dish-price"><?= esc(peso($heroDish['price'])) ?></span>
            </figcaption>
        </figure>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="mb-6"><?= te('section_categories') ?></h2>
        <div class="chips">
            <?php foreach ($categories as $cat): if ((int) $cat['dish_count'] === 0) continue; ?>
                <a class="chip" href="<?= esc(url('menu.php?category=' . rawurlencode($cat['slug']))) ?>"><?= esc(categoryName($cat)) ?> <span class="chip-count"><?= (int) $cat['dish_count'] ?></span></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($featured): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <h2><?= te('section_featured') ?></h2>
            <a class="link" href="<?= esc(url('menu.php')) ?>"><?= te('view_all') ?></a>
        </div>
        <?= dishGrid($featured, $saved) ?>
    </div>
</section>
<?php endif; ?>

<section class="story">
    <div class="container story-inner">
        <h2><?= te('about_title') ?></h2>
        <p><?= te('about_body') ?></p>
    </div>
</section>

<?php if ($popular): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <h2><?= te('section_popular') ?></h2>
            <a class="link" href="<?= esc(url('menu.php')) ?>"><?= te('view_all') ?></a>
        </div>
        <?= dishGrid($popular, $saved) ?>
    </div>
</section>
<?php endif; ?>

<?php if ($reviews): ?>
<section class="section">
    <div class="container">
        <h2 class="mb-6"><?= te('section_reviews') ?></h2>
        <div class="review-cards">
            <?php foreach ($reviews as $rev): ?>
                <figure class="review-card">
                    <?= starsRow((int) $rev['rating']) ?>
                    <blockquote><?= esc($rev['comment']) ?></blockquote>
                    <figcaption><?= esc($rev['username']) ?>, <?= te('review_on', $rev['dish_name']) ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
