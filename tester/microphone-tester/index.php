<?php
$page_title = 'ทดสอบไมโครโฟน (Microphone) ออนไลน์ ฟรี | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=4', 'assets/css/style.css?v=3'];

ob_start(); ?>
<meta name="description" content="ทดสอบไมโครโฟน Mac / iPhone / iPad วัดระดับเสียงเข้าแบบเรียลไทม์ พร้อมอัด-เล่นกลับเพื่อเช็คว่าไมค์รับเสียงปกติ ออนไลน์ฟรี">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/tester/microphone-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<?php $page_head_extra = ob_get_clean();

include_once '../../includes/header.php';
?>

<main class="mic-main">

  <section class="mic-hero">
    <span class="mic-eyebrow">
      <span class="material-symbols-rounded">mic</span> ทดสอบไมโครโฟน
    </span>
    <h1>ทดสอบ<span class="mic-accent">ไมโครโฟน</span></h1>
    <p class="mic-lead">
      วัดระดับเสียงเข้าแบบเรียลไทม์ พร้อมอัด–เล่นกลับ เพื่อเช็คว่าไมค์รับเสียงปกติ
      ไม่ขาด ๆ หาย ๆ หรือเบาผิดปกติ — เบราว์เซอร์จะขออนุญาตใช้ไมค์เมื่อกด "เริ่มทดสอบ"
    </p>
  </section>

  <div class="mic-card">
    <label class="mic-field-label" for="mic-select">เลือกไมโครโฟน</label>
    <select id="mic-select" class="mic-select">
      <option value="">ไมโครโฟนเริ่มต้น (ระบบเลือกให้)</option>
    </select>

    <button id="start-btn" class="mic-btn mic-btn-primary">
      <span class="material-symbols-rounded">play_arrow</span> เริ่มทดสอบ
    </button>
    <button id="stop-btn" class="mic-btn mic-btn-danger" style="display:none">
      <span class="material-symbols-rounded">stop</span> หยุด
    </button>

    <div class="mic-meter">
      <span class="mic-meter-label">ระดับเสียง</span>
      <div class="mic-level"><div class="mic-bar" id="mic-bar"></div></div>
      <span id="mic-percent">0%</span>
    </div>

    <div id="waveform" class="mic-wave"></div>

    <div id="recording-status" class="mic-rec-status" style="display:none">
      <span class="dot"></span> กำลังอัดเสียง...
    </div>

    <div id="record-controls" class="mic-rec">
      <button id="record-btn" class="mic-btn mic-btn-ghost">
        <span class="material-symbols-rounded">mic</span> เริ่มอัดเสียง
      </button>
      <button id="stop-record-btn" class="mic-btn mic-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">stop_circle</span> หยุดอัด
      </button>
      <button id="play-btn" class="mic-btn mic-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">play_circle</span> ฟังเสียง
      </button>
      <button id="download-btn" class="mic-btn mic-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">download</span> ดาวน์โหลด
      </button>
      <audio id="audio-playback" controls style="display:none"></audio>
    </div>
  </div>

  <a class="mic-back" href="/tester/">
    <span class="material-symbols-rounded">arrow_back</span> หน้ารวมเครื่องมือ
  </a>

</main>

<script src="assets/js/script.js?v=3" defer></script>

<?php include_once '../../includes/footer.php'; ?>
