<?php
/*
 * admin/reports/print_all_parts.php
 *
 * หน้ารายงานสต็อกอะไหล่ทั้งหมด (มือ 1, มือ 2, เครื่อง) สำหรับปริ้น
 * (parts_new, parts_used, parts_donors)
 *
 * [v4 - 2025-10-30] อัปเดตที่อยู่
 * [v5 - 2025-10-31] เพิ่มตัวกรองสถานะ (status filter)
 ********************************************************************/

session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

defined('BASE_URL') or define('BASE_URL', 'https://cmnsfixmac.com');
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// สถานะเครื่อง
$DONOR_STATUS = [
  'in_stock' => 'พร้อมแยก',
  'reserved' => 'จอง',
  'for_sale' => 'กำลังขาย',
  'stripped' => 'แยกแล้ว',
  'sold'     => 'ขายแล้ว'
];

// --- 1. รับค่าตัวกรองสถานะ ---
$filter_status = $_GET['status'] ?? 'all'; // 'all', 'in_stock', 'reserved', 'for_sale'


try {
    // --- 2. สร้าง SQL Query แบบไดนามิกตามตัวกรอง ---
    
    $sql_parts = []; // массив для хранения частей UNION
    $params = [];    // массив для хранения параметров PDO

    // --- ส่วนที่ 1: อะไหล่มือ 1 (parts_new) ---
    // จะแสดงเฉพาะเมื่อเลือก 'all' หรือ 'in_stock'
    if ($filter_status === 'all' || $filter_status === 'in_stock') {
        $sql_parts[] = "
        (
            SELECT
                'มือ 1' AS part_type, part_code, MAX(part_name) AS part_name,
                MAX(category) AS category, SUM(quantity) AS quantity,
                GROUP_CONCAT(DISTINCT location ORDER BY location SEPARATOR ',') AS location,
                'In Stock' AS status_label
            FROM parts_new
            WHERE quantity > 0
            GROUP BY part_code
        )
        ";
    }

    // --- ส่วนที่ 2: อะไหล่มือ 2 (parts_used) ---
    // จะแสดงเฉพาะเมื่อเลือก 'all' หรือ 'in_stock'
    if ($filter_status === 'all' || $filter_status === 'in_stock') {
        $sql_parts[] = "
        (
            SELECT
                'มือ 2' AS part_type, part_code, MAX(part_name) AS part_name,
                MAX(category) AS category, COUNT(*) AS quantity,
                GROUP_CONCAT(DISTINCT location ORDER BY location SEPARATOR ',') AS location,
                'In Stock' AS status_label
            FROM parts_used
            GROUP BY part_code
        )
        ";
    }

    // --- ส่วนที่ 3: เครื่อง (parts_donors) ---
    // กำหนดสถานะที่จะ Query จากตาราง donors
    $donor_statuses_to_query = [];
    if ($filter_status === 'all') {
        $donor_statuses_to_query = ['in_stock', 'reserved', 'for_sale'];
    } elseif (in_array($filter_status, ['in_stock', 'reserved', 'for_sale'])) {
        $donor_statuses_to_query = [$filter_status];
    }

    // ถ้ามีสถานะของ donors ที่ต้อง query ค่อยเพิ่มส่วนนี้
    if (!empty($donor_statuses_to_query)) {
        // สร้าง placeholders (?) สำหรับ prepared statement
        $donor_placeholders = implode(',', array_fill(0, count($donor_statuses_to_query), '?'));
        
        $sql_parts[] = "
        (
            SELECT
                'เครื่อง' AS part_type, serial_no AS part_code, device_models AS part_name,
                category, 1 AS quantity, location_index AS location,
                status AS status_label
            FROM parts_donors
            WHERE status IN ($donor_placeholders)
        )
        ";
        // เพิ่มค่าสถานะลงใน $params
        foreach ($donor_statuses_to_query as $status) {
            $params[] = $status;
        }
    }

    // --- 4. รวม Query และ Execute ---
    if (empty($sql_parts)) {
        // ถ้าไม่มี query อะไรเลย (เช่น เลือก filter ที่ไม่มีจริง)
        $rows = [];
    } else {
        // รวมทุกส่วนด้วย UNION ALL
        $sql = implode(" UNION ALL ", $sql_parts);
        $sql .= " ORDER BY part_type, category, part_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params); // ส่ง $params ที่มีค่าสถานะของ donor
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("ฉิบหาย! Query พัง: " . $e->getMessage());
}

// --- 5. อัปเดตหัวข้อตามตัวกรอง ---
$status_map_for_title = [
    'all' => 'ทั้งหมด',
    'in_stock' => 'In Stock',
    'reserved' => 'จอง',
    'for_sale' => 'กำลังขาย'
];
$filter_text = $status_map_for_title[$filter_status] ?? 'ทั้งหมด';
$pageTitle = "รายงานสต็อกอะไหล่ (สถานะ: $filter_text) (" . count($rows) . " รายการ)";

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap');
        
        body {
            font-family: 'Sarabun', sans-serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 0;
            font-size: 10pt;
        }
        .container {
            width: 95%;
            margin: 20px auto;
        }
        .report-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #000;
            margin-bottom: 10px;
        }
        .logo-container img {
            max-height: 70px;
            width: auto;
        }
        .company-info {
            font-size: 10pt;
            line-height: 1.5;
        }
        h1 {
            text-align: center;
            font-size: 16pt;
            margin: 0 0 5px 0;
            padding: 0;
            border-bottom: none;
        }
        .print-info {
            text-align: center;
            font-size: 10pt;
            color: #555;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #999;
            padding: 0;
            text-align: left;
            vertical-align: top;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        th {
            padding: 5px 8px;
            background-color: #f0f0f0;
            font-weight: 700;
        }
        td > div {
            padding: 5px 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 1.5em;
            line-height: 1.5;
        }
        
        th:nth-child(6), td:nth-child(6) > div { text-align: right; } /* Qty */
        
        /* 8 คอลัมน์ */
        th:nth-child(1) { width: 4%; }  /* # */
        th:nth-child(2) { width: 8%; }  /* ประเภท */
        th:nth-child(3) { width: 12%; } /* หมวด */
        th:nth-child(4) { width: 18%; } /* รหัส / ซีเรียล */
        th:nth-child(5) { width: 25%; } /* ชื่อ / รุ่น (เครื่อง) */
        th:nth-child(6) { width: 6%; }  /* คงเหลือ */
        th:nth-child(7) { width: 18%; } /* ที่เก็บ */
        th:nth-child(8) { width: 9%; }  /* สถานะ */

        @page {
            size: A4 landscape;
            margin: 0.75in;
        }
        @media print {
            body { margin: 0; font-size: 9pt; }
            .container { width: 100%; margin: 0; padding: 0; }
            h1 { font-size: 14pt; }
            .print-info, .no-print { display: none; }
            .report-header { border-bottom: none; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="no-print" style="position: fixed; top: 10px; right: 10px; z-index: 100; display: flex; gap: 10px; align-items: center;">
            <form method="GET" action="" id="filterForm" style="margin: 0;">
                <select name="status" onchange="document.getElementById('filterForm').submit()" style="padding: 10px; font-size: 16px; height: 44px; border-radius: 5px; border: 1px solid #ccc;">
                    <option value="all" <?= ($filter_status === 'all') ? 'selected' : '' ?>>
                        -- ดูทั้งหมด --
                    </option>
                    <option value="in_stock" <?= ($filter_status === 'in_stock') ? 'selected' : '' ?>>
                        In Stock (มือ 1, มือ 2, เครื่อง)
                    </option>
                    <option value="for_sale" <?= ($filter_status === 'for_sale') ? 'selected' : '' ?>>
                        กำลังขาย (เครื่อง)
                    </option>
                    <option value="reserved" <?= ($filter_status === 'reserved') ? 'selected' : '' ?>>
                        จอง (เครื่อง)
                    </option>
                </select>
            </form>
            <button onclick="window.print();" style="padding: 10px 15px; background: #007aff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; height: 44px;">
                พิมพ์เอกสาร
            </button>
        </div>


        <header class="report-header">
            <div class="logo-container">
                <img src="<?= h(BASE_URL) ?>/assets/img/Logo1.png" alt="CMNS FixMac Logo">
            </div>
            <div class="company-info">
                <strong>CHIANGMAI NOTEBOOK SERVICE</strong><br>
                ร้านเชียงใหม่ โน๊ตบุ๊ค เซอร์วิส<br>
                482 หมู่ 8 วรุณนิเวศน์ ต.แม่เหียะ อ.เมือง จ.เชียงใหม่<br>
                โทร: 084-151-1684,086-428-6515
            </div>
        </header>

        <h1><?= h($pageTitle) ?></h1>
        <p class="print-info">วันที่พิมพ์: <?= date('d/m/Y H:i') ?></p>

        <div class="content">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ประเภท</th>
                        <th>หมวด</th>
                        <th>รหัส / ซีเรียล</th>
                        <th>ชื่อ / รุ่น (เครื่อง)</th>
                        <th>คงเหลือ (ชิ้น)</th>
                        <th>ที่เก็บ</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8">
                                <div style="white-space: normal; text-align: center; padding: 20px;">
                                    ไม่พบข้อมูลอะไหล่ตามสถานะ "<?= h($filter_text) ?>" ที่เลือก
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $r): ?>
                            <?php
                            $status_text = $r['status_label'];
                            if ($r['part_type'] === 'เครื่อง') {
                                $status_text = $DONOR_STATUS[$r['status_label']] ?? $r['status_label'];
                            }
                            $location_val = $r['location'] ?? '';
                            $location_display = '';
                            if ($r['part_type'] === 'มือ 1' || $r['part_type'] === 'มือ 2') {
                                $location_display = str_replace(',', ', ', $location_val);
                            } else {
                                $location_display = $location_val;
                            }
                            ?>
                            <tr>
                                <td><div><?= $i + 1 ?></div></td>
                                <td><div title="<?= h($r['part_type']) ?>"><strong><?= h($r['part_type']) ?></strong></div></td>
                                <td><div title="<?= h($r['category']) ?>"><?= h($r['category']) ?></div></td>
                                <td><div title="<?= h($r['part_code']) ?>"><code><?= h($r['part_code']) ?></code></div></td>
                                <td><div title="<?= h($r['part_name']) ?>"><?= h($r['part_name']) ?></div></td>
                                <td><div style="text-align: right; font-weight: 700;"><?= h(number_format((float)$r['quantity'])) ?></div></td>
                                <td><div title="<?= h($location_display) ?>"><?= h($location_display) ?></div></td> 
                                <td><div title="<?= h($status_text) ?>"><?= h($status_text) ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html> 