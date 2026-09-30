<?php
require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$user = currentUser();
$errors = [];
$formError = null;
$values = ['full_name' => $user['full_name'], 'email' => $user['email'], 'contact_number' => (string) ($user['contact_number'] ?? '')];
$activeForm = null;

if (isPost()) {
    $activeForm = ($_POST['form'] ?? '') === 'password' ? 'password' : 'details';
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $formError = t('err_csrf_form');
    } elseif ($activeForm === 'details') {
        $values = ['full_name' => postString('full_name', 121), 'email' => postString('email', 121), 'contact_number' => postString('contact_number', 31)];
        if ($values['full_name'] === '' || mb_strlen($values['full_name']) > 120) {
            $errors['full_name'] = t('err_required');
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 120) {
            $errors['email'] = t('err_email');
        }
        if ($values['contact_number'] !== '' && !validatePhone($values['contact_number'])) {
            $errors['contact_number'] = t('err_phone');
        }
        if (!isset($errors['email'])) {
            $dupe = $db->prepare('SELECT 1 FROM users WHERE email = ? AND user_id != ?');
            $dupe->execute([$values['email'], userId()]);
            if ($dupe->fetchColumn()) {
                $errors['email'] = t('err_email_taken');
            }
        }
        if (!$errors) {
            try {
                $db->prepare('UPDATE users SET full_name = ?, email = ?, contact_number = ? WHERE user_id = ?')
                   ->execute([$values['full_name'], $values['email'], $values['contact_number'] ?: null, userId()]);
                flash('success', t('profile_saved'));
                redirect('profile.php');
            } catch (PDOException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $errors['email'] = t('err_email_taken');
            }
        }
    } else {
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
        $hash = $db->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $hash->execute([userId()]);
        if (!password_verify($current, (string) $hash->fetchColumn())) {
            $errors['current_password'] = t('err_current_password');
        }
        if ($passwordError = validatePassword($new)) {
            $errors['new_password'] = $passwordError;
        } elseif ($new !== $confirm) {
            $errors['confirm_password'] = t('err_password_match');
        }
        if (!$errors) {
            $db->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), userId()]);
            session_regenerate_id(true);
            flash('success', t('password_changed'));
            redirect('profile.php');
        }
    }
}

$reviewsStmt = $db->prepare(
    'SELECT r.rating, r.comment, r.created_at, d.name AS dish_name, d.dish_id
     FROM reviews r JOIN dishes d ON d.dish_id = r.dish_id WHERE r.user_id = ? ORDER BY r.created_at DESC'
);
$reviewsStmt->execute([userId()]);
$myReviews = $reviewsStmt->fetchAll();

$pageTitle = t('profile_title');
require __DIR__ . '/includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead(t('nav_profile'), t('profile_sub')) ?>
        <?= accountNav('profile') ?>

        <div class="panel">
            <div class="row mb-6">
                <span class="avatar avatar-lg" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span>
                <div>
                    <h2><?= esc($user['full_name']) ?></h2>
                    <p class="muted">@<?= esc($user['username']) ?> &middot; <?= te('member_since', formatDate($user['created_at'])) ?></p>
                </div>
            </div>
            <?php if ($formError && $activeForm === 'details'): ?><?= alertBox('error', $formError) ?><?php endif; ?>
            <form method="post" data-validate novalidate>
                <?= csrfField() ?><input type="hidden" name="form" value="details">
                <div class="field-row">
                    <?= field(['name' => 'full_name', 'label' => t('full_name'), 'value' => $values['full_name'], 'required' => true, 'autocomplete' => 'name', 'maxlength' => 120, 'error' => $activeForm === 'details' ? ($errors['full_name'] ?? null) : null]) ?>
                    <?= field(['name' => 'email', 'label' => t('email'), 'type' => 'email', 'value' => $values['email'], 'required' => true, 'autocomplete' => 'email', 'maxlength' => 120, 'error' => $activeForm === 'details' ? ($errors['email'] ?? null) : null]) ?>
                </div>
                <?= field(['name' => 'contact_number', 'label' => t('contact_number'), 'type' => 'tel', 'value' => $values['contact_number'], 'autocomplete' => 'tel', 'inputmode' => 'tel', 'maxlength' => 30, 'pattern' => '[0-9+\\(\\)\\-\\s]{7,20}', 'data-pattern-message' => t('err_phone'), 'error' => $activeForm === 'details' ? ($errors['contact_number'] ?? null) : null]) ?>
                <button class="btn btn-primary" type="submit"><?= te('save_changes') ?></button>
            </form>
        </div>

        <div class="panel">
            <h2><?= te('change_password') ?></h2>
            <?php if ($formError && $activeForm === 'password'): ?><?= alertBox('error', $formError) ?><?php endif; ?>
            <form method="post" data-validate novalidate>
                <?= csrfField() ?><input type="hidden" name="form" value="password">
                <?= field(['name' => 'current_password', 'label' => t('current_password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password', 'error' => $activeForm === 'password' ? ($errors['current_password'] ?? null) : null]) ?>
                <div class="field-row">
                    <?= field(['name' => 'new_password', 'label' => t('new_password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'minlength' => PASSWORD_MIN, 'maxlength' => PASSWORD_MAX, 'hint' => t('password_hint', PASSWORD_MIN), 'error' => $activeForm === 'password' ? ($errors['new_password'] ?? null) : null]) ?>
                    <?= field(['name' => 'confirm_password', 'label' => t('confirm_password'), 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'data-match' => '#f-new_password', 'error' => $activeForm === 'password' ? ($errors['confirm_password'] ?? null) : null]) ?>
                </div>
                <button class="btn btn-secondary" type="submit"><?= te('change_password') ?></button>
            </form>
        </div>

        <div class="panel">
            <h2><?= te('my_reviews') ?></h2>
            <?php if (!$myReviews): ?><p class="muted"><?= te('no_my_reviews') ?></p><?php endif; ?>
            <?php foreach ($myReviews as $rev): ?>
                <article class="review">
                    <div class="review-head">
                        <a class="review-user" href="<?= esc(url('dish.php?id=' . (int) $rev['dish_id'])) ?>"><?= esc($rev['dish_name']) ?></a>
                        <span class="review-date"><?= esc(timeAgo($rev['created_at'])) ?></span>
                    </div>
                    <?= starsRow((int) $rev['rating']) ?><span class="sr-only"><?= te('rating_star', (int) $rev['rating']) ?></span>
                    <p><?= esc($rev['comment']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
