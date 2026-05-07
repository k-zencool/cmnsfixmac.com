<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function page_url(int $p): string { $q = $_GET; $q['page'] = $p; return '?' . http_build_query($q); }

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$q      = trim($_GET['q']   ?? '');
$cat    = trim($_GET['cat'] ?? '');
$sort   = $_GET['sort']  ?? 'newest';
$per    = max(10, min(100, (int)($_GET['per']  ?? 20)));
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per;

$CATEGORIES = ['MacBook','iPhone','iPad','iMac','MacMini','AirPods','AppleWatch','Adapter','Other'];

$where = []; $params = [];
if ($q)   { $where[] = '(r.title LIKE ? OR r.model LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat) { $where[] = 'r.category = ?'; $params[] = $cat; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order_sql  = match($sort) { 'oldest'=>'r.created_at ASC','title'=>'r.title ASC', default=>'r.created_at DESC' };

$cnt = $pdo->prepare("SELECT COUNT(*) FROM repairs r $where_sql");
$cnt->execute($params); $total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;

$stmt = $pdo->prepare("
    SELECT r.id, r.title, r.model, r.category, r.image, r.slug, r.created_at, r.views,
           t.ticket_number, t.customer_name
    FROM repairs r
    LEFT JOIN tracking t ON t.id = r.tracking_id
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
        SUM(slug IS NOT NULL AND slug != '') AS has_slug
    FROM repairs
")->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'ผลงานทั้งหมด';
include __DIR__ . '/../templates/header_admin.php';
?>
<link rel="stylesheet" href="../templates/assets/css/inventory-dashboard.css?v=<?= time() ?>">
<link rel="stylesheet" href="../templates/assets/css/inventory-logs.css?v=<?= time() ?>">

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
            <a href="add.php" class="cmns-btn cmns-btn-primary">
                <span class="material-symbols-rounded">add_circle</span> เพิ่มผลงาน
            </a>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($flash): ?>
        <div style="background:#f0fdf4;border:1px solid #86efac;color:#16a34a;padding:10px 16px;border-radius:10px;margin-bottom:16px;font-size:13px;">
            <?= h($flash) ?>
        </div>
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
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="ชื่อผลงาน / รุ่นเครื่อง">
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
                <label>เรียงตาม</label>
                <select name="sort">
                    <option value="newest" <?= $sort==='newest'?'selected':'' ?>>ล่าสุดก่อน</option>
                    <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>เก่าสุดก่อน</option>
                    <option value="title"  <?= $sort==='title' ?'selected':'' ?>>ชื่อ A→Z</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">
                <span class="material-symbols-rounded">search</span> ค้นหา
            </button>
            <?php if ($q || $cat): ?>
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
                    <th style="width:120px;">หมวด / รุ่น</th>
                    <th style="width:150px;">Slug / SEO</th>
                    <th style="width:120px;">ผูกงาน</th>
                    <th style="width:90px;">วันที่</th>
                    <th style="width:100px;text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($repairs): foreach($repairs as $r):
                    $img = $r['image'] ?? '';
                    if ($img && strpos($img, '/') === false) $img = '/uploads/repairs/' . $img;
                ?>
                <tr>
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
                        <div style="font-weight:700;font-size:13px;color:var(--text-main);"><?= h($r['title']) ?></div>
                        <?php if ($r['views']): ?>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">
                                <span class="material-symbols-rounded" style="font-size:12px;vertical-align:-2px;">visibility</span>
                                <?= number_format($r['views']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['category']): ?>
                            <span class="action-badge" style="background:var(--primary-light,#eff6ff);color:var(--primary);border-color:rgba(37,99,235,.2);">
                                <?= h($r['category']) ?>
                            </span>
                        <?php endif; ?>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($r['model']) ?></div>
                    </td>
                    <td>
                        <?php if ($r['slug']): ?>
                            <div style="font-size:12px;font-family:monospace;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">/work/<?= h($r['slug']) ?></div>
                        <?php else: ?>
                            <span style="font-size:11px;color:#f59e0b;font-weight:700;">⚠ ยังไม่มี slug</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['ticket_number']): ?>
                            <span class="action-badge badge-in">
                                <span class="material-symbols-rounded" style="font-size:12px;">task_alt</span>
                                <?= h($r['ticket_number']) ?>
                            </span>
                            <?php if ($r['customer_name']): ?>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:3px;"><?= h($r['customer_name']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;text-align:left;">
                        <?= $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '-' ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;justify-content:center;">
                            <a href="edit.php?id=<?= $r['id'] ?>" class="cmns-btn cmns-btn-secondary" style="padding:6px 14px;font-size:12px;">
                                <span class="material-symbols-rounded" style="font-size:14px;">edit</span> แก้ไข
                            </a>
                            <a href="delete.php?id=<?= $r['id'] ?>" onclick="return confirm('ลบผลงานนี้?')"
                               style="padding:6px 10px;font-size:12px;border:1px solid #fca5a5;border-radius:8px;color:#dc2626;text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:.15s;"
                               onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background=''">
                                <span class="material-symbols-rounded" style="font-size:14px;">delete</span>
                            </a>
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
    </div>

    <!-- Pagination -->
    <div class="cmns-footer-pagination">
        <div class="pagination-info">
            แสดง <b><?= min($total, $offset+1) ?>–<?= min($total, $offset+$per) ?></b> จาก <b><?= number_format($total) ?></b> รายการ
        </div>
        <div class="pagination-controls">
            <a href="<?= page_url(max(1,$page-1)) ?>" class="page-nav-btn <?= $page<=1?'disabled':'' ?>">
                <span class="material-symbols-rounded">chevron_left</span>
            </a>
            <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
                <a href="<?= page_url($i) ?>"
                   style="padding:4px 10px;border-radius:8px;border:1px solid var(--border);font-size:13px;font-weight:600;text-decoration:none;
                          <?= $i===$page ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : 'color:var(--text-main);background:var(--bg-surface-alt);' ?>">
                   <?= $i ?>
                </a>
            <?php endfor; ?>
            <a href="<?= page_url(min($pages,$page+1)) ?>" class="page-nav-btn <?= $page>=$pages?'disabled':'' ?>">
                <span class="material-symbols-rounded">chevron_right</span>
            </a>
            <select class="per-page-select" onchange="location.href=this.value">
                <?php foreach([10,20,50] as $pp): ?>
                    <option value="<?= '?'.http_build_query(array_merge($_GET,['per'=>$pp,'page'=>1])) ?>"
                            <?= $per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
