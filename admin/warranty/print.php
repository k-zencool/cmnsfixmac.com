<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/warranty_lib.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$w = $pdo->prepare("SELECT * FROM warranties WHERE id = ?");
$w->execute([$id]);
$war = $w->fetch(PDO::FETCH_ASSOC);
if (!$war) { header('Location: index.php'); exit; }

$public_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
              . '/warranty/?q=' . urlencode($war['warranty_no']);
$days_left  = w_days_left($war['end_date']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ใบรับประกัน <?= htmlspecialchars($war['warranty_no']) ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Sarabun', 'Helvetica Neue', Arial, sans-serif;
    font-size: 14px;
    color: #1a1a2e;
    background: #f0f0f0;
}
.page {
    width: 210mm;
    min-height: 148mm;
    background: #fff;
    margin: 20px auto;
    padding: 24px 28px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
    border-radius: 4px;
    position: relative;
}
/* Header */
.doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px solid #1e3a5f;
    padding-bottom: 14px;
    margin-bottom: 16px;
}
.brand-name { font-size: 22px; font-weight: 900; color: #1e3a5f; letter-spacing: 1px; }
.brand-sub  { font-size: 11px; color: #6b7280; margin-top: 2px; }
.doc-title-area { text-align: right; }
.doc-title { font-size: 18px; font-weight: 800; color: #1e3a5f; }
.warranty-no { font-size: 13px; font-weight: 700; color: #2563eb; font-family: monospace; margin-top: 4px; }

/* Status badge */
.status-pill {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    margin-top: 6px;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.status-active  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.status-expired { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }
.status-voided  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

/* Grid layout */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 160px;
    gap: 20px;
    align-items: start;
}
.info-section { margin-bottom: 14px; }
.section-label {
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: #9ca3af;
    margin-bottom: 6px;
    border-bottom: 1px solid #f3f4f6;
    padding-bottom: 4px;
}
.info-table { width: 100%; border-collapse: collapse; }
.info-table td { padding: 5px 0; font-size: 13px; vertical-align: top; }
.info-table .lbl { color: #6b7280; width: 110px; flex-shrink: 0; }
.info-table .val { font-weight: 600; }
.val-mono { font-family: monospace; font-size: 12px; }

/* Period bar */
.period-box {
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 14px;
}
.period-box .days-num  { font-size: 28px; font-weight: 900; color: #1e3a5f; line-height: 1; }
.period-box .days-unit { font-size: 12px; color: #6b7280; margin-left: 4px; }
.period-bar-wrap { background: #e5e7eb; border-radius: 10px; height: 7px; margin: 8px 0 4px; overflow: hidden; }
.period-bar { height: 100%; border-radius: 10px; }
.period-dates { font-size: 10px; color: #6b7280; display: flex; justify-content: space-between; }

/* Terms */
.terms-box {
    background: #fafafa;
    border: 1px dashed #d1d5db;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 10px;
    color: #6b7280;
    line-height: 1.6;
}
.terms-box strong { color: #374151; }

/* QR area */
.qr-area { text-align: center; }
.qr-area canvas, .qr-area img { width: 140px; height: 140px; }
.qr-label { font-size: 9px; color: #6b7280; margin-top: 6px; line-height: 1.4; word-break: break-all; }
.qr-hint  { font-size: 10px; font-weight: 600; color: #374151; margin-bottom: 4px; }

/* Footer */
.doc-footer {
    border-top: 1px solid #e5e7eb;
    margin-top: 16px;
    padding-top: 10px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    font-size: 10px;
    color: #9ca3af;
}
.sig-line { border-top: 1px solid #374151; width: 130px; text-align: center; padding-top: 4px; font-size: 10px; color: #374151; margin-top: 28px; }

/* Print button (screen only) */
.print-btn-bar {
    width: 210mm;
    margin: 0 auto 12px;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}
.print-btn {
    padding: 8px 18px;
    background: #1e3a5f;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ── Print ── */
@media print {
    body { background: #fff; }
    .print-btn-bar { display: none; }
    .page { box-shadow: none; margin: 0; border-radius: 0; width: 100%; }
}
@page { size: A5 landscape; margin: 10mm; }
</style>
</head>
<body>

<div class="print-btn-bar">
    <a href="view.php?id=<?= $id ?>" style="padding:8px 14px; background:#f3f4f6; border-radius:8px; text-decoration:none; font-size:13px; color:#374151; display:flex; align-items:center; gap:4px;">
        ← กลับ
    </a>
    <button class="print-btn" onclick="window.print()">
        🖨️ พิมพ์ / บันทึก PDF
    </button>
</div>

<div class="page">
    <!-- Header -->
    <div class="doc-header">
        <div>
            <div class="brand-name">CMNS FIX MAC</div>
            <div class="brand-sub">ศูนย์ซ่อม Mac & iPhone ครบวงจร</div>
        </div>
        <div class="doc-title-area">
            <div class="doc-title">ใบรับประกัน</div>
            <div class="warranty-no"><?= htmlspecialchars($war['warranty_no']) ?></div>
            <?php
            $sc = $war['status'] === 'active' ? 'status-active' : ($war['status'] === 'expired' ? 'status-expired' : 'status-voided');
            $sl = $war['status'] === 'active' ? 'Active' : ($war['status'] === 'expired' ? 'Expired' : 'Voided');
            ?>
            <span class="status-pill <?= $sc ?>"><?= $sl ?></span>
        </div>
    </div>

    <!-- Content -->
    <div class="content-grid">
    <div>
        <!-- Customer info -->
        <div class="info-section">
            <div class="section-label">ข้อมูลลูกค้าและเครื่อง</div>
            <table class="info-table">
                <tr><td class="lbl">ชื่อลูกค้า</td><td class="val"><?= htmlspecialchars($war['customer_name']) ?></td></tr>
                <?php if ($war['customer_phone']): ?>
                <tr><td class="lbl">เบอร์โทร</td><td class="val"><?= htmlspecialchars($war['customer_phone']) ?></td></tr>
                <?php endif; ?>
                <tr><td class="lbl">รุ่นเครื่อง</td><td class="val"><?= htmlspecialchars($war['device_model']) ?></td></tr>
                <?php if ($war['serial_no']): ?>
                <tr><td class="lbl">Serial No.</td><td class="val val-mono"><?= htmlspecialchars($war['serial_no']) ?></td></tr>
                <?php endif; ?>
                <?php if ($war['repair_summary']): ?>
                <tr><td class="lbl" style="padding-top:8px;">งานซ่อม</td>
                    <td class="val" style="padding-top:8px; font-size:12px; white-space:pre-line;"><?= htmlspecialchars($war['repair_summary']) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Warranty period -->
        <?php
        $total_d  = $war['warranty_days'];
        $used_d   = max(0, min((int)ceil((time() - strtotime($war['start_date'])) / 86400), $total_d));
        $pct      = $total_d > 0 ? round(($used_d / $total_d) * 100) : 100;
        $bar_col  = $war['status'] === 'active' ? ($pct < 70 ? '#10b981' : '#f59e0b') : '#d1d5db';
        ?>
        <div class="period-box">
            <div>
                <span class="days-num"><?= $total_d ?></span>
                <span class="days-unit">วัน</span>
                <?php if ($war['status'] === 'active' && $days_left > 0): ?>
                    <span style="font-size:11px; font-weight:700; color:<?= $days_left>30?'#059669':'#d97706' ?>; margin-left:10px;">เหลือ <?= $days_left ?> วัน</span>
                <?php endif; ?>
            </div>
            <div class="period-bar-wrap">
                <div class="period-bar" style="width:<?= $pct ?>%; background:<?= $bar_col ?>;"></div>
            </div>
            <div class="period-dates">
                <span>เริ่ม <?= date('d/m/Y', strtotime($war['start_date'])) ?></span>
                <span>หมด <?= date('d/m/Y', strtotime($war['end_date'])) ?></span>
            </div>
        </div>

        <!-- Terms -->
        <div class="terms-box">
            <strong>เงื่อนไขการรับประกัน:</strong> ครอบคลุมเฉพาะอาการเดิมจากงานซ่อมที่ระบุในเอกสาร ไม่รวมความเสียหายจากการตก กระแทก น้ำ ความชื้น การแกะซ่อมจากภายนอก หรือการใช้งานผิดวิธี • หากพบอาการผิดปกติกรุณานำเครื่องมาให้ช่างตรวจภายในระยะประกัน • สแกน QR หรือเยี่ยมชม cmnsfixmac.com เพื่อตรวจสอบสถานะประกัน
        </div>
    </div>

    <!-- QR Code -->
    <div class="qr-area">
        <div class="qr-hint">สแกนเช็คประกัน</div>
        <canvas id="qr-print"></canvas>
        <div class="qr-label"><?= htmlspecialchars($war['warranty_no']) ?></div>
        <div style="margin-top:20px;">
            <div class="sig-line">ลายเซ็นผู้ออกเอกสาร</div>
        </div>
        <div style="font-size:9px; color:#9ca3af; margin-top:8px;">
            ออกวันที่ <?= date('d/m/Y', strtotime($war['created_at'])) ?>
        </div>
    </div>
    </div><!-- .content-grid -->

    <!-- Footer -->
    <div class="doc-footer">
        <div>cmnsfixmac.com &nbsp;|&nbsp; เอกสารนี้ออกโดยระบบอัตโนมัติ</div>
        <div>ออกวันที่ <?= date('d M Y', strtotime($war['created_at'])) ?></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
QRCode.toCanvas(document.getElementById('qr-print'), <?= json_encode($public_url) ?>, {
    width: 140, margin: 1, color: { dark: '#1e3a5f', light: '#fff' }
}, function(err){ if(err) console.error(err); });
</script>
</body>
</html>
