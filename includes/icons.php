<?php

function icon(string $name, string $class = ''): string
{
    static $paths = [
        'cart' => '<path d="M3 4h2.2l2 10h9.6l1.8-7H6.2"/><circle cx="9" cy="19" r="1.4"/><circle cx="16.5" cy="19" r="1.4"/>',
        'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20c.8-3.6 3.8-5.5 7.5-5.5s6.7 1.9 7.5 5.5"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'heart' => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.2a4.3 4.3 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z"/>',
        'bookmark' => '<path d="M7 4h10a1 1 0 0 1 1 1v15l-6-4-6 4V5a1 1 0 0 1 1-1z"/>',
        'thumb-up' => '<path d="M8 11v9H4.5v-9H8zM8 11l3.5-7c1.5 0 2.5 1 2.2 2.6L13.2 9h5a1.7 1.7 0 0 1 1.7 2l-1.3 6.5a2 2 0 0 1-2 1.5H8"/>',
        'thumb-down' => '<path d="M8 13V4H4.5v9H8zM8 13l3.5 7c1.5 0 2.5-1 2.2-2.6L13.2 15h5a1.7 1.7 0 0 0 1.7-2l-1.3-6.5a2 2 0 0 0-2-1.5H8"/>',
        'flag' => '<path d="M6 21V4M6 5h11l-2 4 2 4H6"/>',
        'star' => '<path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.9l-5.2 2.8 1-5.9L3.5 9.7l5.9-.8L12 3.5z"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'alert' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V13M12 16.5v.1"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/>',
        'trash' => '<path d="M5 7h14M10 7V4.5h4V7M7 7l.8 12.5h8.4L17 7M10.5 11v5M13.5 11v5"/>',
        'bowl' => '<path d="M3.5 11h17a8.5 8.5 0 0 1-8.5 8.5A8.5 8.5 0 0 1 3.5 11zM8 7c0-1.2 1-1.8 1-3M12 7c0-1.2 1-1.8 1-3M16 7c0-1.2 1-1.8 1-3"/>',
        'chevron' => '<path d="M6 9l6 6 6-6"/>',
        'edit' => '<path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3z"/>',
        'package' => '<path d="M4 8l8-4 8 4v8l-8 4-8-4V8zM4 8l8 4 8-4M12 12v8"/>',
        'logout' => '<path d="M10 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H10M15 8l4 4-4 4M19 12H9"/>',
    ];
    $class = trim('icon icon-' . $name . ' ' . $class);
    return '<svg class="' . esc($class) . '" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}
