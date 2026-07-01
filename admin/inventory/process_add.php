<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/image_lib.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

require_perms(['parts.manage']); // เพิ่มสต็อก: ผู้จัดการ+ เท่านั้น

$redirect_back = $_SERVER['HTTP_REFERER'] ?? 'index.php';

try {
    $add_mode = $_POST['add_mode'] ?? 'new';

    // ---- MODE: เติมสต็อกสินค้าเดิม ----
    if ($add_mode === 'existing') {
        $inventory_id = (int)($_POST['existing_item_id'] ?? 0);
        if (!$inventory_id) throw new Exception("กรุณาเลือกสินค้า");

        $lot_number    = 'LOT-' . strtoupper(substr(uniqid(), -6));
        $qty_received  = (int)($_POST['qty_received'] ?? 1);
        $cost_price    = (float)($_POST['cost_price'] ?? 0);
        $sell_price    = (float)($_POST['sell_price'] ?? 0);
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $warranty_end  = !empty($_POST['warranty_end']) ? $_POST['warranty_end'] : null;

        $stmt = $pdo->prepare("INSERT INTO inventory_lots
            (inventory_id, lot_number, qty_received, qty_remaining, cost_price, warranty_start, warranty_end, supplier_name)
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?)");
        $stmt->execute([$inventory_id, $lot_number, $qty_received, $qty_received, $cost_price, $warranty_end, $supplier_name]);

        // อัปเดตราคาขายถ้ากรอกมา
        if ($sell_price > 0) {
            $pdo->prepare("UPDATE inventory SET sell_price = ? WHERE id = ?")->execute([$sell_price, $inventory_id]);
        }

        header("Location: $redirect_back");
        exit();
    }

    // ---- MODE: สร้างโปรไฟล์ใหม่ ----
    $name        = trim($_POST['name'] ?? '');
    $sku         = trim($_POST['sku'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $type        = $_POST['type'] ?? 'new';

    if (!$name || !$category_id) throw new Exception("กรุณากรอกข้อมูลให้ครบ");

    // Auto-generate SKU
    if (!$sku) {
        $prefix = ['new'=>'NW', 'used'=>'US', 'machine'=>'MC', 'sale'=>'SL'][$type] ?? 'IT';
        $sku = $prefix . '-' . strtoupper(substr(uniqid(), -6));
    }

    // Upload image
    $image_filename = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        $upload_dir = '../../uploads/inventory/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if (!img_mime_ok($_FILES['image']['tmp_name'])) throw new Exception("ไฟล์รูปไม่รองรับ");

        // Resize + re-encode to WebP at the source (no more raw 3–4 MB photos)
        $image_filename = $sku . '-' . time() . '.webp';
        if (!img_save_webp($_FILES['image']['tmp_name'], $upload_dir . $image_filename)) {
            $image_filename = null;
        }
    }

    // Fields ตาม type
    $part_number       = trim($_POST['part_number'] ?? '');
    $compatible_models = trim($_POST['compatible_models'] ?? '');
    $location          = trim($_POST['location'] ?? '');
    $min_qty           = (int)($_POST['min_qty'] ?? 1);
    $asset_tag         = trim($_POST['asset_tag'] ?? '');
    $serial_number     = trim($_POST['serial_number'] ?? '');
    $condition_note    = trim($_POST['condition_note'] ?? '');
    $disassembly_status = $_POST['disassembly_status'] ?? 'intact';
    $source_machine_id  = !empty($_POST['source_machine_id']) ? (int)$_POST['source_machine_id'] : null;
    $color                = trim($_POST['color'] ?? '');
    $condition_grade      = trim($_POST['condition_grade'] ?? '');
    $cpu_spec             = trim($_POST['cpu_spec'] ?? '');
    $ram_spec             = trim($_POST['ram_spec'] ?? '');
    $storage_spec         = trim($_POST['storage_spec'] ?? '');
    $gpu_spec             = trim($_POST['gpu_spec'] ?? '');
    $apple_warranty_date  = !empty($_POST['apple_warranty_date']) ? $_POST['apple_warranty_date'] : null;
    $store_warranty_days  = isset($_POST['store_warranty_days']) && $_POST['store_warranty_days'] !== '' ? (int)$_POST['store_warranty_days'] : null;
    $battery_health       = isset($_POST['battery_health']) && $_POST['battery_health'] !== '' ? (int)$_POST['battery_health'] : null;
    $battery_cycles       = isset($_POST['battery_cycles'])  && $_POST['battery_cycles']  !== '' ? (int)$_POST['battery_cycles']  : null;

    // Status default ตาม type (USED รับจาก form ได้ — GOOD หรือ TEST)
    $allowed_statuses = ['STOCK','OOS','GOOD','TEST','DEAD','READY','SOLD','PENDING'];
    $posted_status = strtoupper(trim($_POST['status'] ?? ''));
    $status = (in_array($type, ['used','sale']) && in_array($posted_status, $allowed_statuses))
        ? $posted_status
        : (['new'=>'STOCK', 'used'=>'GOOD', 'machine'=>'READY', 'sale'=>'PENDING'][$type] ?? 'STOCK');

    $sell_price = (float)($_POST['sell_price'] ?? 0);

    $stmt = $pdo->prepare("INSERT INTO inventory
        (category_id, sku, name, image, type, asset_tag, serial_number,
         part_number, compatible_models, location, min_qty, source_machine_id,
         disassembly_status, condition_note, status, sell_price,
         color, condition_grade, cpu_spec, ram_spec, storage_spec, gpu_spec,
         apple_warranty_date, store_warranty_days, battery_health, battery_cycles)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([
        $category_id, $sku, $name, $image_filename, $type,
        $asset_tag ?: null, $serial_number ?: null,
        $part_number ?: null, $compatible_models ?: null,
        $location ?: null, $min_qty, $source_machine_id,
        $disassembly_status, $condition_note ?: null,
        $status, $sell_price,
        $color ?: null, $condition_grade ?: null,
        $cpu_spec ?: null, $ram_spec ?: null,
        $storage_spec ?: null, $gpu_spec ?: null,
        $apple_warranty_date, $store_warranty_days,
        $battery_health, $battery_cycles,
    ]);

    $inventory_id = $pdo->lastInsertId();

    // Machine / Sale ไม่ใช้ lot (1 record = 1 เครื่อง)
    if ($type !== 'machine' && $type !== 'sale') {
        $lot_number    = 'LOT-' . strtoupper(substr(uniqid(), -6));
        $qty_received  = (int)($_POST['qty_received'] ?? 1);
        $cost_price    = (float)($_POST['cost_price'] ?? 0);
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $warranty_end  = !empty($_POST['warranty_end']) ? $_POST['warranty_end'] : null;

        $pdo->prepare("INSERT INTO inventory_lots
            (inventory_id, lot_number, qty_received, qty_remaining, cost_price, warranty_start, warranty_end, supplier_name)
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?)")
            ->execute([$inventory_id, $lot_number, $qty_received, $qty_received, $cost_price, $warranty_end, $supplier_name]);
    }

    header("Location: $redirect_back");
    exit();

} catch (Exception $e) {
    $err = urlencode($e->getMessage());
    header("Location: $redirect_back&err=$err");
    exit();
}
