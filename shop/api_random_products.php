<?php
require_once __DIR__ . '/../includes/db.php';

$limit = 4;

try {
    $st = $pdo->query("
        SELECT sl.id
        FROM shop_listings sl
        JOIN inventory inv ON inv.id = sl.inventory_id
        WHERE sl.status = 'published' AND inv.status != 'SOLD'
    ");
    $allIds = $st->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allIds)) throw new Exception('No products available');

    shuffle($allIds);
    $randomIds = array_slice($allIds, 0, $limit);
    $in = implode(',', array_fill(0, count($randomIds), '?'));

    $sql = "
        SELECT sl.id,
               COALESCE(sl.title, inv.name)        AS title,
               sl.price,
               COALESCE(sl.cover_image, inv.image)  AS main_image,
               sc.name                              AS category
        FROM shop_listings sl
        JOIN inventory inv ON inv.id = sl.inventory_id
        JOIN shop_categories sc ON sc.id = sl.category_id
        WHERE sl.id IN ($in)
        ORDER BY FIELD(sl.id, " . implode(',', $randomIds) . ")
    ";
    $st = $pdo->prepare($sql);
    $st->execute($randomIds);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($items as $row) {
        $img = trim((string)($row['main_image'] ?? ''));
        if ($img !== '' && substr($img, 0, 1) !== '/' && !preg_match('~^https?://~', $img)) {
            $img = '/' . ltrim($img, '/');
        }
        $results[] = [
            'id'        => (int)$row['id'],
            'name'      => htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'),
            'price'     => (float)$row['price'],
            'price_fmt' => '฿' . number_format((float)$row['price'], 0),
            'img'       => htmlspecialchars($img, ENT_QUOTES, 'UTF-8'),
            'url'       => htmlspecialchars('/shop/product-detail.php?id=' . (int)$row['id'], ENT_QUOTES, 'UTF-8'),
            'category'  => htmlspecialchars((string)($row['category'] ?? ''), ENT_QUOTES, 'UTF-8'),
        ];
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'items' => $results]);

} catch (Exception $e) {
    header('Content-Type: application/json; charset=UTF-8', true, 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
