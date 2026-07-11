<?php
// process_strip.php — แยกอะไหล่จาก machine → สร้าง USED item
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/image_lib.php';
require_once __DIR__ . '/../../includes/manager_lib.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['admin_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php"); exit();
}

require_perms(['parts.manage']); // แยกเครื่องซาก: ผู้จัดการ+ เท่านั้น

$redirect_back = $_SERVER['HTTP_REFERER'] ?? 'index.php';

try {
    $source_machine_id = (int)($_POST['source_machine_id'] ?? 0);
    $name              = trim($_POST['name'] ?? '');
    $category_id       = (int)($_POST['category_id'] ?? 0);
    $sku               = trim($_POST['sku'] ?? '') ?: ('US-' . strtoupper(substr(uniqid(), -7)));
    $part_number       = trim($_POST['part_number'] ?? '');
    $serial_number     = trim($_POST['serial_number'] ?? '');
    $status            = strtoupper(trim($_POST['status'] ?? 'GOOD'));
    $condition_note    = trim($_POST['condition_note'] ?? '');
    $sell_price        = (float)($_POST['sell_price'] ?? 0);
    $cost_price        = (float)($_POST['cost_price'] ?? 0);
    $location          = trim($_POST['location'] ?? '');

    if (!$name || !$category_id || !$source_machine_id) {
        throw new Exception("กรุณากรอกข้อมูลให้ครบ");
    }

    if (!in_array($status, ['GOOD','TEST','DEAD'])) $status = 'GOOD';

    // Upload image
    $image_filename = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        $upload_dir = '../../uploads/inventory/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if (img_mime_ok($_FILES['image']['tmp_name'])) {
            $image_filename = $sku . '-' . time() . '.webp';
            if (!img_save_webp($_FILES['image']['tmp_name'], $upload_dir . $image_filename)) {
                $image_filename = null;
            }
        }
    }

    // สร้าง USED item
    $pdo->prepare("INSERT INTO inventory
        (category_id, sku, name, image, type, part_number, serial_number, location,
         source_machine_id, condition_note, status, sell_price)
        VALUES (?, ?, ?, ?, 'used', ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $category_id, $sku, $name, $image_filename,
            $part_number ?: null, $serial_number ?: null, $location ?: null,
            $source_machine_id, $condition_note ?: null,
            $status, $sell_price,
        ]);

    $inv_id = $pdo->lastInsertId();

    // สร้าง lot qty=1
    $pdo->prepare("INSERT INTO inventory_lots
        (inventory_id, lot_number, qty_received, qty_remaining, cost_price, warranty_start)
        VALUES (?, ?, 1, 1, ?, CURDATE())")
        ->execute([$inv_id, 'LOT-STRIP-' . strtoupper(substr(uniqid(), -5)), $cost_price]);

    // อัปเดต disassembly_status ของ machine เป็น partially_stripped
    $cur = $pdo->prepare("SELECT disassembly_status FROM inventory WHERE id = ?");
    $cur->execute([$source_machine_id]);
    $cur_status = $cur->fetchColumn();
    $machine_was_intact = ($cur_status === 'intact');
    if ($machine_was_intact) {
        $pdo->prepare("UPDATE inventory SET disassembly_status = 'partially_stripped' WHERE id = ?")
            ->execute([$source_machine_id]);
    }

    // log ให้ manager center (reverse ได้ถ้ายังไม่ถูกเบิก/ขาย)
    mgr_log($pdo, [
        'action_type' => 'donor_strip', 'ref_table' => 'inventory', 'ref_id' => (int)$inv_id,
        'summary' => "แยกอะไหล่ '{$name}' จากเครื่องซาก #{$source_machine_id}",
        'amount' => $sell_price ?: null, 'reversible' => 1,
        'payload' => [
            'new_inv_id' => (int)$inv_id,
            'source_machine_id' => $source_machine_id,
            'machine_was_intact' => $machine_was_intact,
        ],
    ]);

    header("Location: $redirect_back");
    exit();

} catch (Exception $e) {
    $err = urlencode($e->getMessage());
    header("Location: $redirect_back&err=$err");
    exit();
}
