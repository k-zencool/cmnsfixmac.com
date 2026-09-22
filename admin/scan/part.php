<?php
/* =========================================================
   admin/scan/part.php — scanned part label → part sheet data (JSON)

   A part label (inventory/print_labels.php) decodes to "CMNS:P-<id>". The
   scanner opens this over the live camera, same reason as job.php: a page
   load in an iOS home-screen app asks for camera permission again.

   Login only, like job.php. Sell price only — never cost (lot cost_price
   is left out on purpose; it is shop.finance data).

   GET ?id=<inventory.id>
     200 {ok:true,  part:{…}}
     200 {ok:false, reason:'notfound'}
     401 {ok:false, reason:'auth'}
   ========================================================= */
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'auth']);
    exit;
}
touch_admin_session();

$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("
    SELECT i.id, i.name, i.sku, i.image, i.type, i.status, i.part_number, i.compatible_models,
           i.location, i.min_qty, i.sell_price, i.category_id, c.name AS category_name,
           COALESCE((SELECT SUM(l.qty_remaining) FROM inventory_lots l
                     WHERE l.inventory_id = i.id AND l.qty_remaining > 0), 0) AS qty
    FROM inventory i
    LEFT JOIN parts_categories c ON c.id = i.category_id
    WHERE i.id = ?
");
$st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$id || !$row) {
    echo json_encode(['ok' => false, 'reason' => 'notfound']);
    exit;
}

$lots = $pdo->prepare("
    SELECT lot_number, qty_remaining, warranty_end, supplier_name
    FROM inventory_lots
    WHERE inventory_id = ? AND qty_remaining > 0
    ORDER BY warranty_end ASC, created_at ASC
");
$lots->execute([$id]);

$qty = (int)$row['qty'];
echo json_encode(['ok' => true, 'part' => [
    'id'          => (int)$row['id'],
    'name'        => $row['name'],
    'sku'         => $row['sku'],
    'type'        => $row['type'],
    'status'      => $row['status'],
    'image'       => $row['image'] ? '/uploads/inventory/' . rawurlencode($row['image']) : null,
    'part_number' => $row['part_number'],
    'compatible'  => array_values(array_filter(array_map('trim', explode(',', (string)$row['compatible_models'])))),
    'location'    => $row['location'],
    'category'    => $row['category_name'],
    'qty'         => $qty,
    'min_qty'     => (int)($row['min_qty'] ?? 0),
    'sell_price'  => (float)$row['sell_price'],
    'lots'        => $lots->fetchAll(PDO::FETCH_ASSOC),
    'view_url'    => '/admin/inventory/view.php?type=' . rawurlencode($row['type']) . '&q=' . rawurlencode($row['sku'] ?: $row['name']),
    // the sheet's "เบิกเข้างาน" button: NEW only (USED has its own flow), needs stock + permission
    'can_consume' => $row['type'] === 'new' && $qty > 0 && can('parts.consume'),
]], JSON_UNESCAPED_UNICODE);
