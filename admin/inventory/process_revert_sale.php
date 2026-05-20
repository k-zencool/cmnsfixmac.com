<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$inventory_id = (int)($_POST['inventory_id'] ?? 0);
$admin_id     = $_SESSION['admin_id'] ?? null;
$admin_name   = $_SESSION['admin_username'] ?? null;

if (!$inventory_id) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบ ID']);
    exit;
}

$item = $pdo->prepare("SELECT * FROM inventory WHERE id = ? AND type = 'sale'");
$item->execute([$inventory_id]);
$item = $item->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบสินค้า SALE']);
    exit;
}
if ($item['status'] === 'SOLD') {
    echo json_encode(['ok' => false, 'msg' => 'สินค้าที่ขายไปแล้ว revert ไม่ได้']);
    exit;
}

// ตรวจสอบว่า item นี้มี shop listing อยู่หรือเปล่า
$has_listing = $pdo->prepare("SELECT id FROM shop_listings WHERE inventory_id = ? LIMIT 1");
$has_listing->execute([$inventory_id]);
if ($has_listing->fetch()) {
    echo json_encode(['ok' => false, 'msg' => 'ลบ Shop Listing ออกก่อนค่อย revert']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ── Case 1: NEW → SALE (สร้าง record ใหม่, original_type = NULL) ──
    $tlog = $pdo->prepare("
        SELECT * FROM parts_requisitions
        WHERE remarks = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $tlog->execute(["TRANSFER_TO_SALE:{$inventory_id}"]);
    $tlog = $tlog->fetch(PDO::FETCH_ASSOC);

    if ($tlog) {
        if ($tlog['lot_id']) {
            $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining + 1 WHERE id = ?")
                ->execute([$tlog['lot_id']]);
        }

        $avail = $pdo->prepare("SELECT COALESCE(SUM(qty_remaining), 0) FROM inventory_lots WHERE inventory_id = ?");
        $avail->execute([$tlog['inventory_id']]);
        if ((int)$avail->fetchColumn() > 0) {
            $pdo->prepare("UPDATE inventory SET status = 'STOCK' WHERE id = ? AND status = 'OOS'")
                ->execute([$tlog['inventory_id']]);
        }

        // Audit log ก่อนลบ
        $pdo->prepare("INSERT INTO inventory_status_log
            (inventory_id, action, from_type, to_type, from_status, to_status, reference_id, created_by, admin_name)
            VALUES (?, 'revert_sale', 'sale', 'new', ?, 'STOCK', ?, ?, ?)")
            ->execute([$inventory_id, $item['status'], $tlog['inventory_id'], $admin_id, $admin_name]);

        $pdo->prepare("DELETE FROM parts_requisitions WHERE id = ?")->execute([$tlog['id']]);
        $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$inventory_id]);

        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => 'คืนสต็อก NEW เรียบร้อย']);
        exit;
    }

    // ── Case 2: USED/MACHINE → SALE (in-place, อ่าน original_type จาก column โดยตรง) ──
    $orig_type   = $item['original_type']   ?? null;
    $orig_status = $item['original_status'] ?? null;

    // Fallback: ถ้าเป็น record เก่าที่ยังไม่มี original_type ให้ parse จาก remarks
    if (!$orig_type) {
        $clog = $pdo->prepare("
            SELECT * FROM parts_requisitions
            WHERE inventory_id = ? AND remarks LIKE 'CONVERTED_TO_SALE:%'
            ORDER BY created_at DESC LIMIT 1
        ");
        $clog->execute([$inventory_id]);
        $clog = $clog->fetch(PDO::FETCH_ASSOC);

        if ($clog) {
            $orig_type   = strtolower(explode(':', $clog['remarks'])[1] ?? '');
            $orig_status = null;
            if ($clog) $pdo->prepare("DELETE FROM parts_requisitions WHERE id = ?")->execute([$clog['id']]);
        }
    }

    if (!$orig_type || !in_array($orig_type, ['used', 'machine'])) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => 'ไม่พบข้อมูลประเภทเดิม ไม่สามารถ revert ได้']);
        exit;
    }

    $restore_status = $orig_status ?? ($orig_type === 'used' ? 'GOOD' : 'READY');

    $pdo->prepare("UPDATE inventory SET
        type = ?, status = ?, original_type = NULL, original_status = NULL
        WHERE id = ?")
        ->execute([$orig_type, $restore_status, $inventory_id]);

    if ($orig_type === 'used') {
        $pdo->prepare("UPDATE inventory_lots SET qty_remaining = 1 WHERE inventory_id = ? AND qty_remaining = 0")
            ->execute([$inventory_id]);
    }

    // Audit log
    $pdo->prepare("INSERT INTO inventory_status_log
        (inventory_id, action, from_type, to_type, from_status, to_status, created_by, admin_name)
        VALUES (?, 'revert_sale', 'sale', ?, ?, ?, ?, ?)")
        ->execute([$inventory_id, $orig_type, $item['status'], $restore_status, $admin_id, $admin_name]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'msg' => "คืนกลับเป็น " . strtoupper($orig_type) . " เรียบร้อย"]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
