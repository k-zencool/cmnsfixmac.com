<?php
session_start();
require_once '../../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// AJAX: ดึงข้อมูล item สำหรับฟอร์มแก้ไข
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_item') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($item ?: null);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$redirect_back = $_SERVER['HTTP_REFERER'] ?? 'index.php';

try {
    $id   = (int)($_POST['id'] ?? 0);
    if (!$id) throw new Exception("ไม่พบ ID สินค้า");

    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) throw new Exception("ไม่พบสินค้าในระบบ");

    $name              = trim($_POST['name'] ?? '');
    $sku               = trim($_POST['sku'] ?? $existing['sku']);
    $category_id       = (int)($_POST['category_id'] ?? $existing['category_id']);
    $type              = $_POST['type'] ?? $existing['type'];
    $status            = $_POST['status'] ?? $existing['status'];
    $part_number       = trim($_POST['part_number'] ?? '');
    $compatible_models = trim($_POST['compatible_models'] ?? '');
    $location          = trim($_POST['location'] ?? '');
    $min_qty           = (int)($_POST['min_qty'] ?? 1);
    $asset_tag         = trim($_POST['asset_tag'] ?? '');
    $serial_number     = trim($_POST['serial_number'] ?? '');
    $condition_note    = trim($_POST['condition_note'] ?? '');
    $disassembly_status = $_POST['disassembly_status'] ?? $existing['disassembly_status'];
    $sell_price        = (float)($_POST['sell_price'] ?? $existing['sell_price']);

    // Upload image ถ้ามีอัปโหลดใหม่
    $image_filename = $existing['image'];
    if (!empty($_FILES['image']['tmp_name'])) {
        $upload_dir = '../../uploads/inventory/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $allowed)) throw new Exception("ไฟล์รูปไม่รองรับ");

        $image_filename = $sku . '-' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_filename)) {
            $image_filename = $existing['image'];
        } else {
            // ลบรูปเก่า
            if ($existing['image'] && file_exists($upload_dir . $existing['image'])) {
                unlink($upload_dir . $existing['image']);
            }
        }
    }

    $pdo->prepare("UPDATE inventory SET
        name = ?, sku = ?, category_id = ?, type = ?, status = ?,
        part_number = ?, compatible_models = ?, location = ?, min_qty = ?,
        asset_tag = ?, serial_number = ?, condition_note = ?,
        disassembly_status = ?, sell_price = ?, image = ?
        WHERE id = ?")
        ->execute([
            $name, $sku, $category_id, $type, $status,
            $part_number ?: null, $compatible_models ?: null, $location ?: null, $min_qty,
            $asset_tag ?: null, $serial_number ?: null, $condition_note ?: null,
            $disassembly_status, $sell_price, $image_filename,
            $id
        ]);

    header("Location: $redirect_back");
    exit();

} catch (Exception $e) {
    $err = urlencode($e->getMessage());
    header("Location: $redirect_back&err=$err");
    exit();
}
