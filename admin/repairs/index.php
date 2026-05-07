<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$q     = trim($_GET['q']    ?? '');
$cat   = trim($_GET['cat']  ?? '');
$sort  = $_GET['sort']  ?? 'newest';
$per   = max(10, min(100, (int)($_GET['per']  ?? 20)));
$page  = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per;

$CATEGORIES = ['MacBook','iPhone','iPad','iMac','MacMini','AirPods','AppleWatch','Adapter','Other'];

$where  = [];
$params = [];
if ($q) { $where[] = '(r.title LIKE ? OR r.model LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat) { $where[] = 'r.category = ?'; $params[] = $cat; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$order_sql = match($sort) {
    'oldest' => 'r.created_at ASC',
    'title'  => 'r.title ASC',
    default  => 'r.created_at DESC',
};

$total = $pdo->prepare("SELECT COUNT(*) FROM repairs r $where_sql");
$total->execute($params);
$total = (int)$total->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);

$stmt = $pdo->prepare("
    SELECT r.*, t.ticket_number, t.customer_name
    FROM repairs r
    LEFT JOIN tracking t ON t.id = r.tracking_id
    $where_sql
    ORDER BY $order_sql
    LIMIT $per OFFSET $offset
");
$stmt->execute($params);
$repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function page_url(int $p): string {
    $q = $_GET; $q['page'] = $p;
    return '?' . http_build_query($q);
}

$pageTitle = 'ผลงานทั้งหมด';
include __DIR__ . '/../templates/header_admin.php';
?>
<style>
.rp-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;}
.rp-topbar h2{margin:0;font-size:20px;}
.btn-add{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--primary,#3b82f6);color:#fff;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none;}
.rp-filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;}
.rp-input{padding:8px 12px;border:1.5px solid var(--border,#e5e7eb);border-radius:8px;font-size:13px;background:var(--bg-surface,#fff);color:var(--text-main);}
.rp-input:focus{outline:none;border-color:var(--primary,#3b82f6);}
.btn-search{padding:8px 16px;background:var(--primary,#3b82f6);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;}
.rp-table{width:100%;border-collapse:collapse;}
.rp-table th{padding:10px 14px;text-align:left;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted,#6b7280);border-bottom:2px solid var(--border,#e5e7eb);}
.rp-table td{padding:10px 14px;border-bottom:1px solid var(--border,#f3f4f6);vertical-align:middle;font-size:14px;}
.rp-table tr:hover td{background:var(--bg-surface-alt,#f8fafc);}
.thumb{width:56px;height:56px;object-fit:cover;border-radius:8px;display:block;}
.thumb-empty{width:56px;height:56px;border-radius:8px;background:var(--border,#f3f4f6);display:flex;align-items:center;justify-content:center;color:var(--text-muted);}
.cat-badge{display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;background:var(--primary,#3b82f6)18;color:var(--primary,#3b82f6);}
.track-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:5px;font-size:11px;font-weight:600;background:#10b98118;color:#059669;}
.img-count{font-size:11px;color:var(--text-muted);margin-top:3px;}
.btn-edit{padding:5px 12px;border:1.5px solid var(--border,#e5e7eb);border-radius:6px;font-size:12px;font-weight:600;color:var(--text-main);text-decoration:none;background:none;}
.btn-edit:hover{border-color:var(--primary,#3b82f6);color:var(--primary,#3b82f6);}
.btn-del{padding:5px 12px;border:1.5px solid #fca5a5;border-radius:6px;font-size:12px;font-weight:600;color:#dc2626;text-decoration:none;background:none;cursor:pointer;}
.btn-del:hover{background:#fef2f2;}
.pager{display:flex;align-items:center;gap:8px;justify-content:space-between;margin-top:20px;flex-wrap:wrap;}
.pager-nav{display:flex;gap:4px;}
.pg-btn{padding:6px 12px;border:1.5px solid var(--border,#e5e7eb);border-radius:7px;font-size:13px;text-decoration:none;color:var(--text-main);background:none;}
.pg-btn.active,.pg-btn:hover{background:var(--primary,#3b82f6);color:#fff;border-color:var(--primary,#3b82f6);}
.pg-btn.disabled{opacity:.4;pointer-events:none;}
.flash-ok{background:#f0fdf4;border:1px solid #86efac;color:#16a34a;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;}
.total-info{font-size:13px;color:var(--text-muted);}
</style>

<div style="max-width:1100px;margin:0 auto;padding:24px 0 60px;">

  <?php if ($flash): ?><div class="flash-ok"><?= h($flash) ?></div><?php endif; ?>

  <div class="rp-topbar">
    <h2>ผลงานทั้งหมด <span style="font-size:14px;font-weight:400;color:var(--text-muted);">(<?= number_format($total) ?> รายการ)</span></h2>
    <a href="add.php" class="btn-add">
      <span class="material-symbols-rounded" style="font-size:18px;">add</span> เพิ่มผลงาน
    </a>
  </div>

  <form method="GET" class="rp-filters">
    <input type="text" name="q" class="rp-input" value="<?= h($q) ?>" placeholder="🔍 ค้นหาชื่อ / รุ่น" style="flex:1;min-width:200px;">
    <select name="cat" class="rp-input">
      <option value="">หมวดหมู่ทั้งหมด</option>
      <?php foreach($CATEGORIES as $c): ?>
        <option value="<?= h($c) ?>" <?= $cat===$c?'selected':'' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="sort" class="rp-input">
      <option value="newest" <?= $sort==='newest'?'selected':'' ?>>ล่าสุดก่อน</option>
      <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>เก่าสุดก่อน</option>
      <option value="title"  <?= $sort==='title' ?'selected':'' ?>>ชื่อ A→Z</option>
    </select>
    <button type="submit" class="btn-search">ค้นหา</button>
  </form>

  <div style="background:var(--bg-surface,#fff);border:1px solid var(--border,#e5e7eb);border-radius:14px;overflow:hidden;">
    <table class="rp-table">
      <thead>
        <tr>
          <th style="width:70px;">รูป</th>
          <th>ชื่อผลงาน</th>
          <th>หมวด / รุ่น</th>
          <th>ผูกงาน</th>
          <th>วันที่</th>
          <th style="width:130px;">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($repairs): foreach($repairs as $r):
          $img_src = $r['image'] ?? '';
          if ($img_src && strpos($img_src, '/') === false) {
              $img_src = '/uploads/repairs/' . $img_src;
          }
          ?>
          <tr>
            <td>
              <?php if ($img_src): ?>
                <img src="<?= h($img_src) ?>" class="thumb" alt="" loading="lazy">
              <?php else: ?>
                <div class="thumb-empty"><span class="material-symbols-rounded" style="font-size:20px;">image</span></div>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:600;"><?= h($r['title']) ?></div>
              <?php if ($r['slug']): ?>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">/work/<?= h($r['slug']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span class="cat-badge"><?= h($r['category'] ?? '-') ?></span>
              <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= h($r['model']) ?></div>
            </td>
            <td>
              <?php if ($r['ticket_number']): ?>
                <span class="track-badge">
                  <span class="material-symbols-rounded" style="font-size:13px;">task_alt</span>
                  <?= h($r['ticket_number']) ?>
                </span>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:12px;">—</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--text-muted);font-size:13px;"><?= $r['created_at'] ? date('d/m/Y', strtotime($r['created_at'])) : '-' ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="edit.php?id=<?= $r['id'] ?>" class="btn-edit">แก้ไข</a>
                <a href="delete.php?id=<?= $r['id'] ?>" class="btn-del" onclick="return confirm('ลบผลงานนี้?')">ลบ</a>
              </div>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted);">ยังไม่มีผลงาน</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pager">
    <span class="total-info">หน้า <?= $page ?>/<?= $pages ?></span>
    <nav class="pager-nav">
      <a class="pg-btn <?= $page<=1?'disabled':'' ?>" href="<?= page_url($page-1) ?>">‹</a>
      <?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
        <a class="pg-btn <?= $i===$page?'active':'' ?>" href="<?= page_url($i) ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a class="pg-btn <?= $page>=$pages?'disabled':'' ?>" href="<?= page_url($page+1) ?>">›</a>
    </nav>
    <select onchange="location.href=this.value" class="rp-input" style="font-size:12px;">
      <?php foreach([10,20,50] as $pp): ?>
        <option value="<?= h(http_build_query(array_merge($_GET,['per'=>$pp,'page'=>1]))) ?>" <?= $per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>
