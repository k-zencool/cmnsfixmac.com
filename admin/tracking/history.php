<?php
/********************************************************************
 * admin/tracking/history.php
 * Final Fix V3: เวลาตรงเป๊ะ (ไม่ต้องบวกเพิ่มเพราะ DB เป็นเวลาไทยแล้ว)
 ********************************************************************/

session_start();
// ตั้งเวลา Server เป็นไทย
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = "ประวัติความเคลื่อนไหว (History)";

/* --- Helpers --- */
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function getv($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

// ** แก้ไขล่าสุด: ไม่ต้องบวก 25200 แล้ว เพราะใน DB เป็นเวลาไทยอยู่แล้ว **
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
    'OK'  => ['label'=>'กำลังซ่อม',       'class'=>'badge-purple'],
    'RW'  => ['label'=>'งานแก้/เคลม',     'class'=>'badge-red'],
    'FN'  => ['label'=>'ซ่อมเสร็จ (รอรับ)', 'class'=>'badge-green'],
    'NCF' => ['label'=>'ติดต่อไม่ได้(เสร็จ)', 'class'=>'badge-gray'],
    'NCS' => ['label'=>'ติดต่อไม่ได้(เสนอ)',  'class'=>'badge-gray'],
    'XX'  => ['label'=>'ยกเลิก (รอรับคืน)', 'class'=>'badge-red'],
    'DV'  => ['label'=>'ส่งมอบ (ซ่อมเสร็จ)', 'class'=>'badge-dark'],
    'RT'  => ['label'=>'ยกเลิก (คืนแล้ว)',   'class'=>'badge-dark']
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

include __DIR__ . '/../../templates/header_admin.php';
include __DIR__ . '/../../templates/sidebar_admin.php';
?>

<style>
/* Layout */
.table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.data-table { width: 100%; border-collapse: collapse; table-layout: fixed; } 

/* Columns */
.data-table th { background: #f8fafc; padding: 12px 14px; text-align: left; font-weight: 600; color: #475569; font-size: 0.9rem; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
.data-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; color: #1e293b; vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.data-table tr:last-child td { border-bottom: none; }

/* Alignments */
.text-center { text-align: center !important; }
.text-left { text-align: left !important; }

/* Specific Text */
.time-text { font-family: monospace; color: #64748b; font-weight: 600; font-size: 0.9rem; }
.job-link { font-family: monospace; font-weight: 700; color: #2563eb; text-decoration: none; font-size: 0.95rem; }
.job-link:hover { text-decoration: underline; }
.cust-name { font-weight: 600; color: #1e293b; display: block; font-size: 0.95rem; }
.cust-phone { font-size: 0.85rem; color: #64748b; }
.device-main { font-weight: 700; color: #334155; }
.device-sub { font-size: 0.85rem; color: #64748b; margin-left: 4px; }

/* Badge */
.badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; min-width: 90px; text-align: center; }
.badge-amber { background: #fef3c7; color: #92400e; }
.badge-blue { background: #e0f2fe; color: #075985; }
.badge-purple { background: #f3e8ff; color: #6b21a8; }
.badge-red { background: #fee2e2; color: #991b1b; }
.badge-green { background: #dcfce7; color: #166534; }
.badge-dark { background: #1f2937; color: #fff; }
.badge-gray { background: #f3f4f6; color: #4b5563; }

/* Date Row */
.date-header { background: #f1f5f9; color: #334155; font-weight: 700; font-size: 0.9rem; padding: 10px 16px; border-bottom: 1px solid #e2e8f0; }
.date-header.today { background: #eff6ff; color: #1d4ed8; border-bottom: 1px solid #dbeafe; }
.date-header span { display: flex; align-items: center; gap: 8px; }

/* Search Bar */
.search-form-bind { margin-bottom: 20px; }
.search-and-filter-group { display: flex; gap: 10px; align-items: center; background: #fff; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
.filter-input { flex-grow: 1; padding: 8px 12px; border: none; font-size: 0.95rem; outline: none; }
.filter-dropdown { position: relative; border-left: 1px solid #e5e7eb; padding-left: 10px; }
.filter-menu { position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); padding: 15px; width: 280px; z-index: 50; display: none; margin-top: 10px; }
.filter-menu.show { display: block; }
.filter-section { margin-bottom: 15px; }
.checkline { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 0.9rem; color: #4b5563; cursor: pointer; }
.range-inline { display: flex; gap: 5px; align-items: center; }
.range-inline input { width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px; }
.filter-actions { display: flex; justify-content: space-between; margin-top: 15px; pt: 10px; border-top: 1px solid #f3f4f6; }
.btn-secondary { background: #fff; border: 1px solid #d1d5db; color: #374151; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 0.9rem; display: flex; align-items: center; gap: 4px; }
.btn-primary { background: #2563eb; border: 1px solid #2563eb; color: #fff; padding: 6px 14px; border-radius: 6px; cursor: pointer; }
.btn-search { background: #2563eb; color: #fff; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; margin-left: 5px; }
</style>

<main class="main" id="main-content">
    <div class="topbar">
        <span><?= h($pageTitle) ?></span>
        <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
    </div>

    <form action="" method="GET" class="search-form-bind">
        <div class="search-and-filter-group">
            <span class="material-symbols-rounded" style="color:#9ca3af; margin-left:5px;">search</span>
            <input class="filter-input" name="q" value="<?= h($q) ?>" placeholder="ค้นหา Job No. / ลูกค้า / รุ่น...">
            
            <div class="filter-dropdown">
                <button type="button" class="btn-secondary" onclick="document.getElementById('filterMenu').classList.toggle('show')">
                    <span class="material-symbols-rounded" style="font-size:18px;">filter_list</span> ตัวกรอง
                </button>
                <div id="filterMenu" class="filter-menu">
                    <div class="filter-section">
                        <div style="font-weight:600; margin-bottom:8px;">สถานะงาน</div>
                        <div style="max-height:150px; overflow-y:auto;">
                            <?php foreach ($statusMap as $code => $info): $chk = in_array($code, $statusFilter) ? 'checked' : ''; ?>
                                <label class="checkline"><input type="checkbox" name="status[]" value="<?= $code ?>" <?= $chk ?>> <?= h($info['label']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="filter-section">
                        <div style="font-weight:600; margin-bottom:8px;">ช่วงเวลา</div>
                        <div class="range-inline">
                            <input type="date" name="date_from" value="<?= h($dFrom) ?>"><span>-</span><input type="date" name="date_to" value="<?= h($dTo) ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <a href="history.php" style="color:#ef4444; text-decoration:none; font-size:0.9rem; align-self:center;">ล้างค่า</a>
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
                <col style="width: 10%;"> <col style="width: 12%;"> <col style="width: 20%;"> <col style="width: 25%;"> <col style="width: 13%;"> <col style="width: 15%;"> <col style="width: 5%;">  </colgroup>
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
                        // ** ตรงนี้ไม่ต้องบวก 25200 แล้ว **
                        $ts = get_thai_timestamp($row['updated_at']);
                        
                        $rawDate = date('Y-m-d', $ts);
                        $timeText = th_time($ts);
                        $st = $statusMap[$row['status']] ?? $statusMap['QS'];

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
                        <td class="text-left"><a href="edit.php?id=<?= $row['id'] ?>" class="job-link"><?= h($row['ticket_number']) ?></a></td>
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
                            <a href="edit.php?id=<?= $row['id'] ?>" style="color:#94a3b8; text-decoration:none;" title="ดูรายละเอียด">
                                <span class="material-symbols-rounded">chevron_right</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/../../templates/footer_admin.php'; ?>

<script>
    function toggleMenu(id) { document.getElementById(id).classList.toggle('show'); }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.filter-dropdown')) { document.querySelectorAll('.filter-menu.show').forEach(m => m.classList.remove('show')); }
    });
</script>