<?php
$page_title = 'ทดสอบกล้อง (Camera) ออนไลน์ ฟรี | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=1', 'assets/css/style.css'];

ob_start(); ?>
<meta name="description" content="ทดสอบกล้องหน้า/หลังของ Mac / iPhone / iPad ดูภาพสดในเบราว์เซอร์ เช็คโฟกัสและจุดเสียบนเซนเซอร์ ออนไลน์ฟรี">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/tester/camera-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
<?php $page_head_extra = ob_get_clean();

include_once '../../includes/header.php';
?>

  <div class="container">
    <button id="lang-toggle" class="lang-btn">
        <span class="material-symbols-outlined">translate</span> เปลี่ยนภาษา
    </button>

    <h1 id="titleText">ทดสอบกล้อง</h1>
    <select id="cameraSelect"></select>
    <h2 id="cameraInfo">ยังไม่ได้เปิดกล้อง</h2>

    <div class="buttons">
      <button id="startBtn"><span class="material-icons">play_arrow</span> <span data-i18n="start">เริ่ม</span></button>
      <button id="flipBtn"><span class="material-icons">flip_camera_android</span> <span data-i18n="flip">สลับกล้อง</span></button>
      <button id="stopBtn"><span class="material-icons">stop</span> <span data-i18n="stop">หยุด</span></button>
      <button id="snapshotBtn"><span class="material-icons">photo_camera</span> <span data-i18n="snapshot">บันทึกภาพ</span></button>
          <video id="video" autoplay playsinline></video>
    <img id="snapshotPreview" alt="ภาพที่ถ่าย" />
    <a id="downloadLink" target="_blank">📥 ดาวน์โหลดภาพ</a>
    </div>

  </div>
  <div id="flashEffect"></div>
  <canvas id="snapshotCanvas" style="display: none;"></canvas>
  <script src="assets/js/script.js"></script>
