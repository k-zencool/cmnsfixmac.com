<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Monitor Tester</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-3WXK9GWN7C');
</script>
</head>
<body>
  <div class="header-toggle">
<?php include_once '../../../includes/header_en.php'; ?>
  </div>

  <div class="monitor-tester">
    <div id="modeLabel">โหมด</div>
    <button id="backButton" onclick="goBack()" data-i18n="back">← กลับหน้าแรก</button>

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
</body>

</html>
