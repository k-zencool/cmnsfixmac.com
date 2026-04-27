<?php
/********************************************************************
 * admin/tracking/history.php
 * ประวัติความเคลื่อนไหว (History) - Fixed Include Paths
 ********************************************************************/

session_start();
// ตั้งเวลา Server เป็นไทย
date_default_timezone_set('Asia/Bangkok');

// 1. เรียกไฟล์ระบบ (Database อยู่ข้างนอก admin ถอย 2 ชั้น ถูกแล้ว)
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "ประวัติความเคลื่อนไหว (History)";

/* --- Helpers --- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

// แปลงวันที่ (ไม่ต้องบวกเพิ่มเพราะ DB เป็นเวลาไทยแล้ว)
function get_thai_timestamp($strDate) {
    if(!$strDate) return false;
    return strtotime($strDate); 
}

function th_date($ts) {
    if(!$ts) return '-';
    $strYear = date("Y", $ts) + 543;
    $strDay= date("j", $ts);
    $strMonthCut = ["","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
    $strMonthThai = $strMonthCut[date("n", $ts)];
    return "$strDay $strMonthThai $strYear";
}

function th_time($ts) {
    if(!$ts) return '-';
    return date("H:i", $ts) . " น."; 
}

/* --- Status Map --- */
$statusMap = [
    'QS'  => ['label'=>'รอเช็คราคา',      'class'=>'badge-amber'],
    'WC'  => ['label'=>'รอคอนเฟิร์ม',     'class'=>'badge-blue'],
    'OK'  => ['label'=>'กำลังซ่อม',       'class'=>'badge-blue'],
    'RW'  => ['label'=>'งานแก้/เคลม',     'class'=>'badge-red'],
    'FN'  => ['label'=>'ซ่อมเสร็จ (รอรับ)', 'class'=>'badge-green'],
    'NCF' => ['label'=>'ติดต่อไม่ได้(เสร็จ)', 'class'=>'badge-gray'],
    'NCS' => ['label'=>'ติดต่อไม่ได้(เสนอ)',  'class'=>'badge-gray'],
    'XX'  => ['label'=>'ยกเลิก (รอรับคืน)', 'class'=>'badge-red'],
    'DV'  => ['label'=>'ส่งมอบ (ซ่อมเสร็จ)', 'class'=>'badge-gray'],
    'RT'  => ['label'=>'ยกเลิก (คืนแล้ว)',   'class'=>'badge-gray']
];

/* --- Filter & Query --- */
$q = getv('q', '');
$statusFilter = isset($_GET['status']) ? $_GET['status'] : [];
$dFrom = getv('date_from', '');
$dTo = getv('date_to', '');

$where = [];
$params = [];

if ($q) {
    $where[] = "(t.ticket_number LIKE :q OR t.customer_name LIKE :q OR t.customer_phone LIKE :q OR t.device_model LIKE :q)";
    $params[':q'] = "%$q%";
}
if (!empty($statusFilter)) {
    $in = []; foreach ($statusFilter as $i => $s) { $k=":s$i"; $in[]=$k; $params[$k]=$s; }
    $where[] = "t.status IN (" . implode(',', $in) . ")";
}
if ($dFrom) { $where[] = "DATE(t.updated_at) >= :df"; $params[':df'] = $dFrom; }
if ($dTo)   { $where[] = "DATE(t.updated_at) <= :dt"; $params[':dt'] = $dTo; }

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Query + Join
$sql = "SELECT t.*, u.username as admin_name 
        FROM tracking t 
        LEFT JOIN admin_users u ON t.updated_by = u.id 
        $whereSql 
        ORDER BY t.updated_at DESC LIMIT 150";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ แก้ไขตรงนี้: ถอยแค่ 1 ชั้น (../templates/) เพราะ templates อยู่ใน admin
require_once __DIR__ . '/../templates/header_admin.php';
?>

<style>
/* Table Specifics */
.data-table { table-layout: fixed; } 
.data-table th { white-space: nowrap; }
.data-table td { vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Custom Text */
.time-text { font-family: monospace; color: #64748b; font-weight: 600; font-size: 0.9rem; }
.job-link { font-family: monospace; font-weight: 700; color: #2563eb; text-decoration: none; font-size: 0.95rem; }
.job-link:hover { text-decoration: underline; }
.cust-name { font-weight: 600; color: #1e293b; display: block; font-size: 0.95rem; }
.cust-phone { font-size: 0.85rem; color: #64748b; }
.device-main { font-weight: 700; color: #334155; }
.device-sub { font-size: 0.85rem; color: #64748b; margin-left: 4px; }

/* Date Header Row */
.date-header { background: #f1f5f9; color: #334155; font-weight: 700; font-size: 0.9rem; padding: 10px 16px; border-bottom: 1px solid #e2e8f0; }
.date-header.today { background: #eff6ff; color: #1d4ed8; border-bottom: 1px solid #dbeafe; }
.date-header span { display: flex; align-items: center; gap: 8px; }

/* Override Filter Menu width for this page */
.filter-menu { width: 300px; }
</style>

<div id="global-loader" style="display:none; position:fixed; inset:0; background:rgba(255,255,255,0.8); z-index:9999; align-items:center; justify-content:center;">
  <div style="border:4px solid #f3f3f3; border-top:4px solid #3b82f6; border-radius:50%; width:40px; height:40px; animation:spin 1s linear infinite;"></div>
</div>
<style>@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}</style>

<main class="main" id="main-content">
    <div class="topbar">
        <span><?= h($pageTitle) ?></span>
        <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
    </div>

    <form action="" method="GET" class="search-form-bind" onsubmit="document.getElementById('global-loader').style.display='flex'">
        <div class="search-and-filter-group">
            <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา Job No. / ลูกค้า / รุ่น...">
            
            <div class="filter-dropdown">
                <button type="button" class="btn-secondary" onclick="document.getElementById('filterMenuHistory').classList.toggle('show')">
                    <span class="material-symbols-rounded">filter_list</span> ตัวกรอง
                </button>
                <div id="filterMenuHistory" class="filter-menu">
                    <div class="filter-section">
                        <div class="filter-title">สถานะงาน</div>
                        <div style="max-height:150px; overflow-y:auto;">
                            <?php foreach ($statusMap as $code => $info): $chk = in_array($code, $statusFilter) ? 'checked' : ''; ?>
                                <label class="checkline">
                                    <input type="checkbox" name="status[]" value="<?= $code ?>" <?= $chk ?>> 
                                    <?= h($info['label']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="filter-section">
                        <div class="filter-title">ช่วงเวลา</div>
                        <div style="display:flex; align-items:center; gap:5px;">
                            <input type="date" name="date_from" value="<?= h($dFrom) ?>" style="width:100%; padding:6px; border:1px solid #e5e7eb; border-radius:6px;">
                            <span>-</span>
                            <input type="date" name="date_to" value="<?= h($dTo) ?>" style="width:100%; padding:6px; border:1px solid #e5e7eb; border-radius:6px;">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <a href="history.php" class="btn-secondary" style="color:#ef4444; justify-content:center;">ล้างค่า</a>
                        <button type="submit" class="btn-primary">ใช้ตัวกรอง</button>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-search">ค้นหา</button>
        </div>
    </form>

    <div class="table-container">
        <table class="data-table">
            <colgroup>
                <col style="width: 100px;"> 
                <col style="width: 120px;"> 
                <col style="width: 20%;"> 
                <col style="width: 25%;"> 
                <col style="width: 15%;"> 
                <col style="width: 140px;"> 
                <col style="width: 60px;">  
            </colgroup>
            <thead>
                <tr>
                    <th class="text-left">เวลา</th>
                    <th class="text-left">Job No.</th>
                    <th class="text-left">ลูกค้า</th>
                    <th class="text-left">อุปกรณ์</th>
                    <th class="text-center">ผู้แก้ไข</th>
                    <th class="text-center">สถานะ</th>
                    <th class="text-center"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:50px; color:#94a3b8;">
                        <span class="material-symbols-rounded" style="font-size:48px; display:block; margin-bottom:10px; opacity:0.5;">history_toggle_off</span>
                        ไม่พบข้อมูลความเคลื่อนไหว
                    </td></tr>
                <?php else: 
                    $currDate = '';
                    $todayDate = date('Y-m-d'); 

                    foreach ($logs as $row): 
                        $ts = get_thai_timestamp($row['updated_at']);
                        $rawDate = date('Y-m-d', $ts);
                        $timeText = th_time($ts);
                        $st = $statusMap[$row['status']] ?? $statusMap['QS'];

                        // Grouping Date Header
                        if ($rawDate !== $currDate) {
                            $isToday = ($rawDate === $todayDate);
                            $headerClass = $isToday ? 'date-header today' : 'date-header';
                            $dateText = th_date($ts) . ($isToday ? ' (วันนี้)' : '');
                            $icon = $isToday ? 'today' : 'calendar_month';
                            echo "<tr><td colspan='7' class='{$headerClass}'><span><span class='material-symbols-rounded' style='font-size:20px;'>{$icon}</span> {$dateText}</span></td></tr>";
                            $currDate = $rawDate;
                        }
                ?>
                    <tr>
                        <td class="text-left time-text"><?= $timeText ?></td>
                        <td class="text-left"><a href="edit.php?id=<?= $row['id'] ?>" class="job-link" onclick="document.getElementById('global-loader').style.display='flex'"><?= h($row['ticket_number']) ?></a></td>
                        <td class="text-left">
                            <span class="cust-name"><?= h($row['customer_name']) ?></span>
                            <span class="cust-phone"><?= h($row['customer_phone']) ?></span>
                        </td>
                        <td class="text-left">
                            <div><span class="device-main"><?= h($row['device_type']) ?></span><span class="device-sub"><?= h($row['device_model']) ?></span></div>
                        </td>
                        
                        <td class="text-center">
                            <?php if(!empty($row['admin_name'])): ?>
                                <span style="font-size:0.9rem; color:#1e293b; font-weight:600;"><?= h($row['admin_name']) ?></span>
                            <?php else: ?>
                                <span style="font-size:0.9rem; color:#cbd5e1;">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
                        </td>
                        <td class="text-center">
                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn-icon" title="ดูรายละเอียด" onclick="document.getElementById('global-loader').style.display='flex'">
                                <span class="material-symbols-rounded">chevron_right</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/../templates/footer_admin.php'; ?>

<script>
    function toggleMenu(id) { document.getElementById(id).classList.toggle('show'); }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.filter-dropdown')) { document.querySelectorAll('.filter-menu.show').forEach(m => m.classList.remove('show')); }
    });
</script>