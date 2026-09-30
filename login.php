<?php
require_once __DIR__ . '/includes/bootstrap.php';

$next = safeNext((string) ($_GET['next'] ?? $_POST['next'] ?? ''));
if (isLoggedIn()) {
    redirect($next ?? '');
}

$error = null;
$identifier = '';
if (isPost()) {
    $identifier = postString('identifier', 120);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = t('err_csrf_form');
    } elseif ($identifier === '' || $password === '') {
        $error = t('err_required');
    } else {
        $result = attemptLogin($db, $identifier, $password);
        if ($result['success']) {
            redirect($next ?? (isAdmin() ? 'admin/index.php' : ''));
        }
        $error = $result['message'];
    }
}

$pageTitle = t('login_title');
require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <div class="auth-card">
            <h1><?= te('login_title') ?></h1>
            <p><?= te('login_sub') ?></p>
            <?php if ($error): ?><?= alertBox('error', $error) ?><?php endif; ?>
            <form method="post" data-validate novalidate>
                <?= csrfField() ?>
                <?php if ($next): ?><input type="hidden" name="next" value="<?= esc($next) ?>"><?php endif; ?>
                <?= field(['name' => 'identifier', 'label' => t('username_or_email'), 'value' => $identifier, 'required' => true, 'autocomplete' => 'username', 'autofocus' => $error === null, 'maxlength' => 120]) ?>
                <?= field(['name' => 'password', 'label' => t('password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
                <button class="btn btn-primary btn-block" type="submit"><?= te('nav_login') ?></button>
            </form>
            <p class="auth-alt"><?= te('no_account') ?> <a class="link" href="<?= esc(url('register.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>"><?= te('nav_register') ?></a></p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
