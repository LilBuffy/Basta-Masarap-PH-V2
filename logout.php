<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!isPost() || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    redirect('');
}

$lang = currentLang();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
}
session_destroy();
session_start();
session_regenerate_id(true);
$_SESSION['lang'] = $lang;
flash('success', t('logged_out'));
redirect('');
