<?php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/manager_lib.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// GET: ดึงข้อมูล item สำหรับ pre-fill modal
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get_item') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT i.*, COALESCE(SUM(l.qty_remaining), 0) AS total_qty
        FROM inventory i
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id AND l.qty_remaining > 0
        WHERE i.id = ?
        GROUP BY i.id
    ");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

require_perms_json(['shop.finance']); // เอาขึ้นขาย: หน้าร้าน+ ขึ้นไป

$source_type  = $_POST['source_type'] ?? '';
$inventory_id = (int)($_POST['inventory_id'] ?? 0);
$lot_id       = (int)($_POST['lot_id'] ?? 0) ?: null;
$qty_transfer = max(1, (int)($_POST['qty'] ?? 1));
$admin_id     = $_SESSION['admin_id'] ?? null;
$admin_name   = $_SESSION['admin_username'] ?? null;

$name               = trim($_POST['name'] ?? '');
$sell_price         = (float)($_POST['sell_price'] ?? 0);
$status             = in_array($_POST['status'] ?? '', ['READY', 'PENDING']) ? $_POST['status'] : 'PENDING';
$condition_grade    = trim($_POST['condition_grade'] ?? '') ?: null;
$serial_number      = trim($_POST['serial_number'] ?? '') ?: null;
$asset_tag          = trim($_POST['asset_tag'] ?? '') ?: null;
$color              = trim($_POST['color'] ?? '') ?: null;
$condition_note     = trim($_POST['condition_note'] ?? '') ?: null;
$cpu_spec           = trim($_POST['cpu_spec'] ?? '') ?: null;
$ram_spec           = trim($_POST['ram_spec'] ?? '') ?: null;
$storage_spec       = trim($_POST['storage_spec'] ?? '') ?: null;
$apple_warranty_date = !empty($_POST['apple_warranty_date']) ? $_POST['apple_warranty_date'] : null;
$store_warranty_days = isset($_POST['store_warranty_days']) && $_POST['store_warranty_days'] !== '' ? (int)$_POST['store_warranty_days'] : null;
$battery_health     = isset($_POST['battery_health'])  && $_POST['battery_health']  !== '' ? (int)$_POST['battery_health']  : null;
$battery_cycles     = isset($_POST['battery_cycles'])  && $_POST['battery_cycles']  !== '' ? (int)$_POST['battery_cycles']  : null;

if (!$inventory_id || !$name || !in_array($source_type, ['new', 'used', 'machine'])) {
    echo json_encode(['ok' => false, 'msg' => 'ข้อมูลไม่ครบ']);
    exit;
}

try {
    $src = $pdo->prepare("SELECT * FROM inventory WHERE id = ? AND type = ?");
    $src->execute([$inventory_id, $source_type]);
    $src_item = $src->fetch(PDO::FETCH_ASSOC);

    if (!$src_item) {
        echo json_encode(['ok' => false, 'msg' => 'ไม่พบสินค้าต้นทาง']);
        exit;
    }

    $pdo->beginTransaction();
    $new_sale_id = null;

    // ──────────────────────────────────────────────────
    // NEW → SALE: ตัด qty จาก lot แล้วสร้าง record ใหม่
    // ──────────────────────────────────────────────────
    if ($source_type === 'new') {
        if ($lot_id) {
            $lot_stmt = $pdo->prepare("SELECT * FROM inventory_lots WHERE id = ? AND inventory_id = ? AND qty_remaining > 0");
            $lot_stmt->execute([$lot_id, $inventory_id]);
            $lot = $lot_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lot) throw new Exception("Lot ที่เลือกไม่มีสต็อก");
            if ($qty_transfer > $lot['qty_remaining']) throw new Exception("จำนวนเกินที่มีใน Lot ({$lot['qty_remaining']} ชิ้น)");
        } else {
            $lot_stmt = $pdo->prepare("SELECT * FROM inventory_lots WHERE inventory_id = ? AND qty_remaining > 0 ORDER BY warranty_end ASC, created_at ASC LIMIT 1");
            $lot_stmt->execute([$inventory_id]);
            $lot = $lot_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lot) throw new Exception("ไม่มีสต็อกเหลือ");
            if ($qty_transfer > $lot['qty_remaining']) throw new Exception("จำนวนเกินที่มีใน Lot ({$lot['qty_remaining']} ชิ้น)");
        }

        $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining - ? WHERE id = ?")->execute([$qty_transfer, $lot['id']]);

        $avail = $pdo->prepare("SELECT COALESCE(SUM(qty_remaining), 0) FROM inventory_lots WHERE inventory_id = ?");
        $avail->execute([$inventory_id]);
        if ((int)$avail->fetchColumn() === 0) {
            $pdo->prepare("UPDATE inventory SET status = 'OOS' WHERE id = ?")->execute([$inventory_id]);
        }

        $new_sku = 'SL-' . strtoupper(substr(uniqid(), -7));
        $pdo->prepare("INSERT INTO inventory
            (category_id, sku, name, type, serial_number, asset_tag, color, condition_note,
             condition_grade, cpu_spec, ram_spec, storage_spec,
             apple_warranty_date, store_warranty_days, battery_health, battery_cycles,
             status, sell_price)
            VALUES (?, ?, ?, 'sale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $src_item['category_id'], $new_sku, $name,
                $serial_number, $asset_tag, $color, $condition_note,
                $condition_grade, $cpu_spec, $ram_spec, $storage_spec,
                $apple_warranty_date, $store_warranty_days, $battery_health, $battery_cycles,
                $status, $sell_price,
            ]);
        $new_sale_id = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO parts_requisitions
            (inventory_id, lot_id, item_name, item_sku, qty, cost_price, sell_price, requisitioned_by, admin_name, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $inventory_id, $lot['id'],
                $src_item['name'], $src_item['sku'],
                $qty_transfer,
                $lot['cost_price'] ?? 0, $sell_price,
                $admin_id, $admin_name,
                "TRANSFER_TO_SALE:{$new_sale_id}",
            ]);

        // Audit log
        $pdo->prepare("INSERT INTO inventory_status_log
            (inventory_id, action, from_type, to_type, from_status, to_status, reference_id, created_by, admin_name)
            VALUES (?, 'to_sale', 'new', 'sale', ?, 'READY', ?, ?, ?)")
            ->execute([$new_sale_id, $status, $inventory_id, $admin_id, $admin_name]);

    // ──────────────────────────────────────────────────
    // USED / MACHINE → SALE: convert in place
    // ──────────────────────────────────────────────────
    } else {
        $orig_type   = $src_item['type'];
        $orig_status = $src_item['status'];

        $pdo->prepare("UPDATE inventory SET
            type = 'sale', original_type = ?, original_status = ?,
            name = ?, sell_price = ?, status = ?,
            condition_grade = ?, serial_number = ?, asset_tag = ?, color = ?, condition_note = ?,
            cpu_spec = ?, ram_spec = ?, storage_spec = ?,
            apple_warranty_date = ?, store_warranty_days = ?, battery_health = ?, battery_cycles = ?
            WHERE id = ?")
            ->execute([
                $orig_type, $orig_status,
                $name, $sell_price, $status,
                $condition_grade, $serial_number, $asset_tag, $color, $condition_note,
                $cpu_spec, $ram_spec, $storage_spec,
                $apple_warranty_date, $store_warranty_days, $battery_health, $battery_cycles,
                $inventory_id,
            ]);

        if ($source_type === 'used') {
            $pdo->prepare("UPDATE inventory_lots SET qty_remaining = 0 WHERE inventory_id = ?")->execute([$inventory_id]);
        }

        $pdo->prepare("INSERT INTO parts_requisitions
            (inventory_id, item_name, item_sku, qty, sell_price, requisitioned_by, admin_name, remarks)
            VALUES (?, ?, ?, 1, ?, ?, ?, ?)")
            ->execute([
                $inventory_id, $src_item['name'], $src_item['sku'],
                $sell_price, $admin_id, $admin_name,
                "CONVERTED_TO_SALE:{$source_type}",
            ]);

        // Audit log
        $pdo->prepare("INSERT INTO inventory_status_log
            (inventory_id, action, from_type, to_type, from_status, to_status, created_by, admin_name)
            VALUES (?, 'to_sale', ?, 'sale', ?, ?, ?, ?)")
            ->execute([$inventory_id, $orig_type, $orig_status, $status, $admin_id, $admin_name]);

        $new_sale_id = $inventory_id;
    }

    // log ให้ manager เห็น (reverse ใช้ปุ่ม "คืนสต็อก" ในหน้า inventory)
    mgr_log($pdo, [
        'action_type' => 'to_sale', 'ref_table' => 'inventory', 'ref_id' => $new_sale_id,
        'summary' => "เอา {$name} ขึ้นขาย ฿" . number_format($sell_price), 'amount' => $sell_price,
        'reversible' => 0,
        'payload' => ['inventory_id' => $new_sale_id, 'source_type' => $source_type, 'qty' => $qty_transfer],
    ]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'msg' => "ย้ายไป SALE เรียบร้อย", 'sale_id' => $new_sale_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
