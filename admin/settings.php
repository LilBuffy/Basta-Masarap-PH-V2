<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$fields = [
    'restaurant_name' => ['Restaurant name', 60, true],
    'tagline_en' => ['Tagline (English)', 200, false],
    'tagline_fil' => ['Tagline (Filipino)', 200, false],
    'hours_en' => ['Opening hours (English)', 120, false],
    'hours_fil' => ['Opening hours (Filipino)', 120, false],
    'address' => ['Address', 200, false],
    'contact_phone' => ['Phone', 40, false],
    'contact_email' => ['Email', 120, false],
];

$current = siteSettings();
$values = ['delivery_fee' => (string) (float) $current['delivery_fee']];
foreach ($fields as $key => $_) {
    $values[$key] = $current[$key];
}
$errors = [];
$formError = null;

if (isPost()) {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $formError = t('err_csrf_form');
    } else {
        foreach ($fields as $key => [$label, $max, $required]) {
            $values[$key] = postString($key, $max + 1);
            if (mb_strlen($values[$key]) > $max || ($required && $values[$key] === '')) {
                $errors[$key] = $required ? 'This field is required.' : 'Keep this under ' . $max . ' characters.';
            }
        }
        $values['delivery_fee'] = postString('delivery_fee', 10);
        if (!is_numeric($values['delivery_fee']) || (float) $values['delivery_fee'] < 0 || (float) $values['delivery_fee'] > 9999) {
            $errors['delivery_fee'] = 'Enter an amount between 0 and 9,999.';
        }
        if ($values['contact_email'] !== '' && !filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = t('err_email');
        }

        if (!$errors) {
            $values['delivery_fee'] = number_format((float) $values['delivery_fee'], 2, '.', '');
            $save = $db->prepare('INSERT INTO restaurant_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $db->beginTransaction();
            foreach ($values as $key => $value) {
                $save->execute([$key, $value]);
            }
            $db->commit();
            flash('success', 'Settings saved.');
            redirect('admin/settings.php');
        }
    }
}

$pageTitle = 'Settings';
require __DIR__ . '/../includes/header.php';
?>
<section class="page">
    <div class="container">
        <?= pageHead('Admin') ?>
        <?= adminNav('settings') ?>

        <form class="panel panel-narrow" method="post" data-validate data-once novalidate>
            <?= csrfField() ?>
            <?php if ($formError): ?><?= alertBox('error', $formError) ?><?php endif; ?>
            <?= field(['name' => 'restaurant_name', 'label' => 'Restaurant name', 'value' => $values['restaurant_name'], 'required' => true, 'maxlength' => 60, 'error' => $errors['restaurant_name'] ?? null]) ?>
            <?= field(['name' => 'delivery_fee', 'label' => 'Delivery fee (PHP)', 'type' => 'number', 'value' => $values['delivery_fee'], 'required' => true, 'min' => '0', 'max' => '9999', 'step' => '0.01', 'inputmode' => 'decimal', 'data-range-message' => 'Enter an amount between 0 and 9,999.', 'hint' => 'Charged once per order.', 'error' => $errors['delivery_fee'] ?? null]) ?>
            <div class="field-row">
                <?= field(['name' => 'tagline_en', 'label' => 'Tagline (English)', 'value' => $values['tagline_en'], 'maxlength' => 200, 'error' => $errors['tagline_en'] ?? null]) ?>
                <?= field(['name' => 'tagline_fil', 'label' => 'Tagline (Filipino)', 'value' => $values['tagline_fil'], 'maxlength' => 200, 'error' => $errors['tagline_fil'] ?? null]) ?>
            </div>
            <div class="field-row">
                <?= field(['name' => 'hours_en', 'label' => 'Opening hours (English)', 'value' => $values['hours_en'], 'maxlength' => 120, 'error' => $errors['hours_en'] ?? null]) ?>
                <?= field(['name' => 'hours_fil', 'label' => 'Opening hours (Filipino)', 'value' => $values['hours_fil'], 'maxlength' => 120, 'error' => $errors['hours_fil'] ?? null]) ?>
            </div>
            <?= field(['name' => 'address', 'label' => 'Address', 'value' => $values['address'], 'maxlength' => 200, 'error' => $errors['address'] ?? null]) ?>
            <div class="field-row">
                <?= field(['name' => 'contact_phone', 'label' => 'Phone', 'type' => 'tel', 'value' => $values['contact_phone'], 'maxlength' => 40, 'error' => $errors['contact_phone'] ?? null]) ?>
                <?= field(['name' => 'contact_email', 'label' => 'Email', 'type' => 'email', 'value' => $values['contact_email'], 'maxlength' => 120, 'error' => $errors['contact_email'] ?? null]) ?>
            </div>
            <button class="btn btn-primary" type="submit">Save settings</button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
