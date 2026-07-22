<?php
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/image_lib.php';
require_once __DIR__ . '/../../includes/manager_lib.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// AJAX: ดึงข้อมูล item สำหรับฟอร์มแก้ไข
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_item') {
    require_perms_json(['parts.manage']); // ข้อมูลรวม cost — จำกัดสิทธิ์เดียวกับคนที่เปิด edit modal ได้
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT i.*, COALESCE(SUM(l.qty_remaining), 0) AS total_qty
        FROM inventory i
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id
        WHERE i.id = ?
        GROUP BY i.id
    ");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($item ?: null);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

require_perms(['parts.manage']); // แก้/ปรับสต็อก: ผู้จัดการ+ เท่านั้น

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

    // NEW type: คำนวณ status จาก qty จริง ไม่รับจาก POST
    if ($type === 'new') {
        $qty_stmt = $pdo->prepare("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_lots WHERE inventory_id = ?");
        $qty_stmt->execute([$id]);
        $status = (int)$qty_stmt->fetchColumn() > 0 ? 'STOCK' : 'OOS';
    } else {
        $status = $_POST['status'] ?? $existing['status'];
    }
    $part_number        = trim($_POST['part_number'] ?? '');
    $compatible_models  = trim($_POST['compatible_models'] ?? '');
    $location           = trim($_POST['location'] ?? '');
    $min_qty            = (int)($_POST['min_qty'] ?? 1);
    $asset_tag          = trim($_POST['asset_tag'] ?? '');
    $serial_number      = trim($_POST['serial_number'] ?? '');
    $source_machine_id  = !empty($_POST['source_machine_id']) ? (int)$_POST['source_machine_id'] : null;
    $condition_note     = trim($_POST['condition_note'] ?? '');
    $disassembly_status = $_POST['disassembly_status'] ?? $existing['disassembly_status'];
    $sell_price         = (float)($_POST['sell_price'] ?? $existing['sell_price']);
    $color               = trim($_POST['color']            ?? $existing['color']            ?? '');
    $condition_grade     = trim($_POST['condition_grade']  ?? $existing['condition_grade']  ?? '');
    $cpu_spec            = trim($_POST['cpu_spec']         ?? $existing['cpu_spec']         ?? '');
    $ram_spec            = trim($_POST['ram_spec']         ?? $existing['ram_spec']         ?? '');
    $storage_spec        = trim($_POST['storage_spec']     ?? $existing['storage_spec']     ?? '');
    $gpu_spec            = trim($_POST['gpu_spec']         ?? $existing['gpu_spec']         ?? '');
    $apple_warranty_date = !empty($_POST['apple_warranty_date']) ? $_POST['apple_warranty_date'] : ($existing['apple_warranty_date'] ?? null);
    $store_warranty_days = isset($_POST['store_warranty_days']) && $_POST['store_warranty_days'] !== '' ? (int)$_POST['store_warranty_days'] : ($existing['store_warranty_days'] ?? null);
    $battery_health      = isset($_POST['battery_health']) && $_POST['battery_health'] !== '' ? (int)$_POST['battery_health'] : ($existing['battery_health'] ?? null);
    $battery_cycles      = isset($_POST['battery_cycles'])  && $_POST['battery_cycles']  !== '' ? (int)$_POST['battery_cycles']  : ($existing['battery_cycles']  ?? null);

    // Upload image ถ้ามีอัปโหลดใหม่
    $image_filename = $existing['image'];
    if (!empty($_FILES['image']['tmp_name'])) {
        $upload_dir = '../../uploads/inventory/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        if (!img_mime_ok($_FILES['image']['tmp_name'])) throw new Exception("ไฟล์รูปไม่รองรับ");

        // Resize + re-encode to WebP at the source
        $image_filename = $sku . '-' . time() . '.webp';
        if (!img_save_webp($_FILES['image']['tmp_name'], $upload_dir . $image_filename)) {
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
        asset_tag = ?, serial_number = ?, source_machine_id = ?, condition_note = ?,
        disassembly_status = ?, sell_price = ?, image = ?,
        color = ?, condition_grade = ?, cpu_spec = ?, ram_spec = ?, storage_spec = ?, gpu_spec = ?,
        apple_warranty_date = ?, store_warranty_days = ?, battery_health = ?, battery_cycles = ?
        WHERE id = ?")
        ->execute([
            $name, $sku, $category_id, $type, $status,
            $part_number ?: null, $compatible_models ?: null, $location ?: null, $min_qty,
            $asset_tag ?: null, $serial_number ?: null, $source_machine_id, $condition_note ?: null,
            $disassembly_status, $sell_price, $image_filename,
            $color ?: null, $condition_grade ?: null,
            $cpu_spec ?: null, $ram_spec ?: null, $storage_spec ?: null, $gpu_spec ?: null,
            $apple_warranty_date, $store_warranty_days, $battery_health, $battery_cycles,
            $id
        ]);

    // ── ปรับสต็อก (Adjust) ──
    $adj_mode = $_POST['adjust_mode'] ?? '';
    $adj_qty  = (int)($_POST['adjust_qty'] ?? 0);
    if ($adj_mode && $adj_qty >= 0) {
        // ดึง lots ทั้งหมดเรียงใหม่สุดก่อน
        $lots_adj = $pdo->prepare("SELECT id, qty_remaining FROM inventory_lots WHERE inventory_id = ? ORDER BY created_at DESC");
        $lots_adj->execute([$id]);
        $lot_rows = $lots_adj->fetchAll(PDO::FETCH_ASSOC);

        if ($adj_mode === 'add' && $adj_qty > 0) {
            // เพิ่มใน lot ล่าสุด
            if ($lot_rows) {
                $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining + ? WHERE id = ?")->execute([$adj_qty, $lot_rows[0]['id']]);
            } else {
                // ยังไม่มี lot → สร้างใหม่
                $pdo->prepare("INSERT INTO inventory_lots (inventory_id,lot_number,qty_received,qty_remaining,cost_price) VALUES (?,?,?,?,0)")->execute([$id,'LOT-ADJ-'.strtoupper(substr(uniqid(),-5)),$adj_qty,$adj_qty]);
            }
        } elseif ($adj_mode === 'sub' && $adj_qty > 0) {
            // ลด FIFO จาก lot เก่าสุด
            $rem = $adj_qty;
            $lots_fifo = $pdo->prepare("SELECT id, qty_remaining FROM inventory_lots WHERE inventory_id = ? AND qty_remaining > 0 ORDER BY created_at ASC");
            $lots_fifo->execute([$id]);
            foreach ($lots_fifo->fetchAll(PDO::FETCH_ASSOC) as $lr) {
                if ($rem <= 0) break;
                $take = min($rem, $lr['qty_remaining']);
                $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining - ? WHERE id = ?")->execute([$take, $lr['id']]);
                $rem -= $take;
            }
        } elseif ($adj_mode === 'set') {
            if ($adj_qty === 0) {
                // ตั้งค่าเป็น 0 → clear ทุก lot โดยตรง
                $pdo->prepare("UPDATE inventory_lots SET qty_remaining = 0 WHERE inventory_id = ?")->execute([$id]);
            } else {
                $cur_sum = array_sum(array_column($lot_rows, 'qty_remaining'));
                $diff = $adj_qty - $cur_sum;
                if ($diff > 0 && $lot_rows) {
                    $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining + ? WHERE id = ?")->execute([$diff, $lot_rows[0]['id']]);
                } elseif ($diff < 0) {
                    $rem = abs($diff);
                    $lots_fifo2 = $pdo->prepare("SELECT id, qty_remaining FROM inventory_lots WHERE inventory_id = ? AND qty_remaining > 0 ORDER BY created_at ASC");
                    $lots_fifo2->execute([$id]);
                    foreach ($lots_fifo2->fetchAll(PDO::FETCH_ASSOC) as $lr) {
                        if ($rem <= 0) break;
                        $take = min($rem, $lr['qty_remaining']);
                        $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining - ? WHERE id = ?")->execute([$take, $lr['id']]);
                        $rem -= $take;
                    }
                }
            }
        }
    }

    // Re-sync status สำหรับ NEW type ทุกครั้งที่ save
    if ($type === 'new') {
        $qty_after = $pdo->prepare("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_lots WHERE inventory_id = ?");
        $qty_after->execute([$id]);
        $status_after = (int)$qty_after->fetchColumn() > 0 ? 'STOCK' : 'OOS';
        $pdo->prepare("UPDATE inventory SET status = ? WHERE id = ?")->execute([$status_after, $id]);
    }

    // ── เพิ่ม Lot ใหม่ถ้ากรอก qty_received ──
    $rs_qty = (int)($_POST['qty_received'] ?? 0);
    if ($rs_qty > 0) {
        $lot_number  = trim($_POST['lot_number'] ?? '') ?: ('LOT-' . strtoupper(substr(uniqid(), -6)));
        $cost_price  = (float)($_POST['cost_price']   ?? 0);
        $supplier    = trim($_POST['supplier_name'] ?? '');
        $warranty    = !empty($_POST['warranty_end']) ? $_POST['warranty_end'] : null;

        $pdo->prepare("
            INSERT INTO inventory_lots
                (inventory_id, lot_number, qty_received, qty_remaining, cost_price, warranty_end, supplier_name)
            VALUES (?,?,?,?,?,?,?)
        ")->execute([$id, $lot_number, $rs_qty, $rs_qty, $cost_price, $warranty, $supplier ?: null]);

        // update status เป็น STOCK ถ้าเคย OOS
        if ($status === 'OOS' || $existing['status'] === 'OOS') {
            $pdo->prepare("UPDATE inventory SET status = 'STOCK' WHERE id = ?")->execute([$id]);
        }
    }

    // ── log ให้ manager center เห็น (แก้ field/ปรับสต็อก reverse อัตโนมัติไม่ได้) ──
    $adj_txt = ($adj_mode && $adj_qty > 0) ? " | ปรับสต็อก {$adj_mode} {$adj_qty}" : '';
    $price_txt = ((float)$existing['sell_price'] != $sell_price) ? " | ราคา ฿" . number_format((float)$existing['sell_price']) . "→฿" . number_format($sell_price) : '';
    mgr_log($pdo, [
        'action_type' => 'stock_edit', 'ref_table' => 'inventory', 'ref_id' => $id,
        'summary' => "แก้สต็อก: {$name}{$price_txt}{$adj_txt}", 'amount' => $sell_price,
        'reversible' => 0,
        'payload' => ['inventory_id' => $id, 'old' => [
            'name' => $existing['name'], 'sell_price' => $existing['sell_price'], 'status' => $existing['status'],
        ]],
    ]);

    header("Location: $redirect_back");
    exit();

} catch (Exception $e) {
    $err = urlencode($e->getMessage());
    header("Location: $redirect_back&err=$err");
    exit();
}
