<?php

function dishRows(PDO $db, string $where = '1=1', array $params = [], string $order = 'd.is_available DESC, d.is_popular DESC, d.is_featured DESC, d.name ASC', ?int $limit = null): array
{
    $sql = "SELECT d.*, c.slug AS category_slug, c.name_en AS cat_en, c.name_fil AS cat_fil,
                (SELECT COUNT(*) FROM reactions WHERE dish_id = d.dish_id AND type = 'like') AS likes,
                (SELECT COUNT(*) FROM reactions WHERE dish_id = d.dish_id AND type = 'dislike') AS dislikes,
                (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE dish_id = d.dish_id AND status = 'visible') AS avg_rating,
                (SELECT COUNT(*) FROM reviews WHERE dish_id = d.dish_id AND status = 'visible') AS review_count
            FROM dishes d JOIN categories c ON c.category_id = d.category_id
            WHERE {$where} ORDER BY {$order}" . ($limit ? ' LIMIT ' . (int) $limit : '');
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function userDishIds(PDO $db, string $table): array
{
    if (!isLoggedIn()) {
        return [];
    }
    $stmt = $db->prepare("SELECT dish_id FROM {$table} WHERE user_id = ?");
    $stmt->execute([userId()]);
    return array_map('intval', array_column($stmt->fetchAll(), 'dish_id'));
}

function dishGrid(array $dishes, array $savedIds = [], string $id = ''): string
{
    $out = '<ul class="dish-grid"' . ($id !== '' ? ' id="' . esc($id) . '"' : '') . '>';
    foreach ($dishes as $dish) {
        $out .= dishCard($dish, ['toggle' => 'wishlist', 'active' => in_array((int) $dish['dish_id'], $savedIds, true)]);
    }
    return $out . '</ul>';
}
