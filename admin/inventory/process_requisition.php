<?php
/********************************************************************
 * admin/inventory/process_requisition.php
 * AJAX: เบิกอะไหล่ใหม่ ผูกกับงานซ่อม (tracking)
 ********************************************************************/

session_start();
require_once '../../includes/db.php';
require_once '../../includes/manager_lib.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* ── GET LOTS ── */
if ($action === 'get_lots') {
    $id   = (int)($_GET['item_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT id, lot_number, qty_remaining, qty_received, cost_price,
               warranty_end, supplier_name, created_at
        FROM inventory_lots
        WHERE inventory_id = ? AND qty_remaining > 0
        ORDER BY warranty_end ASC, created_at ASC
    ");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* ── SEARCH JOBS ── */
if ($action === 'search_jobs') {
    $q    = trim($_GET['q'] ?? '');
    $rows = [];
    if (strlen($q) >= 2) {
        $stmt = $pdo->prepare("
            SELECT id, ticket_number, customer_name, customer_phone, device_type, device_model, status
            FROM tracking
            WHERE ticket_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?
            ORDER BY created_at DESC LIMIT 10
        ");
        $like = "%$q%";
        $stmt->execute([$like, $like, $like]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($rows);
    exit;
}

/* ── GET ITEM INFO ── */
if ($action === 'get_item') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT i.*, COALESCE(SUM(l.qty_remaining),0) AS total_qty
        FROM inventory i
        LEFT JOIN inventory_lots l ON i.id = l.inventory_id AND l.qty_remaining > 0
        WHERE i.id = ?
        GROUP BY i.id
    ");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}

/* ── REQUISITION ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'requisition') {
    require_perms_json(['parts.consume']); // เบิกอะไหล่: ช่าง+ ขึ้นไป
    $inventory_id = (int)($_POST['inventory_id'] ?? 0);
    $qty_req      = max(1, (int)($_POST['qty'] ?? 1));
    $tracking_id  = (int)($_POST['tracking_id'] ?? 0) ?: null;
    $ticket_num   = trim($_POST['ticket_number'] ?? '') ?: null;
    $remarks      = trim($_POST['remarks'] ?? '') ?: null;
    $admin_id     = $_SESSION['admin_id'] ?? null;
    $admin_name   = $_SESSION['admin_username'] ?? null;

    if (!$inventory_id) {
        echo json_encode(['ok' => false, 'msg' => 'ไม่พบสินค้า']);
        exit;
    }

    // Load item info
    $item = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $item->execute([$inventory_id]);
    $item = $item->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        echo json_encode(['ok' => false, 'msg' => 'ไม่พบสินค้าในระบบ']);
        exit;
    }

    // Check total available
    $avail_stmt = $pdo->prepare("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_lots WHERE inventory_id = ?");
    $avail_stmt->execute([$inventory_id]);
    $total_avail = (int)$avail_stmt->fetchColumn();

    if ($qty_req > $total_avail) {
        echo json_encode(['ok' => false, 'msg' => "สต็อกไม่พอ (มีแค่ $total_avail ชิ้น)"]);
        exit;
    }

    // กรณีเลือก lot เฉพาะ
    $specific_lot_id = (int)($_POST['lot_id'] ?? 0) ?: null;

    if ($specific_lot_id) {
        // ตรวจสอบ lot ที่เลือก
        $lot_chk = $pdo->prepare("SELECT * FROM inventory_lots WHERE id = ? AND inventory_id = ? AND qty_remaining > 0");
        $lot_chk->execute([$specific_lot_id, $inventory_id]);
        $lot_row = $lot_chk->fetch(PDO::FETCH_ASSOC);
        if (!$lot_row) {
            echo json_encode(['ok' => false, 'msg' => 'Lot ที่เลือกไม่มีสต็อกเพียงพอ']);
            exit;
        }
        if ($qty_req > $lot_row['qty_remaining']) {
            echo json_encode(['ok' => false, 'msg' => "Lot นี้มีแค่ {$lot_row['qty_remaining']} ชิ้น"]);
            exit;
        }
        $lots = [$lot_row];
    } else {
        // FIFO — deduct from oldest lots first
        $lots_stmt = $pdo->prepare("
            SELECT * FROM inventory_lots
            WHERE inventory_id = ? AND qty_remaining > 0
            ORDER BY warranty_end ASC, created_at ASC
        ");
        $lots_stmt->execute([$inventory_id]);
        $lots = $lots_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $pdo->beginTransaction();
    try {
        $remaining_to_deduct = $qty_req;
        $first_lot_id   = null;
        $avg_cost       = 0;
        $total_cost_sum = 0;
        $qty_used       = 0;
        $deductions     = []; // เก็บ lot ที่ตัดไว้สำหรับ reverse ทีหลัง

        foreach ($lots as $lot) {
            if ($remaining_to_deduct <= 0) break;

            $take = min($remaining_to_deduct, $lot['qty_remaining']);
            $pdo->prepare("UPDATE inventory_lots SET qty_remaining = qty_remaining - ? WHERE id = ?")
                ->execute([$take, $lot['id']]);

            if ($first_lot_id === null) $first_lot_id = $lot['id'];
            $deductions[]    = ['lot_id' => (int)$lot['id'], 'take' => (int)$take];
            $total_cost_sum += $lot['cost_price'] * $take;
            $qty_used       += $take;
            $remaining_to_deduct -= $take;
        }

        $avg_cost = $qty_used > 0 ? $total_cost_sum / $qty_used : 0;

        // Insert requisition record
        $pdo->prepare("
            INSERT INTO parts_requisitions
                (inventory_id, lot_id, tracking_id, ticket_number, item_name, item_sku,
                 qty, cost_price, sell_price, requisitioned_by, admin_name, remarks)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $inventory_id, $first_lot_id, $tracking_id, $ticket_num,
            $item['name'], $item['sku'],
            $qty_req, round($avg_cost, 2), $item['sell_price'],
            $admin_id, $admin_name, $remarks
        ]);
        $requisition_id = (int)$pdo->lastInsertId();

        // ── log ลงศูนย์ควบคุมผู้จัดการ (ให้ manager เห็น + ย้อนได้) ──
        $ref_label   = $ticket_num ? " → งาน {$ticket_num}" : '';
        $action_id = mgr_log($pdo, [
            'action_type' => 'requisition',
            'ref_table'   => 'parts_requisitions',
            'ref_id'      => $requisition_id,
            'summary'     => "เบิก {$item['name']} x{$qty_req}{$ref_label}",
            'amount'      => round($avg_cost * $qty_req, 2),
            'reversible'  => 1,
            'payload'     => [
                'requisition_id' => $requisition_id,
                'inventory_id'   => $inventory_id,
                'qty'            => $qty_req,
                'deductions'     => $deductions,
            ],
        ]);
        if ($action_id) {
            $pdo->prepare("UPDATE parts_requisitions SET manager_action_id = ? WHERE id = ?")
                ->execute([$action_id, $requisition_id]);
        }

        // Update inventory status if now OOS
        $avail_after = $pdo->prepare("SELECT COALESCE(SUM(qty_remaining),0) FROM inventory_lots WHERE inventory_id = ?");
        $avail_after->execute([$inventory_id]);
        if ((int)$avail_after->fetchColumn() === 0) {
            $pdo->prepare("UPDATE inventory SET status = 'OOS' WHERE id = ?")->execute([$inventory_id]);
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'msg' => "เบิก {$item['name']} x{$qty_req} เรียบร้อย"]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
