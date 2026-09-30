<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$input = ajaxInput(true);
$reviewId = filter_var($input['review_id'] ?? null, FILTER_VALIDATE_INT);
if (!$reviewId || $reviewId < 1) {
    jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
}
$owner = $db->prepare('SELECT user_id FROM reviews WHERE review_id = ?');
$owner->execute([$reviewId]);
$ownerId = $owner->fetchColumn();
if ($ownerId === false) {
    jsonResponse(['success' => false, 'message' => t('err_review_missing')], 404);
}
if ((int) $ownerId === userId()) {
    jsonResponse(['success' => false, 'message' => t('err_report_own')], 400);
}
$stmt = $db->prepare('INSERT IGNORE INTO review_reports (review_id, user_id) VALUES (?, ?)');
$stmt->execute([$reviewId, userId()]);
jsonResponse(['success' => true, 'message' => t($stmt->rowCount() > 0 ? 'msg_reported' : 'msg_already_reported')]);
