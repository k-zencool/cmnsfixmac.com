<?php
/********************************************************************
 * admin/tracking/index.php
 * หน้ารายการงานซ่อม (Tracking List) - Smart Search Version
 * Features:
 * - Smart Search: ค้นหาด้วยการเว้นวรรค (Keyword Splitting)
 * - Coverage: Job, Name, Phone, SN, Model, Series, Problem
 ********************************************************************/

session_start();
// ตั้งเวลาไทย
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

/* ======================= Helpers ======================= */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

// ฟังก์ชันตัด HTML Tags
function strip_html_content($text) {
    if (!$text) return '-';
    return trim(strip_tags(html_entity_decode($text)));
}

function get_pager(): array{
  $per  = max(5, min(200, (int)getv('per', 20)));
  $page = max(1, (int)getv('page', 1));
  return [$per, $page, ($page-1)*$per];
}
function page_url($i){
  $q = $_GET; $q['page'] = max(1,(int)$i);
  return '?'.http_build_query($q);
}

/* ======================= State & Logic ======================= */
$q = getv('q','');
$dfrom = getv('date_from','');
$dto = getv('date_to','');
$group = getv('group', 'all'); 

$statusFilter = isset($_GET['status']) ? (array)$_GET['status'] : [];

$pageTitle = "รายการงานซ่อมทั้งหมด";

// Auto Group Logic
if (empty($statusFilter)) {
    if ($group === 'active') {
        $statusFilter = ['QS', 'WC', 'OK', 'RW'];
        $pageTitle = "รายการกำลังดำเนินการ";
    } elseif ($group === 'done') {
        $statusFilter = ['FN'];
        $pageTitle = "รายการซ่อมเสร็จ/รอรับ";
    }
}

[$per,$page,$offset] = get_pager();

/* ======================= Status Map ======================= */
$statusMap = [
    'QS'  => ['label'=>'รอเช็คราคา',      'class'=>'badge-amber'],
    'WC'  => ['label'=>'รอคอนเฟิร์ม',     'class'=>'badge-blue'],
    'OK'  => ['label'=>'กำลังซ่อม',       'class'=>'badge-purple'],
    'RW'  => ['label'=>'งานแก้/เคลม',     'class'=>'badge-red'],
    'FN'  => ['label'=>'ซ่อมเสร็จ (รอรับ)', 'class'=>'badge-green'],
    'NCF' => ['label'=>'ติดต่อไม่ได้(เสร็จ)', 'class'=>'badge-gray'],
    'NCS' => ['label'=>'ติดต่อไม่ได้(เสนอ)',  'class'=>'badge-gray'],
    'XX'  => ['label'=>'ยกเลิก (รอรับคืน)', 'class'=>'badge-red'],
    'DV'  => ['label'=>'ส่งมอบ (ซ่อมเสร็จ)', 'class'=>'badge-dark'],
    'RT'  => ['label'=>'ยกเลิก (คืนแล้ว)',   'class'=>'badge-dark']
];

/* ======================= Action: Delete ======================= */
$msg = getv('msg','');
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $delId = (int)$_POST['id'];
    $pdo->prepare("DELETE FROM tracking WHERE id = ?")->execute([$delId]);
    header("Location: index.php?msg=" . urlencode("ลบรายการเรียบร้อย"));
    exit;
}

/* ======================= Query Builder (Smart Search) ======================= */
$where = [];
$params = [];

if ($q) {
    // 1. หั่นคำค้นหาด้วยช่องว่าง (เช่น "A2337 จอแตก" => ["A2337", "จอแตก"])
    $keywords = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
    
    // 2. วนลูปสร้างเงื่อนไข "ต้องเจอทุกคำ" (AND Logic ระหว่างคำ)
    foreach ($keywords as $index => $word) {
        $keyParam = ":q{$index}"; // สร้างชื่อตัวแปร SQL เช่น :q0, :q1
        
        // แต่ละคำ ต้องไปโผล่ในคอลัมน์ไหนก็ได้ (OR Logic ภายในคำนั้น)
        $where[] = "(
            ticket_number   LIKE $keyParam OR 
            customer_name   LIKE $keyParam OR 
            customer_phone  LIKE $keyParam OR 
            serial_number   LIKE $keyParam OR 
            device_model    LIKE $keyParam OR 
            device_series   LIKE $keyParam OR 
            problem_details LIKE $keyParam
        )";
        
        $params[$keyParam] = "%{$word}%";
    }
}

// Filter สถานะ
if (!empty($statusFilter)) {
    $in = [];
    foreach($statusFilter as $i => $s) {
        $key = ":s$i"; $in[] = $key; $params[$key] = $s;
    }
    $where[] = "status IN (" . implode(',', $in) . ")";
}

// Filter วันที่
if ($dfrom !== '') { $where[] = "DATE(created_at) >= :df"; $params[':df'] = $dfrom; }
if ($dto   !== '') { $where[] = "DATE(created_at) <= :dt"; $params[':dt'] = $dto; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count Total
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tracking $whereSql");
foreach($params as $k => $v) $stmtCount->bindValue($k, $v);
$stmtCount->execute();
$total = (int)($stmtCount->fetchColumn() ?: 0);

// Pagination
$pages = max(1, (int)ceil($total/$per));
if ($page > $pages){ $page = $pages; $offset = ($page-1)*$per; }

// Fetch Data
$sql = "SELECT * FROM tracking $whereSql ORDER BY created_at DESC LIMIT :limit OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../templates/header_admin.php';

// ❌ ลบ Sidebar ออก (เพราะ header เรียกให้แล้ว)
// include __DIR__ . '/../../templates/sidebar_admin.php'; 
?>

<style>
/* --- Badge Styles --- */
.badge { 
    padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; 
    display: inline-block; width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
    text-align: center; border: 1px solid transparent; 
}
.badge-amber { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
.badge-blue { background: #e0f2fe; color: #075985; border-color: #bae6fd; }
.badge-purple { background: #f3e8ff; color: #6b21a8; border-color: #d8b4fe; }
.badge-red { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
.badge-green { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
.badge-dark { background: #1f2937; color: #fff; border-color: #374151; }
.badge-gray { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }

/* --- Table Text Styles --- */
.job-link { font-family: monospace; font-weight: 700; color: #2563eb; text-decoration: none; font-size: 0.95rem; }
.job-link:hover { text-decoration: underline; }
.cust-info { line-height: 1.3; }
.cust-name { font-weight: 600; color: #1e293b; display: block; }
.cust-phone { font-size: 0.8rem; color: #64748b; }

/* --- Device Info Styles (UPDATED) --- */
.device-wrapper { line-height: 1.25; text-align: center; }
.dev-top-line { font-size: 0.95rem; color: #1e293b; } /* บรรทัดบน */
.dev-type { font-weight: 700; } /* ตัวหนา */
.dev-series { font-weight: 400; color: #334155; margin-left: 4px; } /* ตัวปกติ */
.dev-bottom-line { font-size: 0.8rem; color: #94a3b8; display: block; margin-top: 2px; } /* บรรทัดล่าง (Model) */

.prob-text { font-size: 0.85rem; color: #ef4444; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: bottom; }
.sn-text { font-family: monospace; font-size: 0.85rem; color: #475569; display: block; }
.pass-text { font-family: monospace; font-size: 0.85rem; color: #dc2626; font-weight: 600; display: block; margin-top: 2px; }

/* --- Time Remaining Colors --- */
.text-ok { color: #16a34a; font-weight: 600; font-size: 0.85rem; }
.text-warn { color: #d97706; font-weight: 600; font-size: 0.85rem; }
.text-danger { color: #dc2626; font-weight: 600; font-size: 0.85rem; }

/* --- Row Highlights --- */
.tr-done { background-color: #f8fafc; }
.tr-done td { color: #94a3b8; }
.tr-done .cust-name, .tr-done .dev-top-line, .tr-done .dev-series, .tr-done .dev-bottom-line, .tr-done .sn-text, .tr-done .pass-text, .tr-done .prob-text { color: #94a3b8 !important; }
.tr-done a.job-link { color: #94a3b8 !important; text-decoration: line-through; }
.tr-done .badge { opacity: 0.6; filter: grayscale(100%); }
.tr-overdue { background-color: #fef2f2; } 
.tr-today { background-color: #fff7ed; }

/* --- Utils --- */
.data-table th { white-space: nowrap; }
.text-left { text-align: left !important; }
.text-right { text-align: right !important; }
.text-center { text-align: center !important; }

/* --- Buttons --- */
.btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; transition: all 0.2s; text-decoration: none; padding: 0; }
.btn-icon:hover { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; }
.btn-icon.print:hover { color: #475569; border-color: #94a3b8; background: #f1f5f9; }
.btn-icon.edit:hover { color: #2563eb; background: #eff6ff; border-color: #bfdbfe; }
.btn-icon.del:hover { color: #ef4444; background: #fef2f2; border-color: #fecaca; }
.material-symbols-rounded { font-size: 18px; line-height: 1; }

/* --- Modal --- */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); z-index: 1000; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s ease-in-out; }
.modal-overlay.show { display: flex; opacity: 1; }
.modal-content { background: white; width: 90%; max-width: 400px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); transform: scale(0.95); transition: transform 0.2s ease-in-out; overflow: hidden; }
.modal-overlay.show .modal-content { transform: scale(1); }
.modal-header { padding: 20px; text-align: center; background: #fef2f2; border-bottom: 1px solid #fee2e2; }
.modal-icon { width: 50px; height: 50px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px auto; }
.modal-title { font-size: 1.25rem; font-weight: 700; color: #991b1b; margin: 0; }
.modal-body { padding: 25px 20px; text-align: center; color: #4b5563; font-size: 1rem; line-height: 1.5; }
.modal-body strong { color: #1f2937; }
.modal-footer { padding: 15px 20px; background: #f9fafb; border-top: 1px solid #f3f4f6; display: flex; gap: 10px; justify-content: center; }
.btn-modal-cancel { padding: 8px 16px; border-radius: 6px; border: 1px solid #d1d5db; background: white; color: #374151; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.btn-modal-cancel:hover { background: #f3f4f6; }
.btn-modal-confirm { padding: 8px 16px; border-radius: 6px; border: none; background: #dc2626; color: white; font-weight: 600; cursor: pointer; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: all 0.2s; }
.btn-modal-confirm:hover { background: #b91c1c; }

/* --- Loading Spinner --- */
#global-loader { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 9999; flex-direction: column; align-items: center; justify-content: center; }
.loader-spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3b82f6; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin-bottom: 10px; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>

<div id="global-loader">
  <div class="loader-spinner"></div>
  <div style="font-weight:600; color:#4b5563;">กำลังโหลด...</div>
</div>

<main class="main" id="main-content">
  <div class="topbar">
    <span><?= h($pageTitle) ?></span>
    <a href="../../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="section-header">
    <h2><?= h($pageTitle) ?></h2>
    <div>
        <a href="create.php" class="btn-primary" onclick="showLoader(event)">+ เปิดงานซ่อมใหม่</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>

  <form action="index.php" method="GET" class="search-form-bind" onsubmit="showLoader(event)">
    <div class="search-and-filter-group">
        <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา... (Job / ชื่อ / เบอร์ / รุ่น / อาการ)">
        
        <?php if($group != 'all'): ?><input type="hidden" name="group" value="<?= h($group) ?>"><?php endif; ?>

        <div class="filter-dropdown">
          <button type="button" class="btn-secondary" onclick="toggleMenu('filterMenuTrack')">ตัวกรอง</button>
          <div id="filterMenuTrack" class="filter-menu">
            <div class="filter-section">
              <div class="filter-title">สถานะงาน (Override)</div>
              <?php foreach ($statusMap as $k => $v): $checked = in_array($k, $statusFilter) ? 'checked' : ''; ?>
                <label class="checkline">
                    <input type="checkbox" name="status[]" value="<?= $k ?>" <?= $checked ?>>
                    <span><?= h($v['label']) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="filter-section">
              <div class="filter-title">วันที่รับงาน</div>
              <div class="range-inline">
                <input type="date" name="date_from" value="<?= h($dfrom) ?>">
                <span class="mx-4">ถึง</span>
                <input type="date" name="date_to" value="<?= h($dto) ?>">
              </div>
            </div>
            <div class="filter-actions">
              <button type="button" class="btn-secondary" onclick="clearMenu('filterMenuTrack')">ล้างค่า</button>
              <button type="submit" class="btn-primary">ค้นหา</button>
            </div>
          </div>
        </div>

        <button class="btn-search">ค้นหา</button>
        <?php if($q || !empty($_GET['status']) || $dfrom): ?>
            <a href="index.php?group=<?= h($group) ?>" class="btn-secondary" style="color:#ef4444;" onclick="showLoader(event)">Reset</a>
        <?php endif; ?>
    </div>
  </form>

  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th width="110">Job No.</th>
          <th width="90">วันที่รับ</th>
          <th>ลูกค้า</th>
          <th class="text-center">อุปกรณ์</th>
          <th class="text-left">S/N & Pass</th>
          <th>อาการเสีย</th>
          <th width="100">กำหนดนัด</th>
          <th width="90">เหลือเวลา</th> <th width="90" class="text-right">ราคา</th>
          <th width="160" class="text-center">สถานะ</th>
          <th width="130" class="text-center">จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($jobs)): ?>
          <tr><td colspan="11" class="text-center" style="padding:40px; color:#94a3b8;">ไม่พบข้อมูลงานซ่อม</td></tr>
        <?php else: foreach($jobs as $row): 
            $stCode = $row['status'] ?? 'QS';
            $stData = $statusMap[$stCode] ?? ['label'=>$stCode, 'class'=>'badge-gray'];
            $dateIn = date('d/m/y', strtotime($row['created_at']));
            $dateApp = $row['appointment_date'] ? date('d/m/y', strtotime($row['appointment_date'])) : '-';
            
            $isDone = in_array($stCode, ['DV', 'RT']); 
            
            // Clean HTML
            $cleanProblem = strip_html_content($row['problem_details']);
            
            $rowClass = '';
            $timeText = '-';
            $timeClass = '';
            
            if ($isDone) {
                $rowClass = 'tr-done';
            } else {
                if (!empty($row['appointment_date'])) {
                    $today = strtotime(date('Y-m-d'));
                    $target = strtotime(date('Y-m-d', strtotime($row['appointment_date'])));
                    $diff = $target - $today;
                    $days = $diff / 86400;

                    if ($days > 0) {
                        $timeText = "อีก " . number_format($days) . " วัน";
                        $timeClass = "text-ok";
                    } elseif ($days == 0) {
                        $timeText = "วันนี้";
                        $timeClass = "text-warn";
                        $rowClass = "tr-today"; 
                    } else {
                        $timeText = "เกิน " . number_format(abs($days)) . " วัน";
                        $timeClass = "text-danger";
                        $rowClass = "tr-overdue";
                    }
                }
            }
        ?>
          <tr class="<?= $rowClass ?>">
            <td>
              <a href="edit.php?id=<?= $row['id'] ?>" class="job-link" onclick="showLoader(event)"><?= h($row['ticket_number']) ?></a>
            </td>
            <td class="muted"><?= $dateIn ?></td>
            <td>
                <div class="cust-info">
                    <span class="cust-name"><?= h($row['customer_name']) ?></span>
                    <span class="cust-phone"><?= h($row['customer_phone']) ?></span>
                </div>
            </td>
            
            <td class="text-center">
                <div class="device-wrapper">
                    <div class="dev-top-line">
                        <span class="dev-type"><?= h($row['device_type']) ?></span>
                        <span class="dev-series"><?= h($row['device_series'] ?? '') ?></span>
                    </div>
                    <span class="dev-bottom-line"><?= h($row['device_model']) ?></span>
                </div>
            </td>
            
            <td class="text-left">
                <span class="sn-text">SN: <?= h($row['serial_number']?:'-') ?></span>
                <span class="pass-text">Pass: <?= h($row['device_password']?:'-') ?></span>
            </td>

            <td>
              <span class="prob-text" title="<?= h($cleanProblem) ?>">
                <?= h($cleanProblem) ?>
              </span>
            </td>
            <td class="muted"><?= $dateApp ?></td>
            
            <td>
                <span class="<?= $timeClass ?>"><?= $timeText ?></span>
            </td>

            <td class="text-right" style="font-weight:600;"><?= number_format($row['estimated_cost']) ?></td>
            <td class="text-center">
              <span class="badge <?= $stData['class'] ?>"><?= $stData['label'] ?></span>
            </td>
            
            <td class="text-center">
                <div style="display:flex; justify-content:center; gap:6px;">
                    <a href="print_job.php?id=<?= $row['id'] ?>" target="_blank" class="btn-icon print" title="พิมพ์ใบรับซ่อม">
                        <span class="material-symbols-rounded" style="font-size:18px;">print</span>
                    </a>
                    
                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn-icon edit" title="แก้ไข" onclick="showLoader(event)">
                        <span class="material-symbols-rounded" style="font-size:18px;">edit</span>
                    </a>
                    
                    <button type="button" class="btn-icon del" title="ลบ" 
                        onclick="openDeleteModal(<?= $row['id'] ?>, '<?= h($row['ticket_number']) ?>')">
                        <span class="material-symbols-rounded" style="font-size:18px;">delete</span>
                    </button>
                </div>
            </td>

          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pager-bar">
    <div class="pager-left">
      <span class="pager-total">พบ <?= (int)$total ?> รายการ</span>
      <span class="divider">•</span>
      <span>หน้า <?= (int)$page ?> / <?= (int)$pages ?></span>
    </div>
    <nav class="pager-nav">
      <a class="page-btn <?= $page<=1?'is-disabled':''?>" href="<?= $page>1?page_url($page-1):'#'?>" onclick="showLoader(event)">‹</a>
      <?php 
      $start=max(1,$page-2); $end=min($pages,$page+2);
      if($start>1) echo '<span class="page-ellipsis">…</span>';
      for($i=$start;$i<=$end;$i++): ?>
        <a class="page-btn <?= $i==$page?'is-active':''?>" href="<?= page_url($i) ?>" onclick="showLoader(event)"><?= $i ?></a>
      <?php endfor; 
      if($end<$pages) echo '<span class="page-ellipsis">…</span>'; ?>
      <a class="page-btn <?= $page>=$pages?'is-disabled':''?>" href="<?= $page<$pages?page_url($page+1):'#'?>" onclick="showLoader(event)">›</a>
      <div class="page-size">
        <select id="ppSelect" class="pager-select">
          <?php foreach([20,50,100] as $pp): ?>
            <option value="<?= $pp ?>" <?= (int)$per===$pp?'selected':'' ?>><?= $pp ?>/หน้า</option>
          <?php endforeach; ?>
        </select>
      </div>
    </nav>
  </div>

  <div id="deleteModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-icon">
                <span class="material-symbols-rounded" style="font-size: 32px;">warning</span>
            </div>
            <h3 class="modal-title">ยืนยันการลบ?</h3>
        </div>
        <div class="modal-body">
            คุณต้องการลบรายการ <strong id="delTicketNum">...</strong> ใช่หรือไม่?<br>
            <span style="font-size:0.9em; color:#ef4444;">การกระทำนี้ไม่สามารถย้อนกลับได้</span>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeDeleteModal()">ยกเลิก</button>
            <form method="post" id="deleteForm" onsubmit="showLoader(event)">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delInputId" value="">
                <button type="submit" class="btn-modal-confirm">ลบรายการ</button>
            </form>
        </div>
    </div>
  </div>

</main>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
  // Robust Loader
  function showLoader(e) {
      if (e && (e.metaKey || e.ctrlKey || e.shiftKey)) return;
      
      const target = e.currentTarget || e.target;
      if (target.tagName === 'A' && (target.getAttribute('href') === '#' || target.getAttribute('href') === 'javascript:void(0)')) return;

      document.getElementById('global-loader').style.display = 'flex';
      
      // Auto Hide (Safety)
      setTimeout(function(){
          document.getElementById('global-loader').style.display = 'none';
      }, 5000);
  }

  // Hide on Back Button / Page Show
  window.addEventListener('pageshow', function(event) {
      document.getElementById('global-loader').style.display = 'none';
  });

  // Filter
  function toggleMenu(id){ var m=document.getElementById(id); if(m) m.classList.toggle('show'); }
  function clearMenu(id){
    var root=document.getElementById(id); if(!root) return;
    root.querySelectorAll('input[type="checkbox"]').forEach(el=>el.checked=false);
    root.querySelectorAll('input[type="date"]').forEach(el=>el.value='');
  }
  document.addEventListener('click', function(e){
    var dd = e.target.closest('.filter-dropdown');
    if(!dd) document.querySelectorAll('.filter-menu.show').forEach(m => m.classList.remove('show'));
  });

  // Modal
  function openDeleteModal(id, ticket) {
      document.getElementById('delInputId').value = id;
      document.getElementById('delTicketNum').innerText = ticket;
      
      const modal = document.getElementById('deleteModal');
      modal.style.display = 'flex';
      modal.offsetHeight; // trigger reflow
      modal.classList.add('show');
  }
  function closeDeleteModal() {
      const m = document.getElementById('deleteModal');
      m.classList.remove('show');
      setTimeout(() => { m.style.display = 'none'; }, 200); 
  }
  document.getElementById('deleteModal').addEventListener('click', function(e) {
      if (e.target === this) closeDeleteModal();
  });

  // Pagination Select
  (function(){
    const sel=document.getElementById('ppSelect'); if(!sel) return;
    sel.addEventListener('change',function(){
      showLoader();
      const u=new URL(location.href);
      u.searchParams.set('per',this.value);
      u.searchParams.set('page','1');
      location=u.toString();
    });
  })();

  // Arrow Keys
  (function(){
    document.addEventListener('keydown',function(e){
      if(e.altKey||e.metaKey||e.ctrlKey) return;
      if(document.activeElement.tagName === 'INPUT') return;
      if(e.key==='ArrowRight') { const next = document.querySelector('.page-btn[href*="page="]:not(.is-disabled):last-of-type'); if(next) { showLoader(); next.click(); } }
      if(e.key==='ArrowLeft')  { const prev = document.querySelector('.page-btn[href*="page="]:not(.is-disabled):first-of-type'); if(prev) { showLoader(); prev.click(); } }
    });
  })();
</script>