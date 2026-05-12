<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function page_url(int $p): string { $q = $_GET; $q['page'] = $p; return '?' . http_build_query($q); }

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$q      = trim($_GET['q']      ?? '');
$cat    = trim($_GET['cat']    ?? '');
$status = trim($_GET['status'] ?? '');
$sort   = $_GET['sort']  ?? 'newest';
$per    = max(10, min(100, (int)($_GET['per']  ?? 20)));
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per;

$CATEGORIES = ['MacBook','iPhone','iPad','iMac','MacMini','AirPods','AppleWatch','Adapter','Other'];
$STATUSES   = ['published'=>'เผยแพร่','draft'=>'ฉบับร่าง','hidden'=>'ซ่อน'];

$where = []; $params = [];
if ($q)      { $where[] = '(r.title LIKE ? OR r.model LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat)    { $where[] = 'r.category = ?'; $params[] = $cat; }
if ($status) { $where[] = 'r.status = ?';   $params[] = $status; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order_sql  = match($sort) { 'oldest'=>'r.created_at ASC','title'=>'r.title ASC','popular'=>'r.views DESC', default=>'r.created_at DESC' };

$cnt = $pdo->prepare("SELECT COUNT(*) FROM repairs r $where_sql");
$cnt->execute($params); $total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$stmt = $pdo->prepare("
    SELECT r.id, r.title, r.model, r.category, r.image, r.slug, r.created_at, r.views, r.status,
           r.tracking_id, r.admin_id, t.ticket_number, t.customer_name,
           u.username AS author
    FROM repairs r
    LEFT JOIN tracking t ON t.id = r.tracking_id
    LEFT JOIN admin_users u ON u.id = r.admin_id
    $where_sql
    ORDER BY $order_sql
    LIMIT $per OFFSET $offset
");
$stmt->execute($params);
$repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_posts,
        SUM(tracking_id IS NOT NULL) AS linked_jobs,
        SUM(MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())) AS this_month,
        SUM(slug IS NOT NULL AND slug != '') AS has_slug,
        SUM(status = 'published') AS cnt_published,
        SUM(status = 'draft')     AS cnt_draft,
        SUM(status = 'hidden')    AS cnt_hidden
    FROM repairs
")->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'ผลงานทั้งหมด';
include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<style>
.t-btn{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;border:1px solid var(--border);background:var(--bg-surface-alt);color:var(--text-muted);cursor:pointer;transition:all .18s;text-decoration:none;padding:0;flex-shrink:0;}
.t-btn .material-symbols-rounded{font-size:16px;line-height:1;}
.t-btn:hover{transform:translateY(-1px);box-shadow:0 3px 8px rgba(0,0,0,.1);}
.t-edit:hover{color:var(--primary);background:rgba(37,99,235,.07);border-color:var(--primary);}
.t-del:hover{color:#ef4444;background:rgba(239,68,68,.07);border-color:#ef4444;}
</style>
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= time() ?>">
<link rel="stylesheet" href="../templates/assets/css/modal.css?v=<?= time() ?>">

<div class="cmns-wrapper">

    <!-- Header -->
    <div class="cmns-header-bar">
        <div>
            <h1 class="cmns-page-title" style="color:var(--primary);">
                <span class="material-symbols-rounded" style="font-size:32px;">collections_bookmark</span>
                ผลงานซ่อม / REPAIRS
            </h1>
            <p style="color:var(--text-muted);margin-top:5px;font-size:13px;">
                โพสต์ผลงาน เพื่อ SEO · แสดง <b><?= number_format($total) ?></b> รายการ
            </p>
        </div>
        <div class="cmns-action-buttons">
            <button type="button" onclick="openRepairModal('add.php?modal=1')" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">add_circle</span> เพิ่มผลงาน
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
            <span class="stat-label">โพสต์ทั้งหมด</span>
            <span class="stat-value"><?= number_format($stats['total_posts']) ?></span>
            <span class="stat-sub">ผลงานในระบบ</span>
        </div>
        <div class="log-stat-card stat-accent-green">
            <span class="stat-label">ผูกงานซ่อม</span>
            <span class="stat-value"><?= number_format($stats['linked_jobs']) ?></span>
            <span class="stat-sub">มี tracking ID</span>
        </div>
        <div class="log-stat-card stat-accent-yellow">
            <span class="stat-label">เดือนนี้</span>
            <span class="stat-value"><?= number_format($stats['this_month']) ?></span>
            <span class="stat-sub"><?= date('M Y') ?></span>
        </div>
        <div class="log-stat-card stat-accent-purple">
            <span class="stat-label">มี Slug (SEO)</span>
            <span class="stat-value"><?= number_format($stats['has_slug']) ?></span>
            <span class="stat-sub">จาก <?= number_format($stats['total_posts']) ?></span>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET">
        <div class="log-filter-bar">
            <div class="log-filter-group" style="flex:1;min-width:220px;">
                <label>ค้นหา</label>
                <div class="log-search-wrap">
                    <span class="material-symbols-rounded search-icon">search</span>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="ชื่อผลงาน / รุ่นเครื่อง" style="padding-left:38px;">
                </div>
            </div>
            <div class="log-filter-group">
                <label>หมวดหมู่</label>
                <select name="cat">
                    <option value="">ทั้งหมด</option>
                    <?php foreach($CATEGORIES as $c): ?>
                        <option value="<?= h($c) ?>" <?= $cat===$c?'selected':'' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="log-filter-group">
                <label>สถานะ</label>
                <select name="status">
                    <option value="">ทั้งหมด</option>
                    <?php foreach($STATUSES as $v => $label): ?>
                        <option value="<?= $v ?>" <?= $status===$v?'selected':'' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="log-filter-group">
                <label>เรียงตาม</label>
                <select name="sort">
                    <option value="newest"  <?= $sort==='newest' ?'selected':'' ?>>ล่าสุดก่อน</option>
                    <option value="oldest"  <?= $sort==='oldest' ?'selected':'' ?>>เก่าสุดก่อน</option>
                    <option value="title"   <?= $sort==='title'  ?'selected':'' ?>>ชื่อ A→Z</option>
                    <option value="popular" <?= $sort==='popular'?'selected':'' ?>>ยอดนิยม (วิวสูงสุด)</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <span class="material-symbols-rounded">search</span> ค้นหา
            </button>
            <?php if ($q || $cat || $status): ?>
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
                    <th>ชื่อผลงาน</th>
                    <th style="width:120px;text-align:center;">หมวด / รุ่น</th>
                    <th style="width:90px;text-align:center;">สถานะ</th>
                    <th style="width:110px;text-align:center;">ผูกงาน</th>
                    <th style="width:76px;text-align:center;">โดย</th>
                    <th style="width:86px;text-align:center;">วันที่</th>
                    <th style="width:80px;text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($repairs): foreach($repairs as $r):
                    $img = $r['image'] ?? '';
                    if ($img && strpos($img, '/') === false) $img = '/uploads/repairs/' . $img;
                ?>
                <tr id="repair-row-<?= $r['id'] ?>">
                    <td>
                        <div style="width:56px;height:56px;border-radius:10px;background:var(--bg-surface-alt);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;overflow:hidden;">
                            <?php if ($img): ?>
                                <img src="<?= h($img) ?>" alt="" loading="lazy"
                                     style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:10px;"
                                     onerror="this.remove()">
                            <?php endif; ?>
                            <span class="material-symbols-rounded" style="color:var(--text-muted);font-size:20px;">hide_image</span>
                        </div>
                    </td>
                    <td>
                        <a href="/works/detail.php?id=<?= $r['id'] ?>" target="_blank" rel="noopener"
                           style="font-weight:700;font-size:13px;color:var(--primary);text-decoration:none;"
                           onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                            <?= h($r['title']) ?>
                        </a>
                        <?php if ($r['views']): ?>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">
                                <span class="material-symbols-rounded" style="font-size:12px;vertical-align:-2px;">visibility</span>
                                <?= number_format($r['views']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($r['category']): ?>
                            <span class="action-badge" style="background:var(--primary-light,#eff6ff);color:var(--primary);border-color:rgba(37,99,235,.2);">
                                <?= h($r['category']) ?>
                            </span>
                        <?php endif; ?>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= h($r['model']) ?></div>
                    </td>
                    <td style="text-align:center;">
                        <?php
                        $sBadge = match($r['status'] ?? 'published') {
                            'published' => ['เผยแพร่',  '#10b981','#f0fdf4','#bbf7d0'],
                            'draft'     => ['ฉบับร่าง', '#d97706','#fffbeb','#fde68a'],
                            'hidden'    => ['ซ่อน',     '#6b7280','#f9fafb','#e5e7eb'],
                            default     => ['เผยแพร่',  '#10b981','#f0fdf4','#bbf7d0'],
                        };
                        ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;background:<?= $sBadge[2] ?>;color:<?= $sBadge[1] ?>;border:1px solid <?= $sBadge[3] ?>;">
                            <span style="width:6px;height:6px;border-radius:50%;background:<?= $sBadge[1] ?>;display:inline-block;flex-shrink:0;"></span>
                            <?= $sBadge[0] ?>
                        </span>
                        <?php if (!$r['slug']): ?>
                            <div style="font-size:10px;color:#f59e0b;margin-top:3px;font-weight:700;">⚠ ไม่มี slug</div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($r['ticket_number']): ?>
                            <a href="../tracking/edit.php?id=<?= $r['tracking_id'] ?>" target="_blank" rel="noopener" style="text-decoration:none;">
                                <span class="action-badge badge-in" style="cursor:pointer;" onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">
                                    <span class="material-symbols-rounded" style="font-size:12px;">task_alt</span>
                                    <?= h($r['ticket_number']) ?>
                                </span>
                            </a>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($r['author']): ?>
                            <span style="font-size:11px;font-weight:600;color:var(--text-muted);">
                                <?= h($r['author']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;text-align:center;">
                        <?= $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '-' ?>
                    </td>
                    <td>
                        <?php
                        $_is_super    = ($_SESSION['admin_role'] ?? '') === 'super_admin';
                        $_owner_id    = (int)($r['admin_id'] ?? 0);
                        $_can_act     = $_is_super || $_owner_id === 0 || $_owner_id === (int)$_SESSION['admin_id'];
                        ?>
                        <div style="display:flex;gap:5px;justify-content:center;">
                            <?php if ($_can_act): ?>
                                <button type="button" onclick="openRepairModal('edit.php?id=<?= $r['id'] ?>&modal=1')" class="t-btn t-edit" title="แก้ไข">
                                    <span class="material-symbols-rounded">edit</span>
                                </button>
                                <button type="button" onclick="openDeleteConfirm(<?= $r['id'] ?>, '<?= h($r['title']) ?>')" class="t-btn t-del" title="ลบ">
                                    <span class="material-symbols-rounded">delete</span>
                                </button>
                            <?php else: ?>
                                <span title="งานของ <?= h($r['author'] ?? 'ผู้อื่น') ?>" style="color:var(--text-muted);cursor:default;padding:4px 8px;font-size:12px;">
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
                            <span class="material-symbols-rounded">collections_bookmark</span>
                            <p>ยังไม่มีผลงาน · <a href="add.php" style="color:var(--primary);">เพิ่มผลงานแรก</a></p>
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
                $start = max(1, $page - 2);
                $end   = min($pages, $start + 4);
                for ($p = $start; $p <= $end; $p++):
                ?>
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

</div>

<!-- Delete Confirm -->
<div id="del-confirm" style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;display:none;align-items:center;justify-content:center;">
  <div id="del-confirm-box" style="background:var(--bg-surface);width:90%;max-width:360px;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.2);overflow:hidden;border:1px solid var(--border);">
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
      <button id="del-confirm-btn" type="button" onclick="doDelete()" class="cmns-btn cmns-btn-primary" style="background:#ef4444;border-color:#ef4444;">ลบผลงาน</button>
    </div>
  </div>
</div>

<!-- Repair Modal -->
<div id="modal-repair" class="cmns-modal">
    <div class="modal-content" style="width:min(940px,calc(100vw - 40px));max-width:none;max-height:none;padding:0;overflow:hidden;border-radius:16px;flex-shrink:0;">
        <iframe id="repair-iframe" src="" style="width:100%;height:88vh;min-height:88vh;border:none;display:block;background:var(--bg-surface);"></iframe>
    </div>
</div>

<script>
function goPerPage(sel) {
    const u = new URL(location.href);
    u.searchParams.set('per', sel.value);
    u.searchParams.set('page', '1');
    location.href = u.toString();
}

let _delId = null;
function openDeleteConfirm(id, title) {
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
      const row = document.getElementById('repair-row-' + _delId);
      if (row) {
        row.style.transition = 'opacity .25s,transform .25s';
        row.style.opacity = '0'; row.style.transform = 'translateX(30px)';
        setTimeout(() => row.remove(), 260);
      }
      Swal.fire({ icon:'success', title:'ลบผลงานเรียบร้อยแล้ว', toast:true, position:'top-end',
        showConfirmButton:false, timer:3000, timerProgressBar:true });
    }
    btn.disabled = false; btn.textContent = 'ลบผลงาน';
  });
}
document.getElementById('del-confirm').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteConfirm();
});

function openRepairModal(url) {
    document.getElementById('repair-iframe').src = url;
    document.getElementById('modal-repair').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeRepairModal() {
    document.getElementById('modal-repair').classList.remove('show');
    document.body.style.overflow = '';
    setTimeout(() => document.getElementById('repair-iframe').src = '', 300);
}

// click backdrop = close
document.getElementById('modal-repair').addEventListener('click', function(e) {
    if (e.target === this) closeRepairModal();
});

// iframe signals success via postMessage
window.addEventListener('message', function(e) {
    if (e.data === 'repair-saved') {
        closeRepairModal();
        location.reload();
    }
});
</script>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
