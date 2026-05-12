<?php

/********************************************************************
 * admin/articles/index.php — Search + Filters + Pagination
 * - ค้นหา: ชื่อบทความ, หมวด, เนื้อหา/คำอธิบาย (ถ้ามีคอลัมน์)
 * - กรอง: สถานะ แสดง/ซ่อน, หมวด, ช่วงวันที่
 * - เรียง: ล่าสุดก่อน/เก่าสุดก่อน/ชื่อ (A→Z)
 * - แบ่งหน้า: ?page= ?per= (20/50/100)
 * - [GEMINI EDIT v2]
 * - Fixed image path to read root-relative path from DB
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "จัดการบทความ";

/* =============== Helpers =============== */
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function getv($k, $d = null)
{
  return isset($_GET[$k]) ? trim($_GET[$k]) : $d;
}
function get_pager(): array
{
  $per  = max(5, min(200, (int)getv('per', 20)));
  $page = max(1, (int)getv('page', 1));
  return [$per, $page, ($page - 1) * $per];
}
function page_url($i)
{
  $q = $_GET;
  $q['page'] = max(1, (int)$i);
  return '?' . http_build_query($q);
}
function has_column(PDO $pdo, string $table, string $col): bool
{
  static $cache = [];
  $k = "$table.$col";
  if (isset($cache[$k])) return $cache[$k];
  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
  $st->execute([$table, $col]);
  return $cache[$k] = $st->fetchColumn() > 0;
}
function whereSearch(string $q, array $cols, array &$params, string $pfx): ?string
{
  $q = trim($q);
  if ($q === '') return null;
  $ors = [];
  $i = 0;
  foreach ($cols as $c) {
    $ph = ":{$pfx}{$i}";
    $ors[] = "$c LIKE $ph";
    $params[$ph] = "%{$q}%";
    $i++;
  }
  return '(' . implode(' OR ', $ors) . ')';
}

/* =============== State =============== */
$q       = getv('q', '');
$status  = getv('status', '');  // '' | '1' | '0'
$cats    = isset($_GET['cat']) ? (array)$_GET['cat'] : [];
$dfrom   = getv('date_from', '');
$dto = getv('date_to', '');
$sort    = getv('sort', 'created_desc');
[$per, $page, $offset] = get_pager();

/* =============== Category list =============== */
$catRows = [];
try {
  $st = $pdo->query("SELECT DISTINCT category FROM articles WHERE category IS NOT NULL AND category<>'' ORDER BY category");
  $catRows = $st ? $st->fetchAll(PDO::FETCH_COLUMN, 0) : [];
  // กัน null/ว่าง เผื่อข้อมูลเน่า
  $catRows = array_values(array_filter($catRows, function ($c) {
    return $c !== null && $c !== '';
  }));
} catch (Throwable $e) {
  $catRows = [];
}

/* =============== Build WHERE =============== */
$params = [];
$where = [];

$cols = ['a.title', 'a.category'];
foreach (['content', 'body', 'description', 'excerpt', 'tags'] as $optCol) {
  if (has_column($pdo, 'articles', $optCol)) $cols[] = "a.$optCol";
}
if ($w = whereSearch($q, $cols, $params, 'q')) $where[] = $w;

if ($status === '1') $where[] = "COALESCE(a.status,1)=1";
else if ($status === '0') $where[] = "COALESCE(a.status,1)=0";

if ($cats) {
  $sel = array_values(array_intersect($cats, $catRows));
  if ($sel) {
    $in = [];
    foreach ($sel as $i => $c) {
      $ph = ":c{$i}";
      $params[$ph] = $c;
      $in[] = $ph;
    }
    $where[] = 'a.category IN (' . implode(',', $in) . ')';
  }
}
if ($dfrom !== '') {
  $where[] = 'DATE(a.created_at) >= :df';
  $params[':df'] = $dfrom;
}
if ($dto   !== '') {
  $where[] = 'DATE(a.created_at) <= :dt';
  $params[':dt'] = $dto;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* =============== Sort =============== */
$ORDER_MAP = [
  'created_desc' => 'a.created_at DESC',
  'created_asc'  => 'a.created_at ASC',
  'title_asc'    => 'a.title ASC'
];
$orderBy = $ORDER_MAP[$sort] ?? $ORDER_MAP['created_desc'];

/* =============== Stats =============== */
$stats = $pdo->query("
  SELECT
    COUNT(*) AS total,
    SUM(status = 1) AS published,
    SUM(MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) AS this_month,
    SUM(slug IS NOT NULL AND slug != '') AS has_slug
  FROM articles
")->fetch(PDO::FETCH_ASSOC);

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

/* =============== Count + Fetch =============== */
$stc = $pdo->prepare("SELECT COUNT(*) FROM articles a {$where_sql}");
foreach ($params as $k => $v) $stc->bindValue($k, $v);
$stc->execute();
$total = (int)($stc->fetchColumn() ?: 0);
$pages = max(1, (int)ceil($total / $per));
if ($page > $pages) {
  $page = $pages;
  $offset = ($page - 1) * $per;
}

$sql = "
SELECT
  a.*,
  au.username AS admin_name
FROM articles a
LEFT JOIN admin_users au ON au.id = a.admin_id
{$where_sql}
ORDER BY {$orderBy}
LIMIT :limit OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':limit', $per, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$articles = $st->fetchAll(PDO::FETCH_ASSOC);

/* =============== Template =============== */
include __DIR__ . '/../templates/header_admin.php';

?>
<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="section-header">
    <h2>บทความทั้งหมด</h2>
    <button type="button" class="cmns-btn cmns-btn-primary" onclick="openArticleModal('add.php?modal=1')">
      <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">add</span> เพิ่มบทความ
    </button>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
    <div class="stat-card" style="border-left:4px solid #3b82f6">
      <div class="stat-card-num"><?= (int)($stats['total'] ?? 0) ?></div>
      <div class="stat-card-label">บทความทั้งหมด</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #10b981">
      <div class="stat-card-num"><?= (int)($stats['published'] ?? 0) ?></div>
      <div class="stat-card-label">เผยแพร่แล้ว</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #f59e0b">
      <div class="stat-card-num"><?= (int)($stats['this_month'] ?? 0) ?></div>
      <div class="stat-card-label">เดือนนี้</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #8b5cf6">
      <div class="stat-card-num"><?= (int)($stats['has_slug'] ?? 0) ?></div>
      <div class="stat-card-label">มี Slug / SEO</div>
    </div>
  </div>

  <form action="index.php" method="get" class="search-and-filter-group">
    <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา ชื่อ/หมวด/เนื้อหา">
    <select name="status" class="filter-input" aria-label="สถานะ">
      <option value="">ทุกสถานะ</option>
      <option value="1" <?= $status === '1' ? 'selected' : '' ?>>เผยแพร่</option>
      <option value="0" <?= $status === '0' ? 'selected' : '' ?>>ซ่อน</option>
    </select>
    <select name="sort" class="filter-input" aria-label="เรียง">
      <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>ล่าสุดก่อน</option>
      <option value="created_asc" <?= $sort === 'created_asc' ? 'selected' : '' ?>>เก่าสุดก่อน</option>
      <option value="title_asc" <?= $sort === 'title_asc'   ? 'selected' : '' ?>>ชื่อ (A→Z)</option>
    </select>

    <div class="filter-dropdown">
      <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuArticles')">ตัวกรอง</button>
      <div id="filterMenuArticles" class="filter-menu">
        <div class="filter-section">
          <div class="filter-title">หมวดหมู่</div>
          <?php foreach ($catRows as $c): $checked = in_array($c, $cats, true) ? 'checked' : ''; ?>
            <label class="checkline"><input type="checkbox" name="cat[]" value="<?= h($c) ?>" <?= $checked ?>><span><?= h($c) ?></span></label>
          <?php endforeach;
          if (!$catRows): ?><div class="muted">ยังไม่มีหมวด</div><?php endif; ?>
        </div>
        <div class="filter-section">
          <div class="filter-title">วันที่</div>
          <div class="range-inline">
            <input type="date" name="date_from" value="<?= h($dfrom) ?>">
            <span class="mx-4">ถึง</span>
            <input type="date" name="date_to" value="<?= h($dto) ?>">
          </div>
        </div>
        <div class="filter-actions">
          <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuArticles')">ล้าง</button>
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
          <th>ชื่อบทความ</th>
          <th>หมวดหมู่</th>
          <th>ผู้สร้าง</th>
          <th>วันที่</th>
          <th>สถานะ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($articles): foreach ($articles as $i => $row): ?>
            <tr>
              <td><?= ($offset + $i + 1) ?></td>
              <td>
                <?php if (!empty($row['image'])): ?>
                  <button type="button" class="thumb-btn" data-src="<?= h($row['image']) ?>">
                    <img src="<?= h($row['image']) ?>" class="thumb" alt="">
                  </button>
                <?php else: ?><div class="thumb"></div><?php endif; ?>
              </td>
              <td><strong><?= h($row['title']) ?></strong></td>
              <td><?= h($row['category'] ?: '-') ?></td>
              <td><?= h($row['admin_name'] ?? 'N/A') ?></td>
              <td class="muted">
                <?php $ts = isset($row['created_at']) ? strtotime($row['created_at']) : false;
                echo $ts ? date('d/m/Y', $ts) : '-'; ?>
              </td>
              <td style="text-align:center">
                <?php $active = (int)($row['status'] ?? 1) === 1; ?>
                <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:3px 10px;border-radius:100px;<?= $active ? 'background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.3)' : 'background:rgba(107,114,128,0.12);color:#9ca3af;border:1px solid rgba(107,114,128,0.25)' ?>">
                  <span style="width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0;"></span>
                  <?= $active ? 'เผยแพร่' : 'ซ่อน' ?>
                </span>
              </td>
              <td>
                <?php
                $_is_super = ($_SESSION['admin_role'] ?? '') === 'super_admin';
                $_own_id   = (int)($row['admin_id'] ?? 0);
                $_can_act  = $_is_super || $_own_id === 0 || $_own_id === (int)$_SESSION['admin_id'];
                ?>
                <div style="display:flex;gap:5px;justify-content:center;">
                  <?php if ($_can_act): ?>
                    <button type="button" onclick="openArticleModal('edit.php?id=<?= (int)$row['id'] ?>&modal=1')" class="t-btn t-edit" title="แก้ไข">
                      <span class="material-symbols-rounded">edit</span>
                    </button>
                    <button type="button" onclick="deleteArticle(<?= (int)$row['id'] ?>, '<?= h(addslashes($row['title'])) ?>')" class="t-btn t-del" title="ลบ">
                      <span class="material-symbols-rounded">delete</span>
                    </button>
                  <?php else: ?>
                    <span title="บทความของ <?= h($row['admin_name'] ?? 'ผู้อื่น') ?>" style="color:var(--text-muted);padding:4px 8px;">
                      <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">lock</span>
                    </span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach;
        else: ?>
          <tr>
            <td colspan="8" class="text-center">ยังไม่มีบทความ</td>
          </tr>
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
      <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page > 1 ? page_url($page - 1) : '#' ?>" rel="prev" aria-label="ก่อนหน้า">‹</a>
      <?php $start = max(1, $page - 2);
      $end = min($pages, $page + 2);
      if ($start > 1) echo '<span class="page-ellipsis">…</span>';
      for ($i = $start; $i <= $end; $i++): ?>
        <a class="page-btn <?= $i == $page ? 'is-active' : '' ?>" href="<?= page_url($i) ?>"><?= $i ?></a>
      <?php endfor;
      if ($end < $pages) echo '<span class="page-ellipsis">…</span>'; ?>
      <a class="page-btn <?= $page >= $pages ? 'is-disabled' : '' ?>" href="<?= $page < $pages ? page_url($page + 1) : '#' ?>" rel="next" aria-label="ถัดไป">›</a>
      <div class="page-size">
        <select id="ppSelect" class="pager-select" aria-label="จำนวนต่อหน้า">
          <?php foreach ([20, 50, 100] as $pp): ?>
            <option value="<?= $pp ?>" <?= (int)$per === $pp ? 'selected' : '' ?>><?= $pp ?>/หน้า</option>
          <?php endforeach; ?>
        </select>
      </div>
    </nav>
  </div>

  <!-- ── ARTICLE MODAL ── -->
  <div id="modal-article" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
    <div style="background:var(--bg-surface,#fff);width:min(96vw,1100px);height:90vh;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.35);">
      <iframe id="article-iframe" src="" style="flex:1;border:none;width:100%;display:block;"></iframe>
    </div>
  </div>

  <div id="imgPreviewOverlay" class="imgpv-overlay" aria-hidden="true">
    <div class="imgpv-dialog" role="dialog" aria-modal="true" aria-label="ตัวอย่างรูป">
      <button type="button" class="imgpv-close" aria-label="ปิด">✕</button>
      <img id="imgPreview" src="" alt="" class="imgpv-img">
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<style>
  .stat-card{background:var(--bg-surface,#fff);border-radius:12px;padding:18px 22px;border:1px solid var(--border,#e5e7eb);}
  .stat-card-num{font-size:32px;font-weight:800;letter-spacing:-0.03em;color:var(--text-main,#111);}
  .stat-card-label{font-size:12px;color:var(--text-muted,#6b7280);margin-top:4px;font-weight:500;}
  .t-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:1px solid var(--border,#e5e7eb);background:transparent;cursor:pointer;color:var(--text-muted,#6b7280);transition:background .15s,color .15s;}
  .t-edit:hover{color:var(--primary,#2563eb);background:rgba(37,99,235,.07);border-color:var(--primary,#2563eb);}
  .t-del:hover{color:#ef4444;background:rgba(239,68,68,.07);border-color:#ef4444;}
  .t-btn .material-symbols-rounded{font-size:16px;}
  .cmns-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;border:none;font-size:14px;font-weight:600;cursor:pointer;}
  .cmns-btn-primary{background:var(--primary,#2563eb);color:#fff;}
  .cmns-btn-primary:hover{opacity:.88;}
</style>
<script>
  // Modal
  const _modal = document.getElementById('modal-article');
  const _iframe = document.getElementById('article-iframe');

  function openArticleModal(url) {
    _iframe.src = url;
    _modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  function closeArticleModal() {
    _modal.style.display = 'none';
    document.body.style.overflow = '';
    setTimeout(() => { _iframe.src = ''; }, 200);
  }

  _modal.addEventListener('click', e => { if (e.target === _modal) closeArticleModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && _modal.style.display !== 'none') closeArticleModal(); });

  window.addEventListener('message', function(e) {
    if (e.data === 'article-saved') {
      closeArticleModal();
      <?php if (!empty($flash)): ?>
      <?php else: ?>
      Swal.fire({ toast:true, position:'top-end', icon:'success', title:'บันทึกเรียบร้อยแล้ว', showConfirmButton:false, timer:2500, timerProgressBar:true });
      <?php endif; ?>
      setTimeout(() => location.reload(), 400);
    }
  });

  // Delete
  function deleteArticle(id, title) {
    Swal.fire({
      title: 'ลบบทความ?',
      html: `<span style="color:#6b7280;font-size:14px;">"${title}"</span>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonText: 'ยกเลิก',
      confirmButtonText: 'ลบเลย'
    }).then(r => {
      if (r.isConfirmed) {
        fetch(`delete.php?id=${id}&ajax=1`, { method: 'GET' })
          .then(res => res.json())
          .then(data => {
            if (data.ok) {
              Swal.fire({ toast:true, position:'top-end', icon:'success', title:'ลบเรียบร้อยแล้ว', showConfirmButton:false, timer:2000 });
              setTimeout(() => location.reload(), 600);
            } else {
              Swal.fire('เกิดข้อผิดพลาด', data.msg || '', 'error');
            }
          });
      }
    });
  }

  <?php if ($flash): ?>
  document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({ toast:true, position:'top-end', icon:'success', title:<?= json_encode($flash) ?>, showConfirmButton:false, timer:3000, timerProgressBar:true });
  });
  <?php endif; ?>

  // Dropdown
  function toggleMenu(id) {
    var m = document.getElementById(id);
    if (m) m.classList.toggle('show');
  }

  function clearMenu(id) {
    var root = document.getElementById(id);
    if (!root) return;
    root.querySelectorAll('input[type="checkbox"]').forEach(el => el.checked = false);
    root.querySelectorAll('input[type="date"]').forEach(el => el.value = '');
  }
  document.addEventListener('click', function(e) {
    var dd = e.target.closest ? e.target.closest('.filter-dropdown') : null;
    document.querySelectorAll('.filter-menu.show').forEach(function(m) {
      if (!dd || !dd.contains(m)) m.classList.remove('show');
    });
  });

  // Image preview modal
  (function() {
    var overlay = document.getElementById('imgPreviewOverlay');
    var imgEl = document.getElementById('imgPreview');

    function openPreview(src) {
      if (!overlay || !imgEl) return;
      imgEl.src = src;
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden', 'false');
    }

    function closePreview() {
      if (!overlay) return;
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden', 'true');
      imgEl.src = '';
    }
    document.addEventListener('click', function(e) {
      var btn = e.target.closest ? e.target.closest('.thumb-btn') : null;
      if (!btn) return;
      var src = btn.getAttribute('data-src');
      if (src) openPreview(src);
    });
    if (overlay) {
      overlay.addEventListener('click', function(e) {
        if (e.target === overlay || e.target.classList.contains('imgpv-close')) closePreview();
      });
    }
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && overlay && overlay.classList.contains('show')) closePreview();
    });
  })();

  // Per page
  (function() {
    const sel = document.getElementById('ppSelect');
    if (!sel) return;
    sel.addEventListener('change', function() {
      const u = new URL(location.href);
      u.searchParams.set('per', this.value);
      u.searchParams.set('page', '1');
      location = u.toString();
    });
  })();

  // Arrow keys
  (function() {
    document.addEventListener('keydown', function(e) {
      if (e.altKey || e.metaKey || e.ctrlKey) return;
      if (e.key === 'ArrowRight') document.querySelector('.page-btn[rel="next"]')?.click();
      if (e.key === 'ArrowLeft') document.querySelector('.page-btn[rel="prev"]')?.click();
    });
  })();
</script>