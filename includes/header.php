<?php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$currentPage = basename($scriptName);
$inAdmin = str_contains($scriptName, '/admin/');
$cartCount = cartCount();
$viewer = currentUser();
$htmlLang = currentLang();
$metaTitle = isset($pageTitle) && $pageTitle ? $pageTitle . ' | ' . siteName() : siteName() . ' | ' . t('meta_title_suffix');
$metaDescription = $pageDescription ?? siteTagline();
$hideCartBar = $inAdmin || in_array($currentPage, ['cart.php', 'checkout.php', 'login.php', 'register.php'], true);
$jsStrings = [
    'network' => t('js_network'),
    'server' => t('err_server'),
    'required' => t('js_required'),
    'email' => t('js_email'),
    'tooShort' => t('js_too_short'),
    'mismatch' => t('js_mismatch'),
    'rating' => t('js_rating'),
    'cancel' => t('cancel'),
    'confirm' => t('confirm'),
    'logIn' => t('nav_login'),
    'cart' => t('nav_cart'),
    'itemOne' => t('item_one'),
    'itemMany' => t('item_many'),
];
$navLinks = [
    'index.php' => [url(''), t('nav_home')],
    'menu.php' => [url('menu.php'), t('nav_menu')],
];
if (isAdmin()) {
    $navLinks['admin'] = [url('admin/index.php'), t('nav_admin')];
}
$accountLinks = [
    'profile.php' => [url('profile.php'), t('nav_profile')],
    'orders.php' => [url('orders.php'), t('nav_orders')],
    'favorites.php' => [url('favorites.php'), t('nav_favorites')],
    'wishlist.php' => [url('wishlist.php'), t('nav_wishlist')],
];
?>
<!DOCTYPE html>
<html lang="<?= $htmlLang === 'fil' ? 'fil' : 'en' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#111111">
<meta name="description" content="<?= esc($metaDescription) ?>">
<meta name="csrf-token" content="<?= esc(csrfToken()) ?>">
<title><?= esc($metaTitle) ?></title>
<link rel="icon" type="image/x-icon" href="<?= esc(asset('../favicon.ico')) ?>">
<link rel="preload" href="<?= esc(asset('fonts/nunito-sans-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= esc(asset('fonts/young-serif-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= esc(asset('css/style.css')) ?>">
<script src="<?= esc(asset('js/app.js')) ?>" defer></script>
</head>
<body class="<?= $inAdmin ? 'is-admin' : '' ?>" data-base="<?= esc(basePath()) ?>" data-login-url="<?= esc(url('login.php')) ?>">
<a class="skip-link" href="#main"><?= te('skip_to_content') ?></a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= esc(url('')) ?>"><?= esc(siteName()) ?></a>

        <nav class="site-nav" id="site-nav" aria-label="<?= te('nav_main') ?>">
            <ul class="nav-list">
                <?php foreach ($navLinks as $navFile => [$navHref, $navLabel]): ?>
                    <?php $navCurrent = $navFile === 'admin' ? $inAdmin : (!$inAdmin && $navFile === $currentPage); ?>
                    <li><a href="<?= esc($navHref) ?>"<?= $navCurrent ? ' class="is-active" aria-current="page"' : '' ?>><?= esc($navLabel) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="nav-sheet-extra">
                <?php if ($viewer): ?>
                    <p class="nav-sheet-title"><?= esc($viewer['full_name']) ?></p>
                    <ul class="nav-list">
                        <?php foreach ($accountLinks as $navFile => [$navHref, $navLabel]): ?>
                            <li><a href="<?= esc($navHref) ?>"<?= $navFile === $currentPage ? ' class="is-active" aria-current="page"' : '' ?>><?= esc($navLabel) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="post" action="<?= esc(url('logout.php')) ?>">
                        <?= csrfField() ?>
                        <button class="btn btn-secondary btn-block" type="submit"><?= te('nav_logout') ?></button>
                    </form>
                <?php else: ?>
                    <div class="nav-sheet-auth">
                        <a class="btn btn-secondary" href="<?= esc(url('login.php')) ?>"><?= te('nav_login') ?></a>
                        <a class="btn btn-primary" href="<?= esc(url('register.php')) ?>"><?= te('nav_register') ?></a>
                    </div>
                <?php endif; ?>
                <div class="lang-switch" role="group" aria-label="<?= te('language') ?>">
                    <a href="<?= esc(langUrl('en')) ?>" lang="en" hreflang="en"<?= $htmlLang === 'en' ? ' class="is-active" aria-current="true"' : '' ?>>EN</a>
                    <a href="<?= esc(langUrl('fil')) ?>" lang="fil" hreflang="fil"<?= $htmlLang === 'fil' ? ' class="is-active" aria-current="true"' : '' ?>>FIL</a>
                </div>
            </div>
        </nav>

        <div class="header-actions">
            <div class="lang-switch header-lang" role="group" aria-label="<?= te('language') ?>">
                <a href="<?= esc(langUrl('en')) ?>" lang="en" hreflang="en"<?= $htmlLang === 'en' ? ' class="is-active" aria-current="true"' : '' ?>>EN</a>
                <a href="<?= esc(langUrl('fil')) ?>" lang="fil" hreflang="fil"<?= $htmlLang === 'fil' ? ' class="is-active" aria-current="true"' : '' ?>>FIL</a>
            </div>
            <a class="icon-btn cart-link" href="<?= esc(url('cart.php')) ?>" data-cart-link aria-label="<?= esc(t('nav_cart') . ': ' . itemsLabel($cartCount)) ?>">
                <?= icon('cart') ?>
                <span class="cart-count" data-cart-count<?= $cartCount > 0 ? '' : ' hidden' ?>><?= $cartCount ?></span>
            </a>
            <?php if ($viewer): ?>
                <div class="user-menu" data-menu>
                    <button class="user-menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-haspopup="true">
                        <span class="avatar" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($viewer['full_name'], 0, 1))) ?></span>
                        <span class="user-menu-name"><?= esc(strtok($viewer['full_name'], ' ') ?: $viewer['username']) ?></span>
                        <?= icon('chevron', 'chevron') ?>
                    </button>
                    <div class="menu-panel" data-menu-panel hidden>
                        <?php foreach ($accountLinks as $navFile => [$navHref, $navLabel]): ?>
                            <a href="<?= esc($navHref) ?>"><?= esc($navLabel) ?></a>
                        <?php endforeach; ?>
                        <form method="post" action="<?= esc(url('logout.php')) ?>">
                            <?= csrfField() ?>
                            <button type="submit"><?= te('nav_logout') ?></button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="header-auth">
                    <a class="btn btn-ghost btn-sm" href="<?= esc(url('login.php')) ?>"><?= te('nav_login') ?></a>
                    <a class="btn btn-primary btn-sm" href="<?= esc(url('register.php')) ?>"><?= te('nav_register') ?></a>
                </div>
            <?php endif; ?>
            <button class="icon-btn nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav" aria-label="<?= te('nav_open_menu') ?>">
                <?= icon('menu', 'when-closed') ?><?= icon('close', 'when-open') ?>
            </button>
        </div>
    </div>
</header>
<div class="toast-region" id="toasts" aria-live="polite"></div>
<script type="application/json" id="bm-i18n"><?= json_encode($jsStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<main id="main" tabindex="-1">
<?php $navFlashes = getFlashes(); if ($navFlashes): ?>
    <div class="container flash-stack">
        <?php foreach ($navFlashes as $navFlash): ?><?= alertBox($navFlash['type'], $navFlash['message']) ?><?php endforeach; ?>
    </div>
<?php endif; ?>
