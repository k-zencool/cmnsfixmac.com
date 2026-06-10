<?php
$page_title = 'Display / Monitor Tester (Dead Pixel & Color) Online Free | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=1', 'assets/css/style.css'];

ob_start(); ?>
<meta name="description" content="Test your Mac / iPhone / iPad display for dead pixels, bright/dark spots and color uniformity in fullscreen. Free online, no install.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/en/tester/monitor-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
<?php $page_head_extra = ob_get_clean();

include_once '../../../includes/header_en.php';
?>

  <div class="monitor-tester">
    <div id="modeLabel">โหมด</div>
    <a id="backButton" href="/en/tester/" data-i18n="back">← กลับหน้าแรก</a>

    <main class="main-container">
      <button id="lang-toggle" class="lang-btn">
        <span class="material-symbols-outlined">translate</span> เปลี่ยนภาษา
      </button>

      <div id="welcome">
        <h1 data-i18n="title">ยินดีต้อนรับสู่เว็บไซต์ทดสอบหน้าจอ</h1>
        <p data-i18n="desc1">เว็บไซต์นี้ใช้สำหรับทดสอบสี พื้นหลัง เส้น และการแสดงผลของหน้าจอ</p>
        <p data-i18n="desc2">กดปุ่ม "เริ่มทดสอบ" เพื่อเข้าสู่โหมดเต็มจอและเริ่มใช้งาน</p>
        <button onclick="startTest()" data-i18n="start">เริ่มทดสอบ</button>
      </div>

      <div id="tester" style="display: none;"></div>
      <script src="assets/js/script.js"></script>
    </main>
  </div>
