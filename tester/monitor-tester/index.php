<?php
$page_title = 'ทดสอบหน้าจอ (Dead Pixel / สี) ออนไลน์ ฟรี | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=5', 'assets/css/style.css?v=4'];

ob_start(); ?>
<meta name="description" content="ทดสอบหน้าจอ Mac / iPhone / iPad หา Dead Pixel จุดสว่าง-ดับ แสงรั่ว สีเพี้ยน และอาการค้าง ด้วยลายทดสอบเต็มจอ ออนไลน์ฟรี">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/tester/monitor-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<?php $page_head_extra = ob_get_clean();

include_once '../../includes/header.php';
?>

<main class="mt-intro">

  <section class="mt-hero">
    <span class="mt-eyebrow">
      <span class="material-symbols-rounded">monitor</span>
      ทดสอบหน้าจอ · Dead Pixel
    </span>
    <h1>ทดสอบหน้าจอ<span class="mt-accent"> แบบเต็มจอ</span></h1>
    <p class="mt-lead">
      ไล่สีพื้น ลายตาราง ไล่เฉด และจุดตาย เพื่อหา Dead / Stuck Pixel,
      แสงรั่ว (backlight bleed), สีเพี้ยน และอาการค้างของหน้าจอ
      Mac / iPhone / iPad — รันในเบราว์เซอร์ ไม่ต้องติดตั้ง
    </p>
    <div class="mt-hero-actions">
      <button class="mt-start" id="mtStart">
        <span class="material-symbols-rounded">play_arrow</span> เริ่มทดสอบเต็มจอ
      </button>
      <a class="mt-back-link" href="/tester/">
        <span class="material-symbols-rounded">arrow_back</span> หน้ารวมเครื่องมือ
      </a>
    </div>
  </section>

  <section class="mt-guide">
    <h2 class="mt-guide-title">หรือเลือกเริ่มจากลายที่ต้องการ</h2>
    <div class="mt-chips" id="mtChips"><!-- filled by JS --></div>
  </section>

  <section class="mt-tips">
    <div class="mt-tip">
      <span class="material-symbols-rounded">ads_click</span>
      <div><strong>คลิก / แตะ</strong><span>ไปลายถัดไป</span></div>
    </div>
    <div class="mt-tip">
      <span class="material-symbols-rounded">swipe</span>
      <div><strong>ปัดซ้าย–ขวา</strong><span>เปลี่ยนลาย (จอสัมผัส)</span></div>
    </div>
    <div class="mt-tip">
      <span class="material-symbols-rounded">keyboard</span>
      <div><strong>ปุ่ม ← →</strong><span>เลื่อนลาย · Esc ออก</span></div>
    </div>
  </section>

</main>

<!-- ── Fullscreen test stage (covers everything) ── -->
<div class="mt-stage" id="mtStage" hidden>
  <canvas class="mt-canvas" id="mtCanvas"></canvas>

  <!-- pattern picker -->
  <div class="mt-picker" id="mtPicker" hidden>
    <div class="mt-picker-head">
      <span>เลือกลายทดสอบ</span>
      <button class="mt-icon-btn" id="mtPickerClose" aria-label="ปิด">
        <span class="material-symbols-rounded">close</span>
      </button>
    </div>
    <div class="mt-picker-grid" id="mtPickerGrid"><!-- filled by JS --></div>
  </div>

  <!-- control bar -->
  <div class="mt-hud" id="mtHud">
    <button class="mt-icon-btn" id="mtPrev" aria-label="ลายก่อนหน้า">
      <span class="material-symbols-rounded">chevron_left</span>
    </button>
    <div class="mt-hud-info">
      <span class="mt-hud-name" id="mtName">—</span>
      <span class="mt-hud-count" id="mtCount">1 / 1</span>
    </div>
    <button class="mt-icon-btn" id="mtNext" aria-label="ลายถัดไป">
      <span class="material-symbols-rounded">chevron_right</span>
    </button>
    <span class="mt-hud-sep"></span>
    <button class="mt-icon-btn" id="mtGrid" aria-label="เลือกลาย">
      <span class="material-symbols-rounded">grid_view</span>
    </button>
    <button class="mt-icon-btn" id="mtFs" aria-label="สลับเต็มจอ">
      <span class="material-symbols-rounded">fullscreen</span>
    </button>
    <button class="mt-icon-btn mt-exit" id="mtExit" aria-label="ออกจากการทดสอบ">
      <span class="material-symbols-rounded">close</span>
    </button>
  </div>

  <!-- first-run hint -->
  <div class="mt-hint" id="mtHint" hidden>
    <div class="mt-hint-card">
      <span class="material-symbols-rounded">touch_app</span>
      <p>คลิก/แตะ = ลายถัดไป · ปัดซ้าย-ขวา = เปลี่ยนลาย · เลื่อนเมาส์เพื่อเรียกแถบควบคุม · กด <kbd>Esc</kbd> เพื่อออก</p>
      <button id="mtHintOk">เริ่มเลย</button>
    </div>
  </div>
</div>

<script src="assets/js/script.js?v=2" defer></script>

<?php include_once '../../includes/footer.php'; ?>
