<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Camera Tester</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons" />

    <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-3WXK9GWN7C');
</script>
  <link rel="stylesheet" href="/tester/camera-tester/assets/css/style.css" />
</head>
<body>

<?php $page_has_own_head = true; include_once '../../../includes/header_en.php'; ?>


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
</body>
</html>
