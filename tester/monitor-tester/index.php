<?php
$page_title = 'ทดสอบหน้าจอ (Dead Pixel / สี) ออนไลน์ ฟรี | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=1', 'assets/css/style.css'];

ob_start(); ?>
<meta name="description" content="ทดสอบหน้าจอ Mac / iPhone / iPad หา Dead Pixel จุดสว่าง-ดับ และความสม่ำเสมอของสีแบบเต็มจอ ออนไลน์ฟรี">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/tester/monitor-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
<?php $page_head_extra = ob_get_clean();

include_once '../../includes/header.php';
?>

  <div class="monitor-tester">
    <div id="modeLabel">โหมด</div>
    <a id="backButton" href="/tester/" data-i18n="back">← กลับหน้าทดสอบ</a>

    <main class="main-container">
      <button id="lang-toggle" class="lang-btn">
        <span class="material-symbols-outlined">translate</span> เปลี่ยนภาษา
      </button>

      <div id="welcome">
        <h1 data-i18n="title">ทดสอบการแสดงผลหน้าจอ</h1>
        <p data-i18n="desc1">ใช้ตรวจสอบสี พื้นหลัง เส้น และจุดเสีย (Dead Pixel) ของหน้าจอ</p>
        <p data-i18n="desc2">กด "เริ่มทดสอบ" เพื่อเข้าสู่โหมดเต็มจอ แล้วแตะ/คลิกเพื่อสลับสี</p>
        <button class="ts-btn" onclick="startTest()" data-i18n="start">เริ่มทดสอบ</button>
      </div>

      <div id="tester" style="display: none;"></div>
      <script src="assets/js/script.js"></script>
    </main>
  </div>

<?php include_once '../../includes/footer.php'; ?>
