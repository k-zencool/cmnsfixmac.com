<?php
$page_title = 'ทดสอบทัชสกรีน (Touchscreen) ออนไลน์ ฟรี | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=1', 'assets/css/style.css'];

ob_start(); ?>
<meta name="description" content="ทดสอบหน้าจอสัมผัส iPad / iPhone / Mac ลากนิ้วเช็คจุดสัมผัสที่ตอบสนองไม่ครบหรือกระตุก ออนไลน์ฟรี">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/tester/touchscreen-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<?php $page_head_extra = ob_get_clean();

include_once '../../includes/header.php';
?>

  <h1>ทดสอบหน้าจอสัมผัส</h1>
  <div id="touchCountDisplay">สัมผัส: 0 จุด</div>

  <div class="ui">
    <button onclick="clearCanvas()">ล้างหน้าจอ</button>
    <button onclick="downloadImage()">บันทึกภาพ</button>
    <button onclick="toggleGrid()">เปิด/ปิดตาราง</button>
  </div>

  <canvas id="touchCanvas"></canvas>
  <div class="footer">แตะหน้าจอเพื่อตรวจสอบการสัมผัสทุกจุด</div>

  <script src="assets/js/script.js"></script>
