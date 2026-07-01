<?php
/**
 * process_delete.php — HARD DELETE an inventory item (ลบทั้งก้อน).
 *
 * Hard restriction: ONLY super_admin with admin_id = 1 may run this.
 * No audit/history is written by design (ลบแล้วไม่เก็บประวัติ).
 *
 * Deletes: inventory row + its inventory_lots + inventory_status_log + shop_listings.
 * Leaves parts_requisitions intact — those belong to repair jobs (deleting would
 * corrupt repair cost history).
 */
session_start();
require_once '../../includes/db.php';
require_once '../../includes/manager_lib.php';

header('Content-Type: application/json; charset=utf-8');

function out(bool $ok, string $msg): void {
    echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['admin_id'])) {
    out(false, 'ยังไม่ได้ล็อกอิน');
}

// Server-side privilege gate — id ต้องเป็น 1 เป๊ะ ห้าม role string
if ((int)$_SESSION['admin_id'] !== 1) {
    http_response_code(403);
    out(false, 'เฉพาะ super admin (id 1) เท่านั้นที่ลบได้');
}

// ถ้ากำลังสวมมุมมองยศอื่น (view-as) ห้ามลบ — ให้ตรงกับสิ่งที่ UI แสดง
if (!empty($_SESSION['view_as'])) {
    http_response_code(403);
    out(false, 'อยู่ในมุมมองยศอื่น — ออกจากมุมมองก่อนจึงจะลบได้');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 'method ไม่ถูกต้อง');
}

$inventory_id = (int)($_POST['inventory_id'] ?? 0);
if (!$inventory_id) {
    out(false, 'ไม่พบ inventory_id');
}

try {
    // ยืนยันว่ามีของจริงก่อนลบ
    $chk = $pdo->prepare("SELECT id, name, sell_price FROM inventory WHERE id = ?");
    $chk->execute([$inventory_id]);
    $del_item = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$del_item) {
        out(false, 'ไม่พบรายการนี้ (อาจถูกลบไปแล้ว)');
    }

    $pdo->beginTransaction();

    // ลบลูกๆ ก่อน แล้วค่อยลบตัวแม่ — กัน orphan row
    $pdo->prepare("DELETE FROM inventory_lots       WHERE inventory_id = ?")->execute([$inventory_id]);
    $pdo->prepare("DELETE FROM inventory_status_log WHERE inventory_id = ?")->execute([$inventory_id]);
    $pdo->prepare("DELETE FROM shop_listings        WHERE inventory_id = ?")->execute([$inventory_id]);
    $pdo->prepare("DELETE FROM inventory            WHERE id = ?")->execute([$inventory_id]);

    // log ให้ manager center เห็น (hard delete → ย้อนไม่ได้)
    mgr_log($pdo, [
        'action_type' => 'stock_delete', 'ref_table' => 'inventory', 'ref_id' => $inventory_id,
        'summary' => "ลบสต็อก: " . ($del_item['name'] ?? "#$inventory_id"),
        'amount' => isset($del_item['sell_price']) ? (float)$del_item['sell_price'] : null,
        'reversible' => 0,
        'payload' => ['inventory_id' => $inventory_id],
    ]);

    $pdo->commit();
    out(true, 'ลบทั้งก้อนเรียบร้อย');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('inventory hard delete error: ' . $e->getMessage());
    out(false, 'ลบไม่สำเร็จ: ' . $e->getMessage());
}
