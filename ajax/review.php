<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$input = ajaxInput(true);
$action = $input['action'] ?? '';
$uid = userId();

$readReview = static function () use ($input): array {
    $rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT);
    $comment = trim((string) ($input['comment'] ?? ''));
    if (!$rating || $rating < 1 || $rating > 5 || $comment === '') {
        jsonResponse(['success' => false, 'message' => t('err_review_fields')], 422);
    }
    if (mb_strlen($comment) > 1000) {
        jsonResponse(['success' => false, 'message' => t('err_review_long')], 422);
    }
    return [$rating, $comment];
};

if ($action === 'create') {
    $dishId = filter_var($input['dish_id'] ?? null, FILTER_VALIDATE_INT);
    [$rating, $comment] = $readReview();
    if (!$dishId || $dishId < 1) {
        jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
    }
    $exists = $db->prepare('SELECT 1 FROM dishes WHERE dish_id = ?');
    $exists->execute([$dishId]);
    if (!$exists->fetchColumn()) {
        jsonResponse(['success' => false, 'message' => t('err_dish_gone')], 404);
    }
    $stmt = $db->prepare('INSERT IGNORE INTO reviews (user_id, dish_id, rating, comment) VALUES (?, ?, ?, ?)');
    $stmt->execute([$uid, $dishId, $rating, $comment]);
    if ($stmt->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => t('err_review_dupe')], 409);
    }
    flash('success', t('msg_review_posted'));
    jsonResponse(['success' => true]);
}

if ($action === 'edit') {
    $reviewId = filter_var($input['review_id'] ?? null, FILTER_VALIDATE_INT);
    [$rating, $comment] = $readReview();
    if (!$reviewId || $reviewId < 1) {
        jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
    }
    $stmt = $db->prepare('UPDATE reviews SET rating = ?, comment = ?, is_edited = 1 WHERE review_id = ? AND user_id = ?');
    $stmt->execute([$rating, $comment, $reviewId, $uid]);
    $own = $db->prepare('SELECT 1 FROM reviews WHERE review_id = ? AND user_id = ?');
    $own->execute([$reviewId, $uid]);
    if (!$own->fetchColumn()) {
        jsonResponse(['success' => false, 'message' => t('err_review_missing')], 404);
    }
    flash('success', t('msg_review_updated'));
    jsonResponse(['success' => true]);
}

if ($action === 'delete') {
    $reviewId = filter_var($input['review_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$reviewId || $reviewId < 1) {
        jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
    }
    $stmt = $db->prepare('DELETE FROM reviews WHERE review_id = ? AND user_id = ?');
    $stmt->execute([$reviewId, $uid]);
    if ($stmt->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => t('err_review_missing')], 404);
    }
    flash('success', t('msg_review_deleted'));
    jsonResponse(['success' => true]);
}

jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
