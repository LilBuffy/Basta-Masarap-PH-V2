<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$input = ajaxInput(true);
$dishId = filter_var($input['dish_id'] ?? null, FILTER_VALIDATE_INT);
$type = $input['type'] ?? '';
if (!$dishId || $dishId < 1 || !in_array($type, ['like', 'dislike'], true)) {
    jsonResponse(['success' => false, 'message' => t('err_invalid')], 400);
}
$exists = $db->prepare('SELECT 1 FROM dishes WHERE dish_id = ?');
$exists->execute([$dishId]);
if (!$exists->fetchColumn()) {
    jsonResponse(['success' => false, 'message' => t('err_dish_gone')], 404);
}

$uid = userId();
$stmt = $db->prepare('SELECT type FROM reactions WHERE user_id = ? AND dish_id = ?');
$stmt->execute([$uid, $dishId]);
$existing = $stmt->fetchColumn();

if ($existing === $type) {
    $db->prepare('DELETE FROM reactions WHERE user_id = ? AND dish_id = ?')->execute([$uid, $dishId]);
    $now = null;
} else {
    $db->prepare('INSERT INTO reactions (user_id, dish_id, type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE type = VALUES(type)')
       ->execute([$uid, $dishId, $type]);
    $now = $type;
}

$counts = $db->prepare("SELECT COALESCE(SUM(type = 'like'), 0) AS likes, COALESCE(SUM(type = 'dislike'), 0) AS dislikes FROM reactions WHERE dish_id = ?");
$counts->execute([$dishId]);
$row = $counts->fetch();
jsonResponse(['success' => true, 'reaction' => $now, 'likes' => (int) $row['likes'], 'dislikes' => (int) $row['dislikes']]);
