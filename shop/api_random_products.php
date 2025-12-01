<?php
require_once __DIR__ . '/../includes/db.php'; // <--- แก้ path 'db.php' ให้ถูก

// --- Config ---
$limit = 4; // สุ่มมากี่ชิ้น

try {
    // --- 1. เอา ID ทั้งหมดที่ "ขายได้" มาก่อน ---
    $st = $pdo->query("SELECT id FROM listings WHERE status='published' AND in_stock=1");
    $allIds = $st->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allIds)) {
        throw new Exception('No products available');
    }

    // --- 2. สุ่ม ID จาก Array ที่ได้มา ---
    shuffle($allIds);
    $randomIds = array_slice($allIds, 0, $limit);

    if (empty($randomIds)) {
        throw new Exception('No random IDs found');
    }

    // --- 3. [!! REVISED !!] ยิง Query เอาข้อมูล (เพิ่ม category) ---
    $inPlaceholders = implode(',', array_fill(0, count($randomIds), '?'));
    
    $sql = "SELECT id, title, price, main_image, category
            FROM listings
            WHERE id IN ($inPlaceholders)
            ORDER BY FIELD(id, " . implode(',', $randomIds) . ")";

    $st = $pdo->prepare($sql);
    $st->execute($randomIds);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. [!! REVISED !!] จัด format ข้อมูล ---
    $results = [];
    foreach ($items as $row) {
        $img = trim((string)$row['main_image']);
        if ($img !== '' && substr($img, 0, 1) !== '/' && !preg_match('~^https?://~', $img)) {
            $img = '/' . ltrim($img, '/');
        }
        
        $category = (string)($row['category'] ?? '');

        $results[] = [
            'id'    => (int)$row['id'],
            'name'  => htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'),
            'price' => (float)$row['price'],
            'price_fmt' => '฿' . number_format((float)$row['price'], 0),
            'img'   => htmlspecialchars($img, ENT_QUOTES, 'UTF-8'), // อาจจะเป็น "" (ค่าว่าง)
            'url'   => htmlspecialchars('/product-detail.php?id=' . (int)$row['id'], ENT_QUOTES, 'UTF-8'),
            'category' => htmlspecialchars($category, ENT_QUOTES, 'UTF-8'), // ส่ง category ไปด้วย
        ];
    }

    // --- 5. ส่ง JSON กลับไป ---
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => true, 'items' => $results]);

} catch (Exception $e) {
    header('Content-Type: application/json; charset=UTF-8', true, 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;