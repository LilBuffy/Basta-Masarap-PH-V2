<?php
require_once __DIR__ . '/includes/bootstrap.php';

$categories = $db->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll();
$validSlugs = array_column($categories, 'slug');
$sorts = ['popular', 'price_low', 'price_high', 'rating', 'newest'];

$search = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 80);
$category = (string) ($_GET['category'] ?? '');
$category = in_array($category, $validSlugs, true) ? $category : '';
$sort = (string) ($_GET['sort'] ?? 'popular');
$sort = in_array($sort, $sorts, true) ? $sort : 'popular';

$dishes = dishRows($db);
foreach ($dishes as $i => &$dish) {
    $dish['rank'] = $i;
}
unset($dish);

$sorter = [
    'popular' => static fn ($a, $b) => $a['rank'] <=> $b['rank'],
    'price_low' => static fn ($a, $b) => [(float) $a['price'], $a['rank']] <=> [(float) $b['price'], $b['rank']],
    'price_high' => static fn ($a, $b) => [(float) $b['price'], $a['rank']] <=> [(float) $a['price'], $b['rank']],
    'rating' => static fn ($a, $b) => [(float) $b['avg_rating'], (int) $b['review_count'], $a['rank']] <=> [(float) $a['avg_rating'], (int) $a['review_count'], $b['rank']],
    'newest' => static fn ($a, $b) => [strtotime($b['created_at']), $a['rank']] <=> [strtotime($a['created_at']), $b['rank']],
];
usort($dishes, $sorter[$sort]);

$needle = foldText($search);

$saved = userDishIds($db, 'wishlists');
$visibleCount = 0;
$counts = array_count_values(array_column($dishes, 'category_slug'));

$pageTitle = t('nav_menu');
$pageDescription = t('menu_sub');
require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead(t('nav_menu'), t('menu_sub')) ?>

        <form class="menu-tools" method="get" action="<?= esc(url('menu.php')) ?>" role="search">
            <div class="search-box">
                <?= icon('search') ?>
                <label class="sr-only" for="menu-search"><?= te('search_label') ?></label>
                <input class="input" type="search" id="menu-search" name="search" value="<?= esc($search) ?>" placeholder="<?= te('search_placeholder') ?>" autocomplete="off" maxlength="80">
            </div>
            <div>
                <label class="sr-only" for="menu-sort"><?= te('sort_label') ?></label>
                <select class="input" id="menu-sort" name="sort">
                    <?php foreach ($sorts as $key): ?>
                        <option value="<?= $key ?>"<?= $sort === $key ? ' selected' : '' ?>><?= te('sort_' . $key) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= esc($category) ?>"><?php endif; ?>
            <noscript><button class="btn btn-primary" type="submit"><?= te('search_label') ?></button></noscript>
        </form>

        <div class="chips menu-chips" role="group" aria-label="<?= te('section_categories') ?>">
            <a class="chip<?= $category === '' ? ' is-active' : '' ?>" href="<?= esc(url('menu.php')) ?>" data-category-chip=""><?= te('all_categories') ?> <span class="chip-count"><?= count($dishes) ?></span></a>
            <?php foreach ($categories as $cat): if (empty($counts[$cat['slug']])) continue; ?>
                <a class="chip<?= $category === $cat['slug'] ? ' is-active' : '' ?>" href="<?= esc(url('menu.php?category=' . rawurlencode($cat['slug']))) ?>" data-category-chip="<?= esc($cat['slug']) ?>"><?= esc(categoryName($cat)) ?> <span class="chip-count"><?= (int) $counts[$cat['slug']] ?></span></a>
            <?php endforeach; ?>
        </div>

        <p class="results-line" id="results-line" aria-live="polite" data-one="<?= te('results_one') ?>" data-many="<?= te('results_many') ?>"></p>

        <ul class="dish-grid" id="dish-grid">
            <?php foreach ($dishes as $dish):
                $matches = ($category === '' || $dish['category_slug'] === $category)
                    && ($needle === '' || str_contains(foldText($dish['name'] . ' ' . $dish['desc_en'] . ' ' . $dish['desc_fil'] . ' ' . $dish['ingredients']), $needle));
                $visibleCount += $matches ? 1 : 0;
                $attrs = 'data-category="' . esc($dish['category_slug']) . '"'
                    . ' data-search="' . esc($dish['name'] . ' ' . $dish['desc_en'] . ' ' . $dish['desc_fil'] . ' ' . $dish['ingredients']) . '"'
                    . ' data-price="' . esc((string) $dish['price']) . '" data-rank="' . (int) $dish['rank'] . '"'
                    . ' data-rating="' . esc((string) ($dish['avg_rating'] ?? 0)) . '" data-reviews="' . (int) $dish['review_count'] . '"'
                    . ' data-created="' . (int) strtotime($dish['created_at']) . '"';
                echo dishCard($dish, ['toggle' => 'wishlist', 'active' => in_array((int) $dish['dish_id'], $saved, true), 'hidden' => !$matches, 'attrs' => $attrs]);
            endforeach; ?>
        </ul>

        <div id="menu-empty"<?= $visibleCount > 0 ? ' hidden' : '' ?>>
            <div class="empty-state">
                <?= icon('search', 'empty-icon') ?>
                <h2><?= te('no_results_title') ?></h2>
                <p><?= te('no_results_text') ?></p>
                <a class="btn btn-secondary" id="menu-reset" href="<?= esc(url('menu.php')) ?>"><?= te('clear_filters') ?></a>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
