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

try {
    $pdo->beginTransaction();

    // ── Case 1: NEW → SALE (log: remarks = 'TRANSFER_TO_SALE:{sale_id}') ──
    $tlog = $pdo->prepare("
        SELECT * FROM parts_requisitions
        WHERE remarks = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $tlog->execute(["TRANSFER_TO_SALE:{$inventory_id}"]);
    $tlog = $tlog->fetch(PDO::FETCH_ASSOC);

    if ($tlog) {
        // คืน qty กลับ lot เดิม
        if ($tlog['lot_id']) {
            $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining + 1 WHERE id = ?")
                ->execute([$tlog['lot_id']]);
        }

        // ถ้า source item เคย OOS → คืน STOCK
        $avail = $pdo->prepare("SELECT COALESCE(SUM(qty_remaining), 0) FROM inventory_lots WHERE inventory_id = ?");
        $avail->execute([$tlog['inventory_id']]);
        if ((int)$avail->fetchColumn() > 0) {
            $pdo->prepare("UPDATE inventory SET status = 'STOCK' WHERE id = ? AND status = 'OOS'")
                ->execute([$tlog['inventory_id']]);
        }

        // ลบ log + ลบ SALE record
        $pdo->prepare("DELETE FROM parts_requisitions WHERE id = ?")->execute([$tlog['id']]);
        $pdo->prepare("DELETE FROM inventory WHERE id = ?")->execute([$inventory_id]);

        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => 'คืนสต็อก NEW เรียบร้อย']);
        exit;
    }

    // ── Case 2: USED/MACHINE → SALE (in-place, log: remarks = 'CONVERTED_TO_SALE:{type}') ──
    $clog = $pdo->prepare("
        SELECT * FROM parts_requisitions
        WHERE inventory_id = ? AND remarks LIKE 'CONVERTED_TO_SALE:%'
        ORDER BY created_at DESC LIMIT 1
    ");
    $clog->execute([$inventory_id]);
    $clog = $clog->fetch(PDO::FETCH_ASSOC);

    if ($clog) {
        $orig_type = strtolower(explode(':', $clog['remarks'])[1] ?? '');
        if (!in_array($orig_type, ['used', 'machine'])) {
            throw new Exception("ระบุประเภทเดิมไม่ได้");
        }

        $restore_status = $orig_type === 'used' ? 'GOOD' : 'READY';
        $pdo->prepare("UPDATE inventory SET type = ?, status = ? WHERE id = ?")
            ->execute([$orig_type, $restore_status, $inventory_id]);

        // USED: คืน lot qty กลับเป็น 1
        if ($orig_type === 'used') {
            $pdo->prepare("UPDATE inventory_lots SET qty_remaining = 1 WHERE inventory_id = ? AND qty_remaining = 0")
                ->execute([$inventory_id]);
        }

        $pdo->prepare("DELETE FROM parts_requisitions WHERE id = ?")->execute([$clog['id']]);

        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => "คืนกลับเป็น " . strtoupper($orig_type) . " เรียบร้อย"]);
        exit;
    }

    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบประวัติการย้าย ไม่สามารถ revert ได้']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
