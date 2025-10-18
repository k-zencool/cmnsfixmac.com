<?php
/********************************************************************
 * admin/products/index.php  (ค้นหา + ตัวกรอง + แบ่งหน้า แบบ parts/index.php)
 * - ค้นหา: ชื่อ, หมวด, SKU/รหัส, คำอธิบาย
 * - ตัวกรอง: สถานะแสดง/ไม่แสดง, หมวดหมู่, ช่วงราคา, วันที่
 * - แบ่งหน้า: ?page=, ?per= (20/50/100)
 * - RBAC-ready: require_login(), can() (ถ้ามี)
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "จัดการสินค้า/บริการ";

// =======================[ Helpers ]==================================
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k, $d = null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
/** คืนค่า [per, page, offset] */
function get_pager(): array{
  $per  = max(5, min(200, (int) getv('per', 20)));
  $page = max(1, (int) getv('page', 1));
  return [$per, $page, ($page-1)*$per];
}
/** page url คงพารามอื่นๆไว้ */
function page_url($i){
  $q = $_GET; $q['page'] = max(1,(int)$i);
  return '?'.http_build_query($q);
}
/** where LIKE หลายคอลัมน์ */
function whereSearch(string $q, array $cols, array &$params, string $pfx): ?string{
  $q = trim($q);
  if ($q === '') return null;
  $ors = []; $i = 0;
  foreach ($cols as $c) {
    $ph = ":{$pfx}{$i}";
    $ors[] = "$c LIKE $ph";
    $params[$ph] = "%{$q}%";
    $i++;
  }
  return '('.implode(' OR ', $ors).')';
}

// =======================[ State ]====================================
$q         = getv('q', '');
$status    = getv('status', '');        // '' | '1' | '0'
$cats      = isset($_GET['cat']) ? (array)$_GET['cat'] : [];  // multi
$price_min = getv('pmin',''); $price_max = getv('pmax','');
$dfrom     = getv('date_from',''); $dto = getv('date_to','');
$sort      = getv('sort','created_desc');
[$per,$page,$offset] = get_pager();

// =======================[ Category list ]============================
$catRows = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
// =======================[ Category list ]============================
$catRows = [];
try {
    $st = $pdo->query("
        SELECT DISTINCT category
        FROM products
        WHERE category IS NOT NULL AND category <> ''
        ORDER BY category
    ");

    // ดึงเป็น array คอลัมน์เดียว
    $catRows = $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
} catch (Throwable $e) {
    $catRows = [];
}

// กรองค่าแปลก ๆ ออก (กัน null/ว่าง) — ใช้ anonymous function เผื่อ PHP < 7.4
$catRows = array_values(array_filter($catRows, function($c){
    return $c !== null && $c !== '';
}));

// =======================[ Build WHERE ]==============================
$params = []; $where = [];
if ($w = whereSearch($q, ['p.name','p.category','p.sku','p.description'], $params, 'q')) $where[] = $w;

if ($status === '1') $where[] = "COALESCE(p.status,1)=1";
else if ($status === '0') $where[] = "COALESCE(p.status,1)=0";

if ($cats) {
  // whitelist จาก catRows
  $sel = array_values(array_intersect($cats, $catRows));
  if ($sel) {
    $in = [];
    foreach ($sel as $i => $c) { $ph=":c{$i}"; $params[$ph]=$c; $in[]=$ph; }
    $where[] = 'p.category IN ('.implode(',',$in).')';
  }
}
if ($price_min !== '' && is_numeric($price_min)) { $where[] = 'COALESCE(p.price,0) >= :pmin'; $params[':pmin'] = (float)$price_min; }
if ($price_max !== '' && is_numeric($price_max)) { $where[] = 'COALESCE(p.price,0) <= :pmax'; $params[':pmax'] = (float)$price_max; }
if ($dfrom !== '') { $where[] = 'DATE(p.created_at) >= :df'; $params[':df']=$dfrom; }
if ($dto   !== '') { $where[] = 'DATE(p.created_at) <= :dt'; $params[':dt']=$dto; }

$where_sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// =======================[ Sort ]=====================================
$ORDER_MAP = [
  'created_desc' => 'p.created_at DESC',
  'created_asc'  => 'p.created_at ASC',
  'price_desc'   => 'p.price DESC',
  'price_asc'    => 'p.price ASC',
  'name_asc'     => 'p.name ASC'
];
$orderBy = $ORDER_MAP[$sort] ?? $ORDER_MAP['created_desc'];

// =======================[ Count + Fetch ]============================
$stc = $pdo->prepare("SELECT COUNT(*) FROM products p {$where_sql}");
foreach ($params as $k=>$v) $stc->bindValue($k, $v);
$stc->execute();
$total = (int)($stc->fetchColumn() ?: 0);
$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) { $page = $pages; $offset = ($page-1)*$per; }

$sql = "
SELECT
  p.*,
  au.username AS admin_name
FROM products p
LEFT JOIN admin_users au ON au.id = p.admin_id
{$where_sql}
ORDER BY {$orderBy}
LIMIT :limit OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k, $v);
$st->bindValue(':limit',$per,PDO::PARAM_INT);
$st->bindValue(':off',$offset,PDO::PARAM_INT);
$st->execute();
$products = $st->fetchAll(PDO::FETCH_ASSOC);

// =======================[ Template ]=================================
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="../dashboard/index.php" class="view-site">กลับแดชบอร์ด</a>
  </div>

  <div class="section-header">
    <h2>รายการสินค้า/บริการ</h2>
    <a href="add_product.php" class="btn-primary">+ เพิ่มสินค้าใหม่</a>
  </div>

  <!-- Search & Filter -->
  <form action="index.php" method="get" class="search-and-filter-group">
    <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา ชื่อ/หมวด/รหัส/คำอธิบาย">

    <select name="status" class="filter-input" aria-label="สถานะ">
      <option value="">ทุกสถานะ</option>
      <option value="1" <?= $status==='1'?'selected':'' ?>>แสดง</option>
      <option value="0" <?= $status==='0'?'selected':'' ?>>ไม่แสดง</option>
    </select>

    <select name="sort" class="filter-input" aria-label="เรียง">
      <option value="created_desc" <?= $sort==='created_desc'?'selected':'' ?>>ล่าสุดก่อน</option>
      <option value="created_asc"  <?= $sort==='created_asc'?'selected':''  ?>>เก่าสุดก่อน</option>
      <option value="price_desc"   <?= $sort==='price_desc'?'selected':''   ?>>ราคา: มากไปน้อย</option>
      <option value="price_asc"    <?= $sort==='price_asc'?'selected':''    ?>>ราคา: น้อยไปมาก</option>
      <option value="name_asc"     <?= $sort==='name_asc'?'selected':''     ?>>ชื่อ (A→Z)</option>
    </select>

    <div class="filter-dropdown">
      <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuProd')">ตัวกรอง</button>
      <div id="filterMenuProd" class="filter-menu">
        <div class="filter-section">
          <div class="filter-title">หมวดหมู่</div>
          <?php foreach ($catRows as $c): $checked = in_array($c, $cats, true) ? 'checked' : ''; ?>
            <label class="checkline"><input type="checkbox" name="cat[]" value="<?= h($c) ?>" <?= $checked ?>><span><?= h($c) ?></span></label>
          <?php endforeach; if (!$catRows): ?>
            <div class="muted">ยังไม่มีหมวด</div>
          <?php endif; ?>
        </div>
        <div class="filter-section">
          <div class="filter-title">ราคา</div>
          <div class="range-inline">
            <input type="number" step="1" name="pmin" placeholder="ต่ำสุด" value="<?= h($price_min) ?>">
            <span class="mx-4">ถึง</span>
            <input type="number" step="1" name="pmax" placeholder="สูงสุด" value="<?= h($price_max) ?>">
          </div>
        </div>
        <div class="filter-section">
          <div class="filter-title">วันที่เพิ่ม</div>
          <div class="range-inline">
            <input type="date" name="date_from" value="<?= h($dfrom) ?>">
            <span class="mx-4">ถึง</span>
            <input type="date" name="date_to" value="<?= h($dto) ?>">
          </div>
        </div>
        <div class="filter-actions">
          <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuProd')">ล้าง</button>
          <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
        </div>
      </div>
    </div>

    <input type="hidden" name="page" value="1">
    <button class="btn-search">ค้นหา</button>
  </form>

  <!-- Table -->
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>รูป</th>
          <th>ชื่อสินค้า</th>
          <th>หมวดหมู่</th>
          <th>ผู้สร้าง</th>
          <th class="ta-r">ราคา</th>
          <th>วันที่เพิ่ม</th>
          <th>สถานะ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($products): foreach ($products as $i => $p): ?>
          <tr>
            <td><?= ($offset + $i + 1) ?></td>
            <td>
              <?php if (!empty($p['main_image'])): ?>
                <button type="button" class="thumb-btn" data-src="<?= h('../../uploads/' . $p['main_image']) ?>">
                  <img src="<?= h('../../uploads/' . $p['main_image']) ?>" class="thumb" alt="">
                </button>
              <?php else: ?><div class="thumb"></div><?php endif; ?>
            </td>
            <td><strong><?= h($p['name']) ?></strong></td>
            <td><?= h($p['category'] ?: '-') ?></td>
            <td><?= h($p['admin_name'] ?? 'N/A') ?></td>
            <td class="ta-r"><?= number_format((float)($p['price'] ?? 0), 0) ?></td>
            <td class="muted"><?= h(date('d/m/Y', strtotime($p['created_at'] ?? 'now'))) ?></td>
            <td>
              <?php $active = (int)($p['status'] ?? 1) === 1; ?>
              <span class="badge <?= $active ? '' : 'badge-gray' ?>"><?= $active ? 'แสดง' : 'ไม่แสดง' ?></span>
            </td>
            <td class="no-wrap">
              <a href="edit_product.php?id=<?= (int)$p['id'] ?>" class="btn-edit">แก้ไข</a>
              <a href="delete_product.php?id=<?= (int)$p['id'] ?>" class="btn-delete" onclick="return confirm('ลบสินค้านี้?')">ลบ</a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="9" class="text-center">ยังไม่มีสินค้า</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pager -->
  <div class="pager-bar">
    <div class="pager-left">
      <span class="pager-total">พบ <?= (int)$total ?> รายการ</span>
      <span class="divider">•</span>
      <span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span>
    </div>
    <nav class="pager-nav" aria-label="Pagination">
      <a class="page-btn <?= $page<=1?'is-disabled':''?>" href="<?= $page>1?page_url($page-1):'#'?>" rel="prev" aria-label="ก่อนหน้า">‹</a>
      <?php $start=max(1,$page-2); $end=min($pages,$page+2);
        if($start>1) echo '<span class="page-ellipsis">…</span>';
        for($i=$start;$i<=$end;$i++): ?>
        <a class="page-btn <?= $i==$page?'is-active':''?>" href="<?= page_url($i) ?>"><?= $i ?></a>
      <?php endfor; if($end<$pages) echo '<span class="page-ellipsis">…</span>'; ?>
      <a class="page-btn <?= $page>=$pages?'is-disabled':''?>" href="<?= $page<$pages?page_url($page+1):'#'?>" rel="next" aria-label="ถัดไป">›</a>
      <div class="page-size">
        <select id="ppSelect" class="pager-select" aria-label="จำนวนต่อหน้า">
          <?php foreach([20,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= (int)$per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option>
          <?php endforeach; ?>
        </select>
      </div>
    </nav>
  </div>

  <!-- Image Preview Modal -->
  <div id="imgPreviewOverlay" class="imgpv-overlay" aria-hidden="true">
    <div class="imgpv-dialog" role="dialog" aria-modal="true" aria-label="ตัวอย่างรูป">
      <button type="button" class="imgpv-close" aria-label="ปิด">✕</button>
      <img id="imgPreview" src="" alt="" class="imgpv-img">
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
  // Dropdown filter
  function toggleMenu(id){ var m=document.getElementById(id); if(m) m.classList.toggle('show'); }
  function clearMenu(id){ var root=document.getElementById(id); if(!root) return;
    root.querySelectorAll('input[type="checkbox"]').forEach(el=>el.checked=false);
    root.querySelectorAll('input[type="number"],input[type="date"]').forEach(el=>el.value='');
  }
  document.addEventListener('click', function(e){
    var dd = e.target.closest ? e.target.closest('.filter-dropdown') : null;
    document.querySelectorAll('.filter-menu.show').forEach(function(m){ if(!dd || !dd.contains(m)) m.classList.remove('show'); });
  });

  // Image preview modal
  (function(){
    var overlay=document.getElementById('imgPreviewOverlay');
    var imgEl=document.getElementById('imgPreview');
    function openPreview(src){ if(!overlay||!imgEl) return; imgEl.src=src; overlay.classList.add('show'); overlay.setAttribute('aria-hidden','false'); }
    function closePreview(){ if(!overlay) return; overlay.classList.remove('show'); overlay.setAttribute('aria-hidden','true'); imgEl.src=''; }
    document.addEventListener('click', function(e){
      var btn = e.target.closest ? e.target.closest('.thumb-btn') : null;
      if(!btn) return; var src = btn.getAttribute('data-src'); if(src) openPreview(src);
    });
    if(overlay){ overlay.addEventListener('click', function(e){ if(e.target===overlay || e.target.classList.contains('imgpv-close')) closePreview(); }); }
    document.addEventListener('keydown', function(e){ if (e.key==='Escape' && overlay && overlay.classList.contains('show')) closePreview(); });
  })();

  // Per-page selector
  (function(){
    const sel=document.getElementById('ppSelect'); if(!sel) return;
    sel.addEventListener('change', function(){
      const u = new URL(location.href);
      u.searchParams.set('per', this.value);
      u.searchParams.set('page', '1');
      location = u.toString();
    });
  })();

  // Arrow keys pager
  (function(){
    document.addEventListener('keydown', function(e){
      if (e.altKey || e.metaKey || e.ctrlKey) return;
      if (e.key === 'ArrowRight') document.querySelector('.page-btn[rel="next"]')?.click();
      if (e.key === 'ArrowLeft')  document.querySelector('.page-btn[rel="prev"]')?.click();
    });
  })();
</script>
