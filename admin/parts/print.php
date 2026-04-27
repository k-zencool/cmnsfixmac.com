<?php
// admin/parts/print_labels.php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

try {
    $sql = "SELECT internal_id FROM parts_donors 
            WHERE internal_id IS NOT NULL AND internal_id != '' 
            ORDER BY internal_id ASC";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { die("DB Error: " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Print Micro Asset Tags</title>
    <style>
        @page { size: A4; margin: 0.5cm; }
        body { font-family: 'Courier New', monospace; margin: 0; background: #eee; }
        .no-print { padding: 15px; text-align: center; background: #333; color: #fff; margin-bottom: 20px; }
        button { padding: 8px 15px; cursor: pointer; font-size: 1rem; }

        /* Grid A4 แบบโคตรประหยัด */
        .page-container {
            width: 210mm; min-height: 297mm; margin: 0 auto; background: white;
            padding: 0.5cm; 
            box-sizing: border-box;
            
            display: grid;
            /* 5 คอลัมน์เหมือนเดิม */
            grid-template-columns: repeat(5, 1fr); 
            
            /* [จุดสำคัญ] ลดความสูงเหลือ 1.2cm (เดิม 1.8cm) */
            grid-auto-rows: 1.2cm; 
            
            gap: 2px; /* ช่องไฟระหว่างป้าย */
        }

        .label-card {
            border: 1px dashed #ccc;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center; /* จัดกึ่งกลางแนวตั้ง */
            background: white;
            text-align: center;
            line-height: 1; /* บีบบรรทัดให้ชิด */
        }

        .tag-title {
            font-size: 0.5rem; /* ตัวหนังสือเล็กจิ๋ว */
            color: #888;
            margin-bottom: 2px; /* ระยะห่างจากบรรทัดล่างนิดเดียวพอ */
            text-transform: uppercase;
        }

        .tag-id {
            font-size: 0.8rem; /* ตัวเลขขนาดกำลังดี */
            font-weight: 900;
            letter-spacing: -0.5px;
            white-space: nowrap;
        }

        @media print {
            body { background: white; } .no-print { display: none; }
            .page-container { width: 100%; margin: 0; padding: 0; box-shadow: none; }
            .label-card { border-color: #ddd; break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <h1>Asset Tag แบบจิ๋ว (<?= count($rows) ?> ชิ้น)</h1>
        <button onclick="window.print()">🖨️ สั่งปริ้นท์</button>
        <a href="index.php?tab=donor" style="color:#fff; margin-left:15px;">กลับ</a>
    </div>

    <div class="page-container">
        <?php if (count($rows) > 0): ?>
            <?php foreach ($rows as $r): ?>
                <div class="label-card">
                    <div class="tag-title">CMNS PROPERTY</div>
                    <div class="tag-id"><?= htmlspecialchars($r['internal_id']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column:1/-1; text-align:center; padding:50px; color:red;">
                <h3>ไม่พบข้อมูล</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>