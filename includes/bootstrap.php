<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

date_default_timezone_set(getenv('BM_TIMEZONE') ?: 'Asia/Manila');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once APP_ROOT . '/config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/components.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/catalog.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

if (session_status() === PHP_SESSION_NONE) {
    session_name('bm_session');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
header('Cache-Control: private, no-cache');

set_exception_handler(function (Throwable $e): void {
    error_log('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $unavailable = $e instanceof DatabaseUnavailableException;

    if (isAjaxRequest()) {
        jsonResponse(['success' => false, 'message' => t('err_server')], $unavailable ? 503 : 500);
    }
    if (headers_sent()) {
        exit;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    renderErrorPage($unavailable ? 503 : 500, $unavailable ? t('err_unavailable_title') : t('err_500_title'), $unavailable ? t('err_unavailable_text') : t('err_500_text'));
});

initLanguage();

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$db = Database::connect();
