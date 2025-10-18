<?php
/********************************************************************
 * admin/pricing/index.php
 * หน้า “ตารางราคาซ่อม/บริการ” ดีไซน์และ UX คล้าย parts/index.php
 * - ใช้แท็บซ้ายบนเลือกตาราง (MacBook/iMac/iPhone/iPad/Watch/AirPods/Software)
 * - ค้นหา (category / model / detail / detail_en)
 * - แสดง 20 รายการต่อหน้า + ปุ่มย้อนกลับ/ถัดไป
 * - ลบเสร็จ redirect แบบ 303 ไป URL ใหม่ (กันวนลูป)
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

/* ---------------------- Helpers ---------------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getv($k, $d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function qs(array $add = [], array $remove = []) : string {
  $q = $_GET;
  foreach ($remove as $k) unset($q[$k]);
  foreach ($add as $k=>$v) $q[$k] = $v;
  return http_build_query($q);
}

/* ---------------------- Config ----------------------- */
$TABLES = [
  'macbook_fix_pricing'    => 'MacBook',
  'imac_fix_pricing'       => 'iMac',
  'iphone_fix_pricing'     => 'iPhone',
  'ipad_fix_pricing'       => 'iPad',
  'applewatch_fix_pricing' => 'Apple Watch',
  'airpods_fix_pricing'    => 'AirPods',
  'software_fix_pricing'   => 'Software',
];

$DEFAULT_TAB = array_key_first($TABLES);

/* ----------------------- State ----------------------- */
$tab = getv('tab', $DEFAULT_TAB);
if (!isset($TABLES[$tab])) $tab = $DEFAULT_TAB;

$q        = getv('q', '');
$msg      = getv('msg', '');
$err      = getv('err', '');
$perPage  = 20;
$page     = max(1, (int)getv('p', 1));
$offset   = ($page - 1) * $perPage;

/* -------------------- Delete (GET) ------------------- */
/* ป้องกันวนลูป: หลังลบ redirect ไป URL ที่ไม่มี delete_id/from_table */
if (isset($_GET['delete_id'], $_GET['from_table'])) {
  $id  = (int)$_GET['delete_id'];
  $tbl = (string)$_GET['from_table'];

  if ($id > 0 && isset($TABLES[$tbl])) {
    $st = $pdo->prepare("DELETE FROM `{$tbl}` WHERE id=? LIMIT 1");
    $st->execute([$id]);
    header("Location: index.php?tab=" . rawurlencode($tbl) . "&msg=" . rawurlencode("ลบรายการเรียบร้อย"), true, 303);
    exit;
  } else {
    header("Location: index.php?tab=" . rawurlencode($tab) . "&err=" . rawurlencode("ลบไม่สำเร็จ"), true, 303);
    exit;
  }
}

/* ---------------------- Load data -------------------- */
$params = [];
$whereSql = '';
if ($q !== '') {
  // หมายเหตุ: ตาราง pricing โครงสร้างเดียวกัน (category/model/detail/detail_en/price)
  // ถ้าบางตารางไม่มีคอลัมน์ detail_en ให้เอาบรรทัดนั้นออก
  $whereSql = "WHERE (category LIKE :q1 OR model LIKE :q2 OR detail LIKE :q3 OR detail_en LIKE :q4)";
  $params[':q1'] = "%{$q}%";
  $params[':q2'] = "%{$q}%";
  $params[':q3'] = "%{$q}%";
  $params[':q4'] = "%{$q}%";
}

/* จำนวนทั้งหมด */
$sqlCount = "SELECT COUNT(*) FROM `{$tab}` {$whereSql}";
$stCnt = $pdo->prepare($sqlCount);
$stCnt->execute($params);
$totalRows  = (int)$stCnt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page-1)*$perPage; }

/* รายการหน้านี้ */
$sql = "SELECT *
        FROM `{$tab}`
        {$whereSql}
        ORDER BY category, model, id DESC
        LIMIT :limit OFFSET :offset";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k, $v, PDO::PARAM_STR);
$st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset,  PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------- Template --------------------- */
$pageTitle = "จัดการตารางราคาทั้งหมด";

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="../dashboard.php" class="view-site">← กลับ Dashboard</a>
  </div>

  <!-- Tabs -->
  <div class="view-switcher">
    <?php foreach ($TABLES as $tbl => $label): ?>
      <a class="switcher-item <?= $tbl === $tab ? 'active' : '' ?>"
         href="index.php?<?= qs(['tab'=>$tbl, 'p'=>1], ['q','delete_id','from_table']) ?>">
        <?= h($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Section header -->
  <div class="section-header">
    <h2>รายการราคา: <?= h($TABLES[$tab]) ?></h2>
    <a href="form.php?table=<?= h($tab) ?>" class="btn-primary">+ เพิ่มรายการใหม่</a>
  </div>

  <!-- Flash -->
  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

  <!-- Search -->
  <form action="index.php" method="get">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <div class="search-and-filter-group">
      <input class="filter-input" name="q" value="<?= h($q) ?>"
             placeholder="ค้นหา หมวด/รุ่น/รายละเอียด...">
      <button class="btn-search">ค้นหา</button>
    </div>
  </form>

  <!-- Table -->
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>หมวดหมู่</th>
          <th>รุ่น</th>
          <th>รายละเอียด (ไทย)</th>
          <th>รายละเอียด (EN)</th>
          <th>ราคา</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows): foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $offset + $i + 1 ?></td>
            <td><?= h($r['category']   ?? '') ?></td>
            <td><?= h($r['model']      ?? '') ?></td>
            <td><?= h($r['detail']     ?? '') ?></td>
            <td><?= h($r['detail_en']  ?? '') ?></td>
            <td><?= isset($r['price']) ? number_format((float)$r['price'], 0) : '' ?></td>
            <td class="no-wrap">
              <a class="btn-edit" href="form.php?table=<?= h($tab) ?>&id=<?= (int)($r['id'] ?? 0) ?>">แก้ไข</a>
              <a class="btn-delete"
                 href="index.php?<?= qs(['tab'=>$tab,'delete_id'=>(int)$r['id'],'from_table'=>$tab], ['p']) ?>"
                 onclick="return confirm('ยืนยันลบรายการนี้หรือไม่?')">ลบ</a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="7" class="text-center">ยังไม่มีข้อมูล</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="pagination">
    <div class="pagination__info">หน้า <?= $page ?> / <?= $totalPages ?></div>
    <div class="pagination__actions">
      <?php if ($page > 1): ?>
        <a class="btn-secondary" href="index.php?<?= qs(['tab'=>$tab,'p'=>$page-1], ['delete_id','from_table']) ?>">← ย้อนกลับ</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a class="btn-secondary" href="index.php?<?= qs(['tab'=>$tab,'p'=>$page+1], ['delete_id','from_table']) ?>">ถัดไป →</a>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<style>
/* เติมสไตล์เล็กน้อยให้ปุ่มแบ่งหน้า (ใช้ธีมเดียวกับ parts) */
.pagination{display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding:12px;border-radius:12px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.05)}
.pagination__info{color:#374151}
.pagination__actions{display:flex;gap:8px}
</style>
