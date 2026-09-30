<?php

const LANGS = ['en', 'fil'];
const ORDER_STATUSES = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
const ORDER_FLOW = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];

function basePath(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $env = getenv('BM_BASE_PATH');
    if ($env !== false) {
        return $base = rtrim($env, '/');
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $file = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $root = realpath(APP_ROOT) ?: APP_ROOT;
    if ($file !== '' && str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($root) + 1));
        if (str_ends_with($script, '/' . $relative)) {
            return $base = substr($script, 0, -strlen('/' . $relative));
        }
    }
    return $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
}

function url(string $path = ''): string
{
    return basePath() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $version = is_file($file) ? filemtime($file) : 0;
    return url('assets/' . $path) . '?v=' . $version;
}

function esc(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function initLanguage(): void
{
    $cookie = $_COOKIE['bm_lang'] ?? null;
    if (empty($_SESSION['lang']) || !in_array($_SESSION['lang'], LANGS, true)) {
        $_SESSION['lang'] = in_array($cookie, LANGS, true) ? $cookie : 'en';
    }

    $requested = $_GET['lang'] ?? null;
    if (is_string($requested) && in_array($requested, LANGS, true)) {
        $_SESSION['lang'] = $requested;
        setcookie('bm_lang', $requested, [
            'expires' => time() + 31536000,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !isAjaxRequest()) {
            $query = $_GET;
            unset($query['lang']);
            $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
            header('Location: ' . $path . ($query ? '?' . http_build_query($query) : ''));
            exit;
        }
    }
}

function currentLang(): string
{
    return $_SESSION['lang'] ?? 'en';
}

function langUrl(string $lang): string
{
    $query = $_GET;
    $query['lang'] = $lang;
    return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') . '?' . http_build_query($query);
}

function t(string $key, string|int|float ...$args): string
{
    static $dict = null;
    if ($dict === null) {
        $dict = require __DIR__ . '/lang.php';
    }
    $text = $dict[currentLang()][$key] ?? $dict['en'][$key] ?? $key;
    return $args ? vsprintf($text, $args) : $text;
}

function te(string $key, string|int|float ...$args): string
{
    return esc(t($key, ...$args));
}

function dishText(array $dish, string $field): string
{
    $suffix = currentLang() === 'fil' ? '_fil' : '_en';
    $value = $dish[$field . $suffix] ?? '';
    return $value !== '' ? $value : ($dish[$field . '_en'] ?? '');
}

function categoryName(array $category): string
{
    return currentLang() === 'fil' ? $category['name_fil'] : $category['name_en'];
}

function itemsLabel(int $count): string
{
    return $count === 1 ? t('item_one') : t('item_many', $count);
}

function peso(float|int|string $amount): string
{
    $value = round((float) $amount, 2);
    return '₱' . number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2);
}

function formatDate(?string $datetime, bool $withTime = false): string
{
    $timestamp = $datetime ? strtotime($datetime) : false;
    if ($timestamp === false) {
        return '';
    }
    $months = currentLang() === 'fil'
        ? ['Ene', 'Peb', 'Mar', 'Abr', 'May', 'Hun', 'Hul', 'Ago', 'Set', 'Okt', 'Nob', 'Dis']
        : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $out = $months[(int) date('n', $timestamp) - 1] . ' ' . date('j, Y', $timestamp);
    return $withTime ? $out . ', ' . date('g:i A', $timestamp) : $out;
}

function timeAgo(string $datetime): string
{
    $diff = time() - (int) strtotime($datetime);
    if ($diff < 60) {
        return t('time_now');
    }
    if ($diff < 3600) {
        return t('time_minutes', (int) floor($diff / 60));
    }
    if ($diff < 86400) {
        return t('time_hours', (int) floor($diff / 3600));
    }
    if ($diff < 2592000) {
        return t('time_days', (int) floor($diff / 86400));
    }
    return formatDate($datetime);
}

function statusLabel(string $status): string
{
    return t('status_' . $status);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc(csrfToken()) . '">';
}

function verifyCsrf(mixed $token): bool
{
    return is_string($token) && $token !== '' && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function isAjaxRequest(): bool
{
    return str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/');
}

function postString(string $key, int $max = 255): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

function safeNext(?string $target): ?string
{
    if ($target === null || $target === '' || $target[0] !== '/' || str_starts_with($target, '//') || str_contains($target, '\\')) {
        return null;
    }
    $base = basePath();
    if ($base !== '' && !str_starts_with($target, $base . '/')) {
        return null;
    }
    return $target;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type === 'error' ? 'error' : 'success', 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function clientIp(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function siteSettings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }
    $defaults = [
        'restaurant_name' => 'Basta Masarap',
        'delivery_fee' => '49',
        'tagline_en' => "When it's delicious, it doesn't need much explanation.",
        'tagline_fil' => 'Kapag masarap, hindi na kailangan ng maraming explanation.',
        'hours_en' => '',
        'hours_fil' => '',
        'address' => '',
        'contact_phone' => '',
        'contact_email' => '',
    ];
    $settings = $defaults;
    try {
        $rows = Database::connect()->query('SELECT setting_key, setting_value FROM restaurant_settings')->fetchAll();
        foreach ($rows as $row) {
            $key = $row['setting_key'];
            $keepEmpty = in_array($key, ['address', 'contact_phone', 'contact_email', 'hours_en', 'hours_fil'], true);
            if ($row['setting_value'] !== '' || $keepEmpty) {
                $settings[$key] = $row['setting_value'];
            }
        }
    } catch (Throwable $e) {
        error_log('Could not load settings: ' . $e->getMessage());
    }
    return $settings;
}

function siteName(): string
{
    return siteSettings()['restaurant_name'];
}

function siteTagline(): string
{
    return siteSettings()[currentLang() === 'fil' ? 'tagline_fil' : 'tagline_en'];
}

function siteHours(): string
{
    $settings = siteSettings();
    $value = $settings[currentLang() === 'fil' ? 'hours_fil' : 'hours_en'];
    return $value !== '' ? $value : $settings['hours_en'];
}

function deliveryFee(): float
{
    return max(0.0, (float) siteSettings()['delivery_fee']);
}

function generateCode(PDO $db, string $prefix, string $table, string $column): string
{
    $stamp = $prefix . '-' . date('Ymd') . '-';
    $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY {$column} DESC LIMIT 1");
    $stmt->execute([$stamp . '%']);
    $last = $stmt->fetchColumn();
    $next = $last ? ((int) substr((string) $last, -4)) + 1 : 1;
    return $stamp . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function likePattern(string $term): string
{
    return '%' . addcslashes($term, '%_\\') . '%';
}

function dishImageUrl(array $dish): ?string
{
    $path = (string) ($dish['image_path'] ?? '');
    if ($path === '' || str_contains($path, '..')) {
        return null;
    }
    $full = APP_ROOT . '/' . $path;
    if (!is_file($full)) {
        return null;
    }
    return url($path) . '?v=' . filemtime($full);
}

function renderErrorPage(int $code, string $title, string $text): never
{
    http_response_code($code);
    $pageTitle = $title;
    require __DIR__ . '/header.php';
    echo '<section class="page"><div class="container">';
    echo emptyState($title, $text, url('menu.php'), t('nav_menu'), 'alert');
    echo '</div></section>';
    require __DIR__ . '/footer.php';
    exit;
}

function ajaxInput(bool $requireLogin = false): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        jsonResponse(['success' => false, 'message' => t('err_invalid')], 405);
    }
    $raw = file_get_contents('php://input');
    $input = is_string($raw) && strlen($raw) < 20000 ? json_decode($raw, true) : null;
    if (!is_array($input)) {
        jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
    }
    if (!verifyCsrf($input['csrf_token'] ?? null)) {
        jsonResponse(['success' => false, 'message' => t('err_csrf')], 403);
    }
    if ($requireLogin && !isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => t('err_login_required'), 'login_url' => url('login.php')], 401);
    }
    return $input;
}

function toggleRow(PDO $db, string $table, string $keyCol): never
{
    $input = ajaxInput(true);
    $dishId = filter_var($input['dish_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$dishId || $dishId < 1) {
        jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
    }
    $exists = $db->prepare('SELECT 1 FROM dishes WHERE dish_id = ?');
    $exists->execute([$dishId]);
    if (!$exists->fetchColumn()) {
        jsonResponse(['success' => false, 'message' => t('err_dish_gone')], 404);
    }
    $uid = userId();
    $delete = $db->prepare("DELETE FROM {$table} WHERE user_id = ? AND dish_id = ?");
    $delete->execute([$uid, $dishId]);
    if ($delete->rowCount() > 0) {
        jsonResponse(['success' => true, 'active' => false, 'message' => t($keyCol === 'favorite' ? 'remove_favorite' : 'remove_wishlist')]);
    }
    $db->prepare("INSERT IGNORE INTO {$table} (user_id, dish_id) VALUES (?, ?)")->execute([$uid, $dishId]);
    jsonResponse(['success' => true, 'active' => true, 'message' => t($keyCol === 'favorite' ? 'add_favorite' : 'add_wishlist')]);
}

function foldText(string $text): string
{
    if (class_exists('Normalizer')) {
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);
        if (is_string($decomposed)) {
            $text = preg_replace('/\p{Mn}/u', '', $decomposed) ?? $text;
        }
    } else {
        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);
    }
    return mb_strtolower($text);
}
