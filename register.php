<?php
require_once __DIR__ . '/includes/bootstrap.php';

$next = safeNext((string) ($_GET['next'] ?? ''));
if (isLoggedIn()) {
    redirect('');
}

$errors = [];
$formError = null;
$old = ['full_name' => '', 'username' => '', 'email' => '', 'contact_number' => ''];

if (isPost()) {
    foreach (['full_name' => 120, 'username' => 30, 'email' => 120, 'contact_number' => 30] as $key => $max) {
        $old[$key] = postString($key, $max + 1);
    }
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $formError = t('err_csrf_form');
    } else {
        if ($old['full_name'] === '' || mb_strlen($old['full_name']) > 120) {
            $errors['full_name'] = t('err_required');
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $old['username'])) {
            $errors['username'] = t('err_username');
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) {
            $errors['email'] = t('err_email');
        }
        if ($old['contact_number'] !== '' && !validatePhone($old['contact_number'])) {
            $errors['contact_number'] = t('err_phone');
        }
        if ($passwordError = validatePassword($password)) {
            $errors['password'] = $passwordError;
        } elseif ($password !== $confirm) {
            $errors['confirm_password'] = t('err_password_match');
        }

        if (!$errors) {
            $check = $db->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
            $check->execute([$old['username'], $old['email']]);
            foreach ($check->fetchAll() as $row) {
                if (mb_strtolower($row['username']) === mb_strtolower($old['username'])) {
                    $errors['username'] = t('err_taken');
                }
                if (mb_strtolower($row['email']) === mb_strtolower($old['email'])) {
                    $errors['email'] = t('err_taken');
                }
            }
        }

        if (!$errors) {
            try {
                $db->prepare('INSERT INTO users (role_id, username, email, password_hash, full_name, contact_number) VALUES (2, ?, ?, ?, ?, ?)')
                   ->execute([$old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['full_name'], $old['contact_number'] ?: null]);
                flash('success', t('account_created'));
                redirect('login.php' . ($next ? '?next=' . rawurlencode($next) : ''));
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $errors['username'] = t('err_taken');
            }
        }
    }
}

$pageTitle = t('register_title');
require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <div class="auth-card">
            <h1><?= te('register_title') ?></h1>
            <p><?= te('register_sub') ?></p>
            <?php if ($formError): ?><?= alertBox('error', $formError) ?><?php endif; ?>
            <form method="post" data-validate novalidate>
                <?= csrfField() ?>
                <?= field(['name' => 'full_name', 'label' => t('full_name'), 'value' => $old['full_name'], 'required' => true, 'autocomplete' => 'name', 'maxlength' => 120, 'error' => $errors['full_name'] ?? null]) ?>
                <?= field(['name' => 'username', 'label' => t('username'), 'value' => $old['username'], 'required' => true, 'autocomplete' => 'username', 'maxlength' => 30, 'minlength' => 3, 'pattern' => '[A-Za-z0-9_]{3,30}', 'data-pattern-message' => t('err_username'), 'hint' => t('username_hint'), 'error' => $errors['username'] ?? null]) ?>
                <?= field(['name' => 'email', 'label' => t('email'), 'type' => 'email', 'value' => $old['email'], 'required' => true, 'autocomplete' => 'email', 'maxlength' => 120, 'error' => $errors['email'] ?? null]) ?>
                <?= field(['name' => 'contact_number', 'label' => t('contact_number'), 'type' => 'tel', 'value' => $old['contact_number'], 'autocomplete' => 'tel', 'inputmode' => 'tel', 'maxlength' => 30, 'pattern' => '[0-9+\\(\\)\\-\\s]{7,20}', 'data-pattern-message' => t('err_phone'), 'error' => $errors['contact_number'] ?? null]) ?>
                <?= field(['name' => 'password', 'label' => t('password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'minlength' => PASSWORD_MIN, 'maxlength' => PASSWORD_MAX, 'hint' => t('password_hint', PASSWORD_MIN), 'error' => $errors['password'] ?? null]) ?>
                <?= field(['name' => 'confirm_password', 'label' => t('confirm_password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'data-match' => '#f-password', 'error' => $errors['confirm_password'] ?? null]) ?>
                <button class="btn btn-primary btn-block" type="submit"><?= te('nav_register') ?></button>
            </form>
            <p class="auth-alt"><?= te('already_have_account') ?> <a class="link" href="<?= esc(url('login.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>"><?= te('nav_login') ?></a></p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
