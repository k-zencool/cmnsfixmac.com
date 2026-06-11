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
    COALESCE(SUM(status = 1), 0) AS published,
    COALESCE(SUM(MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())), 0) AS this_month,
    COALESCE(SUM(slug IS NOT NULL AND slug != ''), 0) AS has_slug
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
<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<style>
.t-btn{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-muted);cursor:pointer;transition:all .18s;padding:0;flex-shrink:0;}
.t-btn .material-symbols-rounded{font-size:16px;line-height:1;}
.t-btn:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(0,0,0,.1);}
.t-edit:hover{color:var(--primary);background:rgba(37,99,235,.07);border-color:var(--primary);}
.t-del:hover{color:#ef4444;background:rgba(239,68,68,.07);border-color:#ef4444;}

.thumb-wrap{width:56px;height:56px;border-radius:10px;background:var(--bg-surface-alt);border:1px solid var(--border);flex-shrink:0;position:relative;overflow:hidden;}
.thumb-wrap::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.18) 50%,transparent 100%);background-size:200% 100%;animation:shimmer 1.4s infinite;pointer-events:none;}
[data-theme="dark"] .thumb-wrap::after{background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.07) 50%,transparent 100%);background-size:200% 100%;}
@keyframes shimmer{from{background-position:200% 0}to{background-position:-200% 0}}
.thumb-wrap.loaded::after{display:none;}
.thumb-wrap img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:10px;opacity:0;transition:opacity .3s ease;}
.thumb-wrap.loaded img{opacity:1;}
.thumb-wrap .thumb-icon{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--text-muted);transition:opacity .2s;}
.thumb-wrap.loaded .thumb-icon{opacity:0;pointer-events:none;}
</style>
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= time() ?>">
<link rel="stylesheet" href="../templates/assets/css/modal.css?v=<?= time() ?>">

<div class="cmns-wrapper">

    <!-- Header -->
    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color:var(--primary);">
                <span class="material-symbols-rounded" style="font-size:32px;">article</span>
                บทความ / ARTICLES
            </h1>
            <p style="color:var(--text-muted);margin-top:5px;font-size:13px;">
                จัดการบทความ · แสดง <b><?= number_format($total) ?></b> รายการ
            </p>
        </div>
        <div class="cmns-action-buttons">
            <button type="button" onclick="openArticleModal('add.php?modal=1')" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">add_circle</span> เพิ่มบทความ
            </button>
        </div>
    </div>

    <?php if ($flash): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({ icon:'success', title: <?= json_encode($flash) ?>, toast:true, position:'top-end',
            showConfirmButton:false, timer:3000, timerProgressBar:true });
    });
    </script>
    <?php endif; ?>

    <!-- Stats -->
    <div class="log-stats" style="margin-bottom:24px;">
        <div class="log-stat-card stat-accent-blue">
            <span class="stat-label">บทความทั้งหมด</span>
            <span class="stat-value"><?= number_format($stats['total'] ?? 0) ?></span>
            <span class="stat-sub">ในระบบ</span>
        </div>
        <div class="log-stat-card stat-accent-green">
            <span class="stat-label">เผยแพร่แล้ว</span>
            <span class="stat-value"><?= number_format($stats['published'] ?? 0) ?></span>
            <span class="stat-sub">สาธารณะ</span>
        </div>
        <div class="log-stat-card stat-accent-yellow">
            <span class="stat-label">เดือนนี้</span>
            <span class="stat-value"><?= number_format($stats['this_month'] ?? 0) ?></span>
            <span class="stat-sub"><?= date('M Y') ?></span>
        </div>
        <div class="log-stat-card stat-accent-purple">
            <span class="stat-label">มี Slug (SEO)</span>
            <span class="stat-value"><?= number_format($stats['has_slug'] ?? 0) ?></span>
            <span class="stat-sub">จาก <?= number_format($stats['total'] ?? 0) ?></span>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET">
        <div class="log-filter-bar">
            <div class="log-filter-group" style="flex:1;min-width:220px;">
                <label>ค้นหา</label>
                <div class="log-search-wrap">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="ชื่อบทความ / หมวด / เนื้อหา" style="padding-left:38px;">
                </div>
            </div>
            <div class="log-filter-group">
                <label>หมวดหมู่</label>
                <select name="cat[]">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($catRows as $c): ?>
                        <option value="<?= h($c) ?>" <?= in_array($c, $cats, true) ? 'selected' : '' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="log-filter-group">
                <label>สถานะ</label>
                <select name="status">
                    <option value="">ทั้งหมด</option>
                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>เผยแพร่</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>ซ่อน</option>
                </select>
            </div>
            <div class="log-filter-group">
                <label>เรียงตาม</label>
                <select name="sort">
                    <option value="created_desc" <?= $sort === 'created_desc' ? 'selected' : '' ?>>ล่าสุดก่อน</option>
                    <option value="created_asc"  <?= $sort === 'created_asc'  ? 'selected' : '' ?>>เก่าสุดก่อน</option>
                    <option value="title_asc"    <?= $sort === 'title_asc'    ? 'selected' : '' ?>>ชื่อ A→Z</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <span class="material-symbols-rounded">search</span> ค้นหา
            </button>
            <?php if ($q || $status || $cats): ?>
                <a href="index.php" class="btn-reset">
                    <span class="material-symbols-rounded">close</span>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="log-card">
        <div style="overflow-x:auto;">
        <table class="log-table">
            <thead>
                <tr>
                    <th style="width:72px;">รูปปก</th>
                    <th>ชื่อบทความ</th>
                    <th style="width:120px;text-align:center;">หมวดหมู่</th>
                    <th style="width:90px;text-align:center;">สถานะ</th>
                    <th style="width:76px;text-align:center;">โดย</th>
                    <th style="width:90px;text-align:center;">วันที่</th>
                    <th style="width:80px;text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($articles): foreach ($articles as $row):
                    $img = $row['image'] ?? '';
                ?>
                <tr id="article-row-<?= (int)$row['id'] ?>">
                    <td>
                        <div class="thumb-wrap">
                            <?php if ($img): ?>
                                <img src="<?= h($img) ?>" alt="" loading="lazy" decoding="async"
                                     onload="this.closest('.thumb-wrap').classList.add('loaded')"
                                     onerror="this.remove();this.closest('.thumb-wrap').classList.add('loaded')">
                            <?php endif; ?>
                            <span class="thumb-icon material-symbols-rounded" style="font-size:20px;">article</span>
                        </div>
                    </td>
                    <td>
                        <a href="/article/<?= h($row['slug'] ?? '') ?>" target="_blank" rel="noopener"
                           style="font-weight:700;font-size:13px;color:var(--primary);text-decoration:none;"
                           onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                            <?= h($row['title']) ?>
                        </a>
                        <?php if ($row['views']): ?>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">
                                <span class="material-symbols-rounded" style="font-size:12px;vertical-align:-2px;">visibility</span>
                                <?= number_format($row['views']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$row['slug']): ?>
                            <div style="font-size:10px;color:#f59e0b;margin-top:2px;font-weight:700;">⚠ ไม่มี slug</div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($row['category']): ?>
                            <span class="action-badge" style="background:var(--primary-light,#eff6ff);color:var(--primary);border-color:rgba(37,99,235,.2);">
                                <?= h($row['category']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php $active = (int)($row['status'] ?? 1) === 1; ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;background:<?= $active ? '#f0fdf4' : '#f9fafb' ?>;color:<?= $active ? '#10b981' : '#6b7280' ?>;border:1px solid <?= $active ? '#bbf7d0' : '#e5e7eb' ?>;">
                            <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;flex-shrink:0;"></span>
                            <?= $active ? 'เผยแพร่' : 'ซ่อน' ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($row['admin_name']): ?>
                            <span style="font-size:11px;font-weight:600;color:var(--text-muted);"><?= h($row['admin_name']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;text-align:center;">
                        <?= $row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : '-' ?>
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
                                <span title="บทความของ <?= h($row['admin_name'] ?? 'ผู้อื่น') ?>" style="color:var(--text-muted);cursor:default;padding:4px 8px;">
                                    <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">lock</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <span class="material-symbols-rounded">article</span>
                            <p>ยังไม่มีบทความ · <a href="javascript:void(0)" onclick="openArticleModal('add.php?modal=1')" style="color:var(--primary);">เพิ่มบทความแรก</a></p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>

        <div class="log-pagination">
            <div>
                แสดง <b><?= number_format(min($total, $offset+1)) ?>–<?= number_format(min($total, $offset+$per)) ?></b>
                จาก <b><?= number_format($total) ?></b> รายการ
                &nbsp;·&nbsp; หน้า <?= $page ?> / <?= $pages ?>
            </div>
            <div class="page-btns">
                <a href="<?= $page > 1 ? page_url($page-1) : '#' ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_left</span>
                </a>
                <?php
                $pStart = max(1, $page - 2);
                $pEnd   = min($pages, $pStart + 4);
                for ($p = $pStart; $p <= $pEnd; $p++): ?>
                    <a href="<?= page_url($p) ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="<?= $page < $pages ? page_url($page+1) : '#' ?>" class="page-btn <?= $page>=$pages?'disabled':'' ?>">
                    <span class="material-symbols-rounded" style="font-size:16px;">chevron_right</span>
                </a>
                <select onchange="goPerPage(this)"
                        style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);background:var(--bg-surface);color:var(--text-main);font-size:13px;outline:none;cursor:pointer;font-family:'Sarabun',sans-serif;">
                    <?php foreach([10,20,50] as $pp): ?>
                        <option value="<?= $pp ?>" <?= $per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

</div><!-- /.cmns-wrapper -->

<!-- Delete Confirm -->
<div id="del-confirm" style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;display:none;align-items:center;justify-content:center;">
  <div style="background:var(--bg-surface);width:90%;max-width:360px;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.2);overflow:hidden;border:1px solid var(--border);">
    <div style="padding:20px 20px 12px;text-align:center;background:rgba(239,68,68,.06);border-bottom:1px solid var(--border);">
      <div style="width:44px;height:44px;border-radius:50%;background:rgba(239,68,68,.12);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
        <span class="material-symbols-rounded" style="font-size:22px;">delete</span>
      </div>
      <h3 style="margin:0;font-size:15px;font-weight:700;color:#dc2626;">ยืนยันการลบ?</h3>
    </div>
    <div style="padding:16px 20px;text-align:center;color:var(--text-main);font-size:14px;line-height:1.6;">
      ลบ <strong id="del-title" style="color:var(--primary);"></strong><br>
      <span style="font-size:12px;color:#ef4444;">ไม่สามารถย้อนกลับได้</span>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);background:var(--bg-surface-alt);display:flex;gap:8px;justify-content:center;">
      <button type="button" onclick="closeDeleteConfirm()" class="cmns-btn cmns-btn-secondary">ยกเลิก</button>
      <button id="del-confirm-btn" type="button" onclick="doDelete()" class="cmns-btn cmns-btn-primary" style="background:#ef4444;border-color:#ef4444;">ลบบทความ</button>
    </div>
  </div>
</div>

<!-- Article Modal -->
<div id="modal-article" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
    <div style="background:var(--bg-surface,#fff);width:min(96vw,1100px);height:90vh;border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.35);">
        <iframe id="article-iframe" src="" style="flex:1;border:none;width:100%;display:block;"></iframe>
    </div>
</div>

<script>
function openArticleModal(url) {
    document.getElementById('article-iframe').src = url;
    const m = document.getElementById('modal-article');
    m.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeArticleModal() {
    document.getElementById('modal-article').style.display = 'none';
    document.body.style.overflow = '';
    setTimeout(() => { document.getElementById('article-iframe').src = ''; }, 200);
}
document.getElementById('modal-article').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-article')) closeArticleModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('modal-article').style.display !== 'none') closeArticleModal();
});
window.addEventListener('message', function(e) {
    if (e.data === 'article-saved') {
        closeArticleModal();
        Swal.fire({ toast:true, position:'top-end', icon:'success', title:'บันทึกเรียบร้อยแล้ว', showConfirmButton:false, timer:2500, timerProgressBar:true });
        setTimeout(() => location.reload(), 400);
    }
});
let _delId = null;
function deleteArticle(id, title) {
    _delId = id;
    document.getElementById('del-title').textContent = title;
    document.getElementById('del-confirm').style.display = 'flex';
}
function closeDeleteConfirm() {
    document.getElementById('del-confirm').style.display = 'none';
}
function doDelete() {
    if (!_delId) return;
    const btn = document.getElementById('del-confirm-btn');
    btn.disabled = true; btn.textContent = 'กำลังลบ...';
    fetch('delete.php?id=' + _delId + '&ajax=1').then(r => r.json()).then(d => {
        closeDeleteConfirm();
        if (d.ok) {
            const row = document.getElementById('article-row-' + _delId);
            if (row) {
                row.style.transition = 'opacity .25s,transform .25s';
                row.style.opacity = '0'; row.style.transform = 'translateX(30px)';
                setTimeout(() => row.remove(), 260);
            }
            Swal.fire({ icon:'success', title:'ลบบทความเรียบร้อยแล้ว', toast:true, position:'top-end',
                showConfirmButton:false, timer:3000, timerProgressBar:true });
        } else {
            Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text: d.msg || '', toast:true, position:'top-end',
                showConfirmButton:false, timer:3000 });
        }
        btn.disabled = false; btn.textContent = 'ลบบทความ';
    });
}
document.getElementById('del-confirm').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteConfirm();
});
function goPerPage(sel) {
    const u = new URL(location.href);
    u.searchParams.set('per', sel.value);
    u.searchParams.set('page', '1');
    location = u.toString();
}
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
