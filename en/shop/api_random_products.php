<?php
// [!! GEMINI-FIX !!] แก้ Path ตรงนี้!! ถ้าไฟล์นี้อยู่ใน /en/shop/ ต้องถอย 2 ชั้น!!
require_once __DIR__ . '/../../includes/db.php';

// --- Config ---
$limit = 4; // How many items to fetch

try {
    // --- 1. Get all available product IDs ---
    $st = $pdo->query("SELECT id FROM listings WHERE status='published' AND in_stock=1");
    $allIds = $st->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allIds)) {
        // [GEMINI-EN-FIX]
        throw new Exception('No products available');
    }

    // --- 2. Shuffle and pick random IDs ---
    shuffle($allIds);
    $randomIds = array_slice($allIds, 0, $limit);

    if (empty($randomIds)) {
        // [GEMINI-EN-FIX]
        throw new Exception('No random IDs found');
    }

    // --- 3. Fetch product data ---
    $inPlaceholders = implode(',', array_fill(0, count($randomIds), '?'));

    // [GEMINI-NOTE] Select 'title_en', 'category_en' if you have them in your DB
    $sql = "SELECT id, title, price, main_image, category
            FROM listings
            WHERE id IN ($inPlaceholders)
            ORDER BY FIELD(id, " . implode(',', $randomIds) . ")"; // Keep original random order

    $st = $pdo->prepare($sql);
    $st->execute($randomIds);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. Format results ---
    $results = [];
    foreach ($items as $row) {
        $img = trim((string)$row['main_image']);
        if ($img !== '' && substr($img, 0, 1) !== '/' && !preg_match('~^https?://~', $img)) {
            $img = '/' . ltrim($img, '/');
        }

        $category = (string)($row['category'] ?? ''); // Assumes EN from DB or Thai is ok

        $results[] = [
            'id'    => (int)$row['id'],
            'name'  => htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'), // Assumes EN from DB
            'price' => (float)$row['price'],
            'price_fmt' => '฿' . number_format((float)$row['price'], 0), // Keep Baht format
            'img'   => htmlspecialchars($img, ENT_QUOTES, 'UTF-8'),
            // [GEMINI-EN-FIX] Point URL to the English product page
            'url'   => htmlspecialchars('/en/shop/product-detail.php?id=' . (int)$row['id'], ENT_QUOTES, 'UTF-8'),
            'category' => htmlspecialchars($category, ENT_QUOTES, 'UTF-8'),
        ];
    }

    // --- 5. Return JSON ---
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'items' => $results]);

} catch (Exception $e) {
    header('Content-Type: application/json; charset=UTF-8', true, 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;