<?php
$page_title = 'ทดสอบกล้อง (Camera) ออนไลน์ ฟรี | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=5', 'assets/css/style.css?v=2'];

ob_start(); ?>
<meta name="description" content="ทดสอบกล้องหน้า/หลังของ Mac / iPhone / iPad ดูภาพสดในเบราว์เซอร์ เช็คโฟกัสและจุดเสียบนเซนเซอร์ พร้อมถ่ายภาพเก็บไว้ ออนไลน์ฟรี">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/tester/camera-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<?php $page_head_extra = ob_get_clean();

include_once '../../includes/header.php';
?>

<main class="cam-main">

  <section class="cam-hero">
    <span class="cam-eyebrow">
      <span class="material-symbols-rounded">photo_camera</span> ทดสอบกล้อง
    </span>
    <h1>ทดสอบ<span class="cam-accent">กล้อง</span></h1>
    <p class="cam-lead">
      ดูภาพสดจากกล้องหน้า/หลังในเบราว์เซอร์ เช็คโฟกัส สี และจุดเสียบนเซนเซอร์
      พร้อมถ่ายภาพเก็บไว้เทียบ — เบราว์เซอร์จะขออนุญาตใช้กล้องเมื่อกด "เริ่มทดสอบ"
    </p>
  </section>

  <div class="cam-card">
    <label class="cam-field-label" for="cam-select">เลือกกล้อง</label>
    <select id="cam-select" class="cam-select">
      <option value="">กล้องเริ่มต้น (ระบบเลือกให้)</option>
    </select>

    <div class="cam-stage">
      <video id="cam-video" class="cam-video" autoplay playsinline muted></video>
      <div class="cam-placeholder" id="cam-placeholder">
        <span class="material-symbols-rounded">videocam_off</span>
        <span>ยังไม่ได้เปิดกล้อง</span>
      </div>
      <span class="cam-resolution" id="cam-resolution" style="display:none"></span>
    </div>

    <div class="cam-controls">
      <button id="cam-start" class="cam-btn cam-btn-primary">
        <span class="material-symbols-rounded">play_arrow</span> เริ่มทดสอบ
      </button>
      <button id="cam-stop" class="cam-btn cam-btn-danger" style="display:none">
        <span class="material-symbols-rounded">stop</span> หยุด
      </button>
      <button id="cam-flip" class="cam-btn cam-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">flip_camera_android</span> สลับกล้อง
      </button>
      <button id="cam-snap" class="cam-btn cam-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">photo_camera</span> ถ่ายภาพ
      </button>
    </div>

    <div id="cam-shot" class="cam-shot" style="display:none">
      <img id="cam-preview" class="cam-preview" alt="ภาพที่ถ่าย" />
      <a id="cam-download" class="cam-btn cam-btn-ghost" download="camera-test.png">
        <span class="material-symbols-rounded">download</span> ดาวน์โหลดภาพ
      </a>
    </div>
  </div>

  <a class="cam-back" href="/tester/">
    <span class="material-symbols-rounded">arrow_back</span> หน้ารวมเครื่องมือ
  </a>

</main>

<div id="cam-flash" class="cam-flash"></div>
<canvas id="cam-canvas" style="display:none"></canvas>

<script src="assets/js/script.js?v=2" defer></script>

<?php include_once '../../includes/footer.php'; ?>
