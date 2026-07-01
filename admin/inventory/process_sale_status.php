<?php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/manager_lib.php';

if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_perms_json(['shop.finance']); // ปิดการขาย/mark sold: หน้าร้าน+ ขึ้นไป

$action       = $_POST['action'] ?? '';
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

$from_status = $item['status'];

if ($action === 'mark_ready') {
    $pdo->prepare("UPDATE inventory SET status = 'READY' WHERE id = ?")->execute([$inventory_id]);

    $pdo->prepare("INSERT INTO inventory_status_log
        (inventory_id, action, from_type, to_type, from_status, to_status, created_by, admin_name)
        VALUES (?, 'status_update', 'sale', 'sale', ?, 'READY', ?, ?)")
        ->execute([$inventory_id, $from_status, $admin_id, $admin_name]);

    mgr_log($pdo, [
        'action_type' => 'sale_status', 'ref_table' => 'inventory', 'ref_id' => $inventory_id,
        'summary' => "ตั้ง {$item['name']} เป็น READY", 'reversible' => 1,
        'payload' => ['inventory_id' => $inventory_id, 'from_status' => $from_status, 'to_status' => 'READY'],
    ]);

    echo json_encode(['ok' => true, 'msg' => 'อัปเดตเป็น READY แล้ว']);

} elseif ($action === 'mark_pending') {
    $pdo->prepare("UPDATE inventory SET status = 'PENDING' WHERE id = ?")->execute([$inventory_id]);

    $pdo->prepare("INSERT INTO inventory_status_log
        (inventory_id, action, from_type, to_type, from_status, to_status, created_by, admin_name)
        VALUES (?, 'status_update', 'sale', 'sale', ?, 'PENDING', ?, ?)")
        ->execute([$inventory_id, $from_status, $admin_id, $admin_name]);

    mgr_log($pdo, [
        'action_type' => 'sale_status', 'ref_table' => 'inventory', 'ref_id' => $inventory_id,
        'summary' => "ตั้ง {$item['name']} เป็น PENDING", 'reversible' => 1,
        'payload' => ['inventory_id' => $inventory_id, 'from_status' => $from_status, 'to_status' => 'PENDING'],
    ]);

    echo json_encode(['ok' => true, 'msg' => 'อัปเดตเป็น PENDING แล้ว']);

} elseif ($action === 'mark_sold') {
    $sold_price = isset($_POST['sold_price']) && $_POST['sold_price'] !== '' ? (float)$_POST['sold_price'] : (float)$item['sell_price'];

    $pdo->prepare("UPDATE inventory SET status = 'SOLD', sell_price = ? WHERE id = ?")->execute([$sold_price, $inventory_id]);

    $pdo->prepare("INSERT INTO parts_requisitions
        (inventory_id, item_name, item_sku, qty, sell_price, requisitioned_by, admin_name, remarks)
        VALUES (?, ?, ?, 1, ?, ?, ?, 'SOLD')")
        ->execute([$inventory_id, $item['name'], $item['sku'], $sold_price, $admin_id, $admin_name]);

    $pdo->prepare("INSERT INTO inventory_status_log
        (inventory_id, action, from_type, to_type, from_status, to_status, created_by, admin_name)
        VALUES (?, 'sold', 'sale', 'sale', ?, 'SOLD', ?, ?)")
        ->execute([$inventory_id, $from_status, $admin_id, $admin_name]);

    mgr_log($pdo, [
        'action_type' => 'sale_status', 'ref_table' => 'inventory', 'ref_id' => $inventory_id,
        'summary' => "ขาย {$item['name']} ฿" . number_format($sold_price), 'amount' => $sold_price, 'reversible' => 1,
        'payload' => ['inventory_id' => $inventory_id, 'from_status' => $from_status, 'to_status' => 'SOLD', 'prev_sell_price' => (float)$item['sell_price']],
    ]);

    echo json_encode(['ok' => true, 'msg' => "ขาย {$item['name']} เรียบร้อย ฿" . number_format($sold_price)]);

} else {
    echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
}
