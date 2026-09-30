<?php

function starsRow(int $filled): string
{
    $out = '<span class="stars" aria-hidden="true">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= icon('star', $i <= $filled ? 'on' : '');
    }
    return $out . '</span>';
}

function ratingSummary(mixed $average, int $count): string
{
    if ($count < 1) {
        return '<span class="rating rating-none">' . te('rating_none') . '</span>';
    }
    $avg = number_format((float) $average, 1);
    return '<span class="rating">' . icon('star', 'on') . '<span class="rating-value">' . esc($avg) . '</span>'
        . '<span class="rating-count" aria-hidden="true">(' . $count . ')</span>'
        . '<span class="sr-only">' . te('rating_aria', $avg, $count) . '</span></span>';
}

function statusPill(string $status): string
{
    return '<span class="status status-' . esc($status) . '">' . esc(statusLabel($status)) . '</span>';
}

function alertBox(string $type, string $message): string
{
    $kind = $type === 'error' ? 'error' : 'success';
    return '<div class="alert alert-' . $kind . '" role="' . ($kind === 'error' ? 'alert' : 'status') . '">'
        . icon($kind === 'error' ? 'alert' : 'check') . '<p>' . esc($message) . '</p></div>';
}

function emptyState(string $title, string $text = '', ?string $href = null, ?string $label = null, string $iconName = 'bowl', bool $hidden = false): string
{
    $out = '<div class="empty-state"' . ($hidden ? ' hidden data-empty-state' : '') . '>' . icon($iconName, 'empty-icon');
    $out .= '<h2>' . esc($title) . '</h2>';
    if ($text !== '') {
        $out .= '<p>' . esc($text) . '</p>';
    }
    if ($href && $label) {
        $out .= '<a class="btn btn-primary" href="' . esc($href) . '">' . esc($label) . '</a>';
    }
    return $out . '</div>';
}

function dishImage(array $dish, bool $describe = false, bool $eager = false): string
{
    $src = dishImageUrl($dish);
    if ($src === null) {
        return '<span class="dish-placeholder" role="img" aria-label="' . esc($dish['name']) . '">' . icon('bowl') . '</span>';
    }
    return '<img src="' . esc($src) . '" alt="' . ($describe ? esc($dish['name']) : '') . '" width="800" height="600"'
        . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">';
}

function dishBadge(array $dish): string
{
    if (isset($dish['is_available']) && !$dish['is_available']) {
        return '<span class="dish-badge dish-badge-off">' . te('unavailable') . '</span>';
    }
    if (!empty($dish['is_popular'])) {
        return '<span class="dish-badge">' . te('badge_popular') . '</span>';
    }
    if (!empty($dish['is_featured'])) {
        return '<span class="dish-badge">' . te('badge_featured') . '</span>';
    }
    return '';
}

function dishCard(array $dish, array $opts = []): string
{
    $id = (int) $dish['dish_id'];
    $available = !isset($dish['is_available']) || (bool) $dish['is_available'];
    $toggle = $opts['toggle'] ?? 'wishlist';
    $active = !empty($opts['active']);
    $removeOnOff = !empty($opts['remove_on_off']);
    $hidden = !empty($opts['hidden']);
    $extra = $opts['attrs'] ?? '';
    $labels = $toggle === 'favorite'
        ? [t('remove_favorite'), t('add_favorite'), 'heart']
        : [t('remove_wishlist'), t('add_wishlist'), 'bookmark'];

    $out = '<li class="dish-item' . ($available ? '' : ' is-unavailable') . '" data-dish-item' . ($hidden ? ' hidden' : '') . ' ' . $extra . '>';
    $out .= '<article class="dish-card">';
    $out .= '<div class="dish-card-media">' . dishImage($dish) . dishBadge($dish);
    $out .= '<button type="button" class="save-btn' . ($active ? ' is-active' : '') . '" data-toggle="' . $toggle . '" data-dish-id="' . $id . '"'
        . ' data-label-on="' . esc($labels[0]) . '" data-label-off="' . esc($labels[1]) . '"' . ($removeOnOff ? ' data-remove-on-off' : '')
        . ' aria-pressed="' . ($active ? 'true' : 'false') . '" aria-label="' . esc($active ? $labels[0] : $labels[1]) . '">' . icon($labels[2]) . '</button>';
    $out .= '</div><div class="dish-card-body">';
    $out .= '<h3 class="dish-card-title"><a class="dish-card-link" href="' . esc(url('dish.php?id=' . $id)) . '">' . esc($dish['name']) . '</a></h3>';
    $out .= '<p class="dish-card-desc">' . esc(dishText($dish, 'desc')) . '</p>';
    $out .= '<div class="dish-card-foot"><div class="dish-card-info"><span class="price">' . esc(peso($dish['price'])) . '</span>'
        . ratingSummary($dish['avg_rating'] ?? null, (int) ($dish['review_count'] ?? 0)) . '</div>';
    $out .= '<button type="button" class="btn btn-primary btn-sm add-btn" data-add-cart="' . $id . '"' . ($available ? '' : ' disabled') . '>'
        . '<span class="add-idle">' . icon('plus') . '<span>' . te('add') . '</span></span>'
        . '<span class="add-done" aria-hidden="true">' . icon('check') . '<span>' . te('added') . '</span></span></button>';
    return $out . '</div></div></article></li>';
}

function field(array $o): string
{
    $name = $o['name'];
    $id = $o['id'] ?? ('f-' . $name);
    $type = $o['type'] ?? 'text';
    $value = (string) ($o['value'] ?? '');
    $error = $o['error'] ?? null;
    $hint = $o['hint'] ?? null;
    $describedBy = array_filter([$hint ? $id . '-hint' : null, $error ? $id . '-error' : null]);

    $attrs = ' id="' . esc($id) . '" name="' . esc($name) . '"';
    foreach (['autocomplete', 'inputmode', 'minlength', 'maxlength', 'min', 'max', 'step', 'placeholder', 'accept', 'pattern'] as $attr) {
        if (isset($o[$attr])) {
            $attrs .= ' ' . $attr . '="' . esc((string) $o[$attr]) . '"';
        }
    }
    foreach ($o as $key => $val) {
        if (is_string($key) && str_starts_with($key, 'data-')) {
            $attrs .= ' ' . $key . '="' . esc((string) $val) . '"';
        }
    }
    if (!empty($o['required'])) {
        $attrs .= ' required';
    }
    if (!empty($o['autofocus'])) {
        $attrs .= ' autofocus';
    }
    if ($describedBy) {
        $attrs .= ' aria-describedby="' . esc(implode(' ', $describedBy)) . '"';
    }
    if ($error) {
        $attrs .= ' aria-invalid="true"';
    }

    $out = '<div class="field' . ($error ? ' has-error' : '') . '">';
    $out .= '<label for="' . esc($id) . '">' . esc($o['label']) . (empty($o['required']) && empty($o['hide_optional']) ? ' <span class="optional">' . te('optional') . '</span>' : '') . '</label>';
    if (($o['type'] ?? '') === 'textarea') {
        $out .= '<textarea class="input"' . $attrs . ' rows="' . (int) ($o['rows'] ?? 3) . '">' . esc($value) . '</textarea>';
    } elseif (($o['type'] ?? '') === 'select') {
        $out .= '<select class="input"' . $attrs . '>';
        foreach ($o['options'] as $optValue => $optLabel) {
            $out .= '<option value="' . esc((string) $optValue) . '"' . ((string) $optValue === $value ? ' selected' : '') . '>' . esc($optLabel) . '</option>';
        }
        $out .= '</select>';
    } else {
        $out .= '<input class="input" type="' . esc($type) . '"' . $attrs . ($type === 'password' || $type === 'file' ? '' : ' value="' . esc($value) . '"') . '>';
    }
    if ($hint) {
        $out .= '<p class="field-hint" id="' . esc($id) . '-hint">' . esc($hint) . '</p>';
    }
    if ($error) {
        $out .= '<p class="field-error" id="' . esc($id) . '-error">' . esc($error) . '</p>';
    }
    return $out . '</div>';
}

function pageHead(string $title, string $sub = '', string $actions = ''): string
{
    $out = '<div class="page-head"><div><h1>' . esc($title) . '</h1>';
    if ($sub !== '') {
        $out .= '<p class="page-sub">' . esc($sub) . '</p>';
    }
    return $out . '</div>' . ($actions !== '' ? '<div class="page-actions">' . $actions . '</div>' : '') . '</div>';
}

function subnav(array $items, string $active, string $label): string
{
    $out = '<nav class="subnav" aria-label="' . esc($label) . '"><ul>';
    foreach ($items as $key => [$href, $text]) {
        $out .= '<li><a href="' . esc($href) . '"' . ($key === $active ? ' class="is-active" aria-current="page"' : '') . '>' . esc($text) . '</a></li>';
    }
    return $out . '</ul></nav>';
}

function accountNav(string $active): string
{
    return subnav([
        'profile' => [url('profile.php'), t('nav_profile')],
        'orders' => [url('orders.php'), t('nav_orders')],
        'favorites' => [url('favorites.php'), t('nav_favorites')],
        'wishlist' => [url('wishlist.php'), t('nav_wishlist')],
    ], $active, t('nav_account'));
}

function adminNav(string $active): string
{
    return subnav([
        'index' => [url('admin/index.php'), 'Overview'],
        'orders' => [url('admin/orders.php'), 'Orders'],
        'dishes' => [url('admin/dishes.php'), 'Dishes'],
        'comments' => [url('admin/comments.php'), 'Reviews'],
        'users' => [url('admin/users.php'), 'Users'],
        'settings' => [url('admin/settings.php'), 'Settings'],
    ], $active, 'Admin');
}

function pagination(int $page, int $pages, string $path, array $query = []): string
{
    if ($pages <= 1) {
        return '';
    }
    $link = static function (int $p, string $label, string $class = '', bool $disabled = false) use ($path, $query): string {
        if ($disabled) {
            return '<span class="page-link is-disabled ' . $class . '">' . esc($label) . '</span>';
        }
        $q = http_build_query(array_merge($query, ['page' => $p]));
        return '<a class="page-link ' . $class . '" href="' . esc($path . '?' . $q) . '">' . esc($label) . '</a>';
    };
    return '<nav class="pagination" aria-label="' . te('pagination') . '">'
        . $link($page - 1, t('previous'), '', $page <= 1)
        . '<span class="page-status">' . te('page_of', $page, $pages) . '</span>'
        . $link($page + 1, t('next'), '', $page >= $pages)
        . '</nav>';
}

function confirmAttrs(string $message, string $label): string
{
    return ' data-confirm="' . esc($message) . '" data-confirm-label="' . esc($label) . '"';
}
