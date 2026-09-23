<?php
/* =========================================================
   admin/inventory/bin_api.php — storage slot data + moves (JSON)

   Used by the scanner's slot sheet (admin/templates/assets/js/bin-view.js)
   and bins.php. Reading a slot needs a login only (looking is open to every
   role, like the rest of the inventory); searching and moving need
   parts.manage, same as editing an item's location.

   GET  ?action=get&id=<bin>              {ok, bin:{…}, items:[…]}
   GET  ?action=search&id=<bin>&q=…       {ok, results:[…]}  items that fit the slot
   GET  ?action=check&id=<bin>&item=<id>  {ok, item:{…}, already, refusal}  before a scanned add
   POST action=add    id=<bin> item=<id>  {ok, bin, items} | {ok:false, msg}
   POST action=remove id=<bin> item=<id>  {ok, bin, items}
   401 {ok:false, reason:'auth'} · 403 {ok:false, msg} · 200 {ok:false, reason:'notfound'}
   ========================================================= */
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/storage_bin_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['admin_id'])) out(['ok' => false, 'reason' => 'auth'], 401);
touch_admin_session();

$action = $_POST['action'] ?? $_GET['action'] ?? 'get';
$canManage = can('parts.manage');

$bin = sbin_get($pdo, (int)($_POST['id'] ?? $_GET['id'] ?? 0));
if (!$bin) out(['ok' => false, 'reason' => 'notfound']);

function bin_payload(PDO $pdo, array $bin, bool $canManage): array {
    $types = sbin_types();
    return [
        'ok'  => true,
        'bin' => [
            'id'         => (int)$bin['id'],
            'code'       => $bin['code'],
            'shelf'      => $bin['shelf_name'] ?: ('ชั้น ' . $bin['shelf_code']),
            'slot'       => (int)$bin['slot'],
            'type'       => $bin['item_type'],
            'type_label' => $bin['item_type'] ? $types[$bin['item_type']] : null,
            'category'   => $bin['category_name'],
            'note'       => $bin['note'],
            'ready'      => (bool)($bin['item_type'] && $bin['category_id']),
            'can_manage' => $canManage,
            'page_url'   => '/admin/inventory/bins.php?bin=' . (int)$bin['id'],
        ],
        'items' => sbin_items($pdo, $bin),
    ];
}

if ($action === 'get') out(bin_payload($pdo, $bin, $canManage));

if (!$canManage) out(['ok' => false, 'msg' => 'ไม่มีสิทธิ์ย้ายของ (ต้องเป็นผู้จัดการขึ้นไป)'], 403);

/* ── search: items that fit this slot and are not in it yet ── */
if ($action === 'search') {
    $q = trim((string)($_GET['q'] ?? ''));
    if (!$bin['item_type'] || !$bin['category_id']) out(['ok' => true, 'results' => []]);

    $where  = ["i.type = ?", "COALESCE(c.parent_id, c.id) = ?", "(i.bin_id IS NULL OR i.bin_id <> ?)"];
    $params = [$bin['item_type'], (int)$bin['category_id'], (int)$bin['id']];
    if ($q !== '') {
        $where[] = "(i.name LIKE ? OR i.sku LIKE ? OR i.asset_tag LIKE ? OR i.serial_number LIKE ?)";
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }
    // not-yet-shelved first: that is what the first round of sorting is about
    $st = $pdo->prepare("
        SELECT i.id, i.name, i.sku, i.asset_tag, i.status, i.location, s.code AS shelf_code, b.slot
        FROM inventory i
        LEFT JOIN parts_categories c ON c.id = i.category_id
        LEFT JOIN storage_bins b     ON b.id = i.bin_id
        LEFT JOIN storage_shelves s  ON s.id = b.shelf_id
        WHERE " . implode(' AND ', $where) . "
          AND i.status NOT IN ('sold', 'SOLD')
        ORDER BY i.bin_id IS NOT NULL, i.asset_tag IS NULL, i.asset_tag, i.name
        LIMIT 30
    ");
    $st->execute($params);
    $res = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $res[] = [
            'id'       => (int)$r['id'],
            'name'     => $r['name'],
            'tag'      => $r['asset_tag'] ?: $r['sku'],
            'bin_code' => $r['shelf_code'] ? sbin_code($r['shelf_code'], (int)$r['slot']) : null,
            'location' => $r['location'],
        ];
    }
    out(['ok' => true, 'results' => $res]);
}

/* ── check: what a scanned label is, and whether it may go in — the
   scanner shows this and waits for a confirm before adding ── */
if ($action === 'check') {
    $item = sbin_item($pdo, (int)($_GET['item'] ?? 0));
    if (!$item) out(['ok' => false, 'reason' => 'notfound']);
    $types = sbin_types();
    $already = (int)$item['bin_id'] === (int)$bin['id'];
    out(['ok' => true,
        'item' => [
            'id'       => (int)$item['id'],
            'name'     => $item['name'],
            'tag'      => $item['asset_tag'] ?: $item['sku'],
            'serial'   => $item['serial_number'],
            'kind'     => ($types[$item['type']] ?? $item['type']) . ' · ' . ($item['root_category_name'] ?: '—'),
            'status'   => $item['status'],
            'stripped' => in_array($item['disassembly_status'], ['stripped', 'partially_stripped'], true),
            'from'     => $item['bin_code'] ?: ($item['location'] ? 'เดิม: ' . $item['location'] : 'ยังไม่เข้าช่อง'),
            'in_bin'   => (bool)$item['bin_code'],
        ],
        'already' => $already,
        'refusal' => $already ? null : sbin_refusal($bin, $item),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'msg' => 'bad request'], 400);

$itemId = (int)($_POST['item'] ?? 0);

if ($action === 'add') {
    $before = sbin_item($pdo, $itemId);
    if ($before && (int)$before['bin_id'] === (int)$bin['id']) {
        // the same label read twice — say so instead of counting it again
        out(['ok' => false, 'msg' => $before['name'] . ' อยู่ใน ' . $bin['code'] . ' แล้ว', 'already' => true]);
    }
    $why = sbin_move($pdo, $itemId, $bin);
    if ($why !== null) out(['ok' => false, 'msg' => $why]);
    $item = sbin_item($pdo, $itemId);
    out(bin_payload($pdo, sbin_get($pdo, (int)$bin['id']), $canManage) + ['moved' => $item['name']]);
}

if ($action === 'remove') {
    // only from this slot — a stale sheet must not pull an item out of the slot it was moved to since
    $item = sbin_item($pdo, $itemId);
    if ($item && (int)$item['bin_id'] === (int)$bin['id']) sbin_move($pdo, $itemId, null);
    out(bin_payload($pdo, sbin_get($pdo, (int)$bin['id']), $canManage));
}

out(['ok' => false, 'msg' => 'bad action'], 400);
