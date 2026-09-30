<?php
$settings = siteSettings();
$footerCategories = [];
try {
    $footerCategories = Database::connect()->query('SELECT slug, name_en, name_fil FROM categories ORDER BY sort_order')->fetchAll();
} catch (Throwable $e) {
    error_log('Footer categories failed: ' . $e->getMessage());
}
$hours = siteHours();
?>
</main>
<?php if (empty($hideCartBar)): ?>
<a class="cart-bar" href="<?= esc(url('cart.php')) ?>" data-cart-bar<?= ($cartCount ?? 0) > 0 ? '' : ' hidden' ?>>
    <span class="cart-bar-label"><?= icon('cart') ?><?= te('cart_bar_view') ?></span>
    <span class="cart-bar-count" data-cart-bar-count><?= esc(itemsLabel((int) ($cartCount ?? 0))) ?></span>
</a>
<?php endif; ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-about">
            <p class="footer-brand"><?= esc(siteName()) ?></p>
            <p><?= te('footer_tagline') ?></p>
        </div>
        <?php if ($footerCategories): ?>
        <nav aria-label="<?= te('footer_menu') ?>">
            <h2 class="footer-title"><?= te('nav_menu') ?></h2>
            <ul>
                <?php foreach ($footerCategories as $cat): ?>
                    <li><a href="<?= esc(url('menu.php?category=' . rawurlencode($cat['slug']))) ?>"><?= esc(categoryName($cat)) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php if ($settings['address'] !== '' || $settings['contact_phone'] !== '' || $settings['contact_email'] !== '' || $hours !== ''): ?>
        <div>
            <h2 class="footer-title"><?= te('footer_contact') ?></h2>
            <ul>
                <?php if ($settings['address'] !== ''): ?><li><?= esc($settings['address']) ?></li><?php endif; ?>
                <?php if ($hours !== ''): ?><li><?= esc($hours) ?></li><?php endif; ?>
                <?php if ($settings['contact_phone'] !== ''): ?><li><a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $settings['contact_phone'])) ?>"><?= esc($settings['contact_phone']) ?></a></li><?php endif; ?>
                <?php if ($settings['contact_email'] !== ''): ?><li><a href="mailto:<?= esc($settings['contact_email']) ?>"><?= esc($settings['contact_email']) ?></a></li><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= esc(siteName()) ?>. <?= te('footer_rights') ?></p>
    </div>
</footer>
</body>
</html>
