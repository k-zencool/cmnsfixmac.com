<?php
$page_title = 'ทดสอบทัชสกรีน (Touchscreen) ออนไลน์ ฟรี | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=5', 'assets/css/style.css?v=3'];

ob_start(); ?>
<meta name="description" content="ทดสอบหน้าจอสัมผัส iPad / iPhone / Mac ลากนิ้วเช็คจุดสัมผัสที่ตอบสนองไม่ครบหรือกระตุก รองรับทั้งนิ้ว เมาส์ และปากกา ออนไลน์ฟรี">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/tester/touchscreen-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<?php $page_head_extra = ob_get_clean();

include_once '../../includes/header.php';
?>

<main class="ts-main">
  <div id="ts-grid" class="ts-grid" aria-hidden="true"></div>
  <canvas id="ts-canvas" class="ts-canvas"></canvas>

  <div class="ts-hud">
    <span class="ts-title">
      <span class="material-symbols-rounded">touch_app</span> ทดสอบทัชสกรีน
    </span>
    <span class="ts-count" id="ts-count">0 จุด</span>
  </div>

  <div class="ts-toolbar">
    <button class="ts-btn" data-action="clear">
      <span class="material-symbols-rounded">ink_eraser</span> ล้างหน้าจอ
    </button>
    <button class="ts-btn" data-action="grid">
      <span class="material-symbols-rounded">grid_on</span> ตาราง
    </button>
    <button class="ts-btn" data-action="save">
      <span class="material-symbols-rounded">download</span> บันทึกภาพ
    </button>
    <button class="ts-btn" data-action="fullscreen">
      <span class="material-symbols-rounded">fullscreen</span> <span class="ts-fs-label">เต็มจอ</span>
    </button>
    <a class="ts-btn" href="/tester/">
      <span class="material-symbols-rounded">arrow_back</span> กลับ
    </a>
  </div>

  <p class="ts-hint">ลากนิ้วให้ทั่วหน้าจอเพื่อหาจุดที่สัมผัสไม่ติดหรือกระตุก — รองรับนิ้ว เมาส์ และปากกา</p>
</main>

<script src="assets/js/script.js?v=4" defer></script>
<?php include_once '../../includes/footer.php'; ?>
