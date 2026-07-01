<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

header('Content-Type: application/json');
require_perms_json(['content.write']); // ลบสินค้า shop: หน้าร้าน+ ขึ้นไป

$id       = (int)($_GET['id'] ?? 0);
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['admin_username'] ?? null;

if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID']); exit; }

// ดึง listing + inventory_id ก่อนลบ
$listing = $pdo->prepare("SELECT sl.id, sl.inventory_id, sl.status FROM shop_listings sl WHERE sl.id = ?");
$listing->execute([$id]);
$listing = $listing->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบรายการ']);
    exit;
}

// ป้องกันลบ listing ที่ขายไปแล้ว
if ($listing['status'] === 'sold') {
    echo json_encode(['ok' => false, 'msg' => 'รายการที่ขายไปแล้วลบไม่ได้']);
    exit;
}

$imgs = $pdo->prepare("SELECT url FROM shop_images WHERE listing_id = ?");
$imgs->execute([$id]);
$imgUrls = $imgs->fetchAll(PDO::FETCH_COLUMN);

try {
    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM shop_listings WHERE id = ?")->execute([$id]);

    // คืน inventory กลับเป็น READY (ยังเป็น type='sale' อยู่ แค่ un-list)
    if ($listing['inventory_id']) {
        $inv = $pdo->prepare("SELECT status FROM inventory WHERE id = ? AND type = 'sale' AND status != 'SOLD'");
        $inv->execute([$listing['inventory_id']]);
        $inv = $inv->fetch(PDO::FETCH_ASSOC);

        if ($inv) {
            $from_status = $inv['status'];
            $pdo->prepare("UPDATE inventory SET status = 'READY' WHERE id = ?")->execute([$listing['inventory_id']]);

            $pdo->prepare("INSERT INTO inventory_status_log
                (inventory_id, action, from_type, to_type, from_status, to_status, reference_id, created_by, admin_name, note)
                VALUES (?, 'listing_deleted', 'sale', 'sale', ?, 'READY', ?, ?, ?, 'shop listing ถูกลบ')")
                ->execute([$listing['inventory_id'], $from_status, $id, $admin_id, $admin_name]);
        }
    }

    $pdo->commit();

    // ลบไฟล์รูปออกจาก disk
    $root = realpath(__DIR__ . '/../../uploads');
    foreach ($imgUrls as $url) {
        $abs = realpath(__DIR__ . '/../../' . ltrim($url, '/'));
        if ($abs && $root && str_starts_with($abs, $root)) @unlink($abs);
    }

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
