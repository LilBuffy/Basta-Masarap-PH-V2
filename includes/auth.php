<?php

const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_MAX_PER_ACCOUNT = 5;
const LOGIN_MAX_PER_IP = 12;
const PASSWORD_MIN = 8;
const PASSWORD_MAX = 72;

function currentUser(bool $refresh = false): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded && !$refresh) {
        return $user;
    }
    $loaded = true;
    $user = null;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    try {
        $stmt = Database::connect()->prepare(
            'SELECT u.user_id, u.username, u.email, u.full_name, u.contact_number, u.status, u.created_at, r.role_name
             FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('currentUser failed: ' . $e->getMessage());
        return null;
    }
    if (!$row || $row['status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
        return null;
    }
    $_SESSION['role'] = $row['role_name'];
    return $user = $row;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function isAdmin(): bool
{
    return (currentUser()['role_name'] ?? '') === 'admin';
}

function userId(): int
{
    return (int) (currentUser()['user_id'] ?? 0);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $next = $_SERVER['REQUEST_URI'] ?? url('');
        redirect('login.php?next=' . rawurlencode($next));
    }
}

function requireAdmin(): void
{
    if (!isLoggedIn()) {
        $next = $_SERVER['REQUEST_URI'] ?? url('');
        redirect('login.php?next=' . rawurlencode($next));
    }
    if (!isAdmin()) {
        renderErrorPage(403, t('err_403_title'), t('err_403_text'));
    }
}

function startUserSession(array $user, string $roleName): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $roleName;
    currentUser(true);
}

function loginThrottled(PDO $db, string $identifier): bool
{
    try {
        $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
        $byAccount = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > ?');
        $byAccount->execute([mb_strtolower($identifier), $since]);
        if ((int) $byAccount->fetchColumn() >= LOGIN_MAX_PER_ACCOUNT) {
            return true;
        }
        $byIp = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?');
        $byIp->execute([clientIp(), $since]);
        return (int) $byIp->fetchColumn() >= LOGIN_MAX_PER_IP;
    } catch (Throwable $e) {
        error_log('Login throttle unavailable: ' . $e->getMessage());
        return false;
    }
}

function recordLoginFailure(PDO $db, string $identifier): void
{
    try {
        $db->prepare('INSERT INTO login_attempts (ip_address, identifier) VALUES (?, ?)')
           ->execute([clientIp(), mb_strtolower(mb_substr($identifier, 0, 120))]);
        $db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    } catch (Throwable $e) {
        error_log('Could not record login failure: ' . $e->getMessage());
    }
}

function clearLoginFailures(PDO $db, string $identifier): void
{
    try {
        $db->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([mb_strtolower($identifier)]);
    } catch (Throwable $e) {
        error_log('Could not clear login failures: ' . $e->getMessage());
    }
}

function attemptLogin(PDO $db, string $identifier, string $password): array
{
    if (loginThrottled($db, $identifier)) {
        return ['success' => false, 'message' => t('err_login_throttled', LOGIN_WINDOW_MINUTES)];
    }

    $stmt = $db->prepare(
        'SELECT u.user_id, u.username, u.password_hash, u.status, r.role_name
         FROM users u JOIN roles r ON r.role_id = u.role_id
         WHERE u.username = ? OR u.email = ? LIMIT 1'
    );
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    $hash = $user['password_hash'] ?? '$2y$10$lFrnzjjvEPtzM3PDjlpvveKzi2RgKXYKRMsPmRHyC1di7Qo94Psxm';
    $valid = password_verify($password, $hash) && $user;

    if (!$valid) {
        recordLoginFailure($db, $identifier);
        return ['success' => false, 'message' => t('err_login_invalid')];
    }
    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => t('err_login_inactive')];
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $db->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
           ->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
    }

    clearLoginFailures($db, $identifier);
    startUserSession($user, $user['role_name']);
    return ['success' => true];
}

function validatePassword(string $password): ?string
{
    if (strlen($password) < PASSWORD_MIN) {
        return t('err_password_short', PASSWORD_MIN);
    }
    if (strlen($password) > PASSWORD_MAX) {
        return t('err_password_long', PASSWORD_MAX);
    }
    return null;
}

function validatePhone(string $phone): bool
{
    if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
        return false;
    }
    $digits = preg_replace('/\D/', '', $phone);
    return strlen($digits) >= 7 && strlen($digits) <= 13;
}
