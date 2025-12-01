<?php
/********************************************************************
 * admin/repairs/index.php — Search + Filters + Pagination
 * - ค้นหา: ชื่อผลงาน, รุ่น, คำอธิบาย (ถ้ามีคอลัมน์)
 * - กรอง: ผู้สร้าง, รุ่น, ช่วงวันที่สร้าง
 * - เรียง: ล่าสุดก่อน/เก่าสุดก่อน/ชื่อ (A→Z)
 * - แบ่งหน้า: ?page= ?per= (20/50/100)
 * - [GEMINI EDIT v1]
 * - Changed upload path to /uploads/repairs/
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "ผลงานทั้งหมด";

/* ======================= Helpers ======================= */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function get_pager(): array{
  $per  = max(5, min(200, (int)getv('per', 20)));
  $page = max(1, (int)getv('page', 1));
  return [$per, $page, ($page-1)*$per];
}
function page_url($i){
  $q = $_GET; $q['page'] = max(1,(int)$i);
  return '?'.http_build_query($q);
}
function has_column(PDO $pdo, string $table, string $col): bool{
  static $cache=[]; $k="$table.$col";
  if(isset($cache[$k])) return $cache[$k];
  $st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
  $st->execute([$table,$col]); return $cache[$k] = $st->fetchColumn()>0;
}
function whereSearch(string $q, array $cols, array &$params, string $pfx): ?string{
  $q = trim($q); if ($q==='') return null;
  $ors=[]; $i=0;
  foreach($cols as $c){ $ph=":{$pfx}{$i}"; $ors[]="$c LIKE $ph"; $params[$ph] = "%{$q}%"; $i++; }
  return '(' . implode(' OR ', $ors) . ')';
}

/* ======================= State ========================= */
$q        = getv('q','');
$admin_id = getv('admin_id','');   // single
$models   = isset($_GET['model']) ? (array)$_GET['model'] : [];
$dfrom    = getv('date_from',''); $dto = getv('date_to','');
$sort     = getv('sort','created_desc');
[$per,$page,$offset] = get_pager();

/* =================== Lookup lists ====================== */
// รายชื่อแอดมิน
$admins = [];
try{
  $admins = $pdo->query("SELECT id, username FROM admin_users ORDER BY username")
                ->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){ $admins = []; }

// รุ่น สำหรับ filter (ดึงจาก repairs.model)
$modelRows = [];
try{
  $st = $pdo->query("SELECT DISTINCT model FROM repairs WHERE model IS NOT NULL AND model<>'' ORDER BY model");
  $modelRows = $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
  $modelRows = array_values(array_filter($modelRows, function($v){ return $v!==null && $v!==''; }));
}catch(Throwable $e){ $modelRows = []; }

/* =================== Build WHERE ======================= */
$params=[]; $where=[];

// columns ที่มีจริง
$cols = ['r.title','r.model'];
if (has_column($pdo,'repairs','description')) $cols[] = 'r.description';
if ($w = whereSearch($q, $cols, $params, 'q')) $where[] = $w;

if ($admin_id !== '' && ctype_digit($admin_id)) { $where[] = 'r.admin_id = :aid'; $params[':aid'] = (int)$admin_id; }

if ($models) {
  $sel = array_values(array_intersect($models, $modelRows));
  if ($sel) {
    $in=[]; foreach($sel as $i=>$m){ $ph=":m{$i}"; $params[$ph]=$m; $in[]=$ph; }
    $where[] = 'r.model IN ('.implode(',',$in).')';
  }
}
if ($dfrom !== '') { $where[] = 'DATE(r.created_at) >= :df'; $params[':df'] = $dfrom; }
if ($dto   !== '') { $where[] = 'DATE(r.created_at) <= :dt'; $params[':dt'] = $dto; }

$where_sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* =================== Sort ============================== */
$ORDER_MAP = [
  'created_desc' => 'r.created_at DESC',
  'created_asc'  => 'r.created_at ASC',
  'title_asc'    => 'r.title ASC'
];
$orderBy = $ORDER_MAP[$sort] ?? $ORDER_MAP['created_desc'];

/* ================= Count + Fetch ======================= */
$stc = $pdo->prepare("SELECT COUNT(*) FROM repairs r {$where_sql}");
foreach($params as $k=>$v) $stc->bindValue($k,$v);
$stc->execute();
$total = (int)($stc->fetchColumn() ?: 0);
$pages = max(1, (int)ceil($total/$per));
if ($page > $pages){ $page = $pages; $offset = ($page-1)*$per; }

$sql = "
SELECT
  r.*,
  au.username AS admin_name
FROM repairs r
LEFT JOIN admin_users au ON au.id = r.admin_id
{$where_sql}
ORDER BY {$orderBy}
LIMIT :limit OFFSET :off";
$st = $pdo->prepare($sql);
foreach($params as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':limit',$per,PDO::PARAM_INT);
$st->bindValue(':off',$offset,PDO::PARAM_INT);
$st->execute();
$repairs = $st->fetchAll(PDO::FETCH_ASSOC);

/* =================== Template ========================== */
include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="section-header">
    <h2>ผลงานทั้งหมด</h2>
    <a href="add.php" class="btn-primary">+ เพิ่มผลงาน</a>
  </div>

  <form action="index.php" method="get" class="search-and-filter-group">
    <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา ชื่อผลงาน/รุ่น/คำอธิบาย">

    <select name="admin_id" class="filter-input" aria-label="ผู้สร้าง">
      <option value="">ผู้สร้างทั้งหมด</option>
      <?php foreach($admins as $ad): ?>
        <option value="<?= (int)$ad['id'] ?>" <?= ($admin_id!=='' && (int)$admin_id===(int)$ad['id'])?'selected':'' ?>>
          <?= h($ad['username']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="sort" class="filter-input" aria-label="เรียง">
      <option value="created_desc" <?= $sort==='created_desc'?'selected':'' ?>>ล่าสุดก่อน</option>
      <option value="created_asc"  <?= $sort==='created_asc' ?'selected':'' ?>>เก่าสุดก่อน</option>
      <option value="title_asc"    <?= $sort==='title_asc'   ?'selected':'' ?>>ชื่อ (A→Z)</option>
    </select>

    <div class="filter-dropdown">
      <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuRep')">ตัวกรอง</button>
      <div id="filterMenuRep" class="filter-menu">
        <div class="filter-section">
          <div class="filter-title">รุ่น</div>
          <?php foreach($modelRows as $m): $checked = in_array($m, $models, true) ? 'checked' : ''; ?>
            <label class="checkline"><input type="checkbox" name="model[]" value="<?= h($m) ?>" <?= $checked ?>><span><?= h($m) ?></span></label>
          <?php endforeach; if(!$modelRows): ?><div class="muted">ยังไม่มีรุ่น</div><?php endif; ?>
        </div>
        <div class="filter-section">
          <div class="filter-title">วันที่สร้าง</div>
          <div class="range-inline">
            <input type="date" name="date_from" value="<?= h($dfrom) ?>">
            <span class="mx-4">ถึง</span>
            <input type="date" name="date_to" value="<?= h($dto) ?>">
          </div>
        </div>
        <div class="filter-actions">
          <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuRep')">ล้าง</button>
          <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
        </div>
      </div>
    </div>

    <input type="hidden" name="page" value="1">
    <button class="btn-search">ค้นหา</button>
  </form>

  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>รูป</th>
          <th>ชื่อผลงาน</th>
          <th>รุ่น</th>
          <th>ผู้สร้าง</th>
          <th>วันที่สร้าง</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if($repairs): foreach($repairs as $i=>$r): ?>
          <tr>
            <td><?= ($offset + $i + 1) ?></td>
            <td>
              <?php if (!empty($r['image'])): ?>
                <button type="button" class="thumb-btn" data-src="<?= h('../../uploads/repairs/'.$r['image']) ?>">
                  <img src="<?= h('../../uploads/repairs/'.$r['image']) ?>" class="thumb" alt="">
                </button>
              <?php else: ?><div class="thumb"></div><?php endif; ?>
            </td>
            <td><strong><?= h($r['title'] ?? '') ?></strong></td>
            <td><?= h($r['model'] ?? '-') ?></td>
            <td><?= h($r['admin_name'] ?? 'N/A') ?></td>
            <td class="muted">
              <?php $ts = isset($r['created_at']) ? strtotime($r['created_at']) : false;
                echo $ts ? date('d/m/Y', $ts) : '-'; ?>
            </td>
            <td class="no-wrap">
              <a href="edit.php?id=<?= (int)$r['id'] ?>" class="btn-edit">แก้ไข</a>
              <a href="delete.php?id=<?= (int)$r['id'] ?>" class="btn-delete" onclick="return confirm('ยืนยันการลบใช่ไหม?')">ลบ</a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="7" class="text-center">ยังไม่มีผลงาน</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

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

  <div id="imgPreviewOverlay" class="imgpv-overlay" aria-hidden="true">
    <div class="imgpv-dialog" role="dialog" aria-modal="true" aria-label="ตัวอย่างรูป">
      <button type="button" class="imgpv-close" aria-label="ปิด">✕</button>
      <img id="imgPreview" src="" alt="" class="imgpv-img">
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
  // Dropdown
  function toggleMenu(id){ var m=document.getElementById(id); if(m) m.classList.toggle('show'); }
  function clearMenu(id){
    var root=document.getElementById(id); if(!root) return;
    root.querySelectorAll('input[type="checkbox"]').forEach(el=>el.checked=false);
    root.querySelectorAll('input[type="date"]').forEach(el=>el.value='');
  }
  document.addEventListener('click', function(e){
    var dd = e.target.closest ? e.target.closest('.filter-dropdown') : null;
    document.querySelectorAll('.filter-menu.show').forEach(m=>{ if(!dd || !dd.contains(m)) m.classList.remove('show'); });
  });

  // Modal preview
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
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && overlay && overlay.classList.contains('show')) closePreview(); });
  })();

  // Per page
  (function(){
    const sel=document.getElementById('ppSelect'); if(!sel) return;
    sel.addEventListener('change', function(){
      const u=new URL(location.href); u.searchParams.set('per',this.value); u.searchParams.set('page','1'); location = u.toString();
    });
  })();

  // Arrow keys
  (function(){
    document.addEventListener('keydown', function(e){
      if (e.altKey || e.metaKey || e.ctrlKey) return;
      if (e.key === 'ArrowRight') document.querySelector('.page-btn[rel="next"]')?.click();
      if (e.key === 'ArrowLeft')  document.querySelector('.page-btn[rel="prev"]')?.click();
    });
  })();
</script>