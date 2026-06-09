<?php
require_once '../includes/db.php';

$page_title       = 'เครื่องมือทดสอบอุปกรณ์ Apple ออนไลน์ ฟรี | CMNS FixMac';
$page_description = 'ทดสอบหน้าจอ คีย์บอร์ด ไมโครโฟน กล้อง ลำโพง และทัชสกรีนของ Mac / iPhone / iPad ออนไลน์ฟรี ไม่ต้องติดตั้งโปรแกรม เช็คก่อนซื้อ-ขายมือสอง';
$page_keywords    = 'ทดสอบหน้าจอ, ทดสอบคีย์บอร์ด, ทดสอบกล้อง, ทดสอบไมโครโฟน, เช็คเครื่อง Mac มือสอง, dead pixel test';
$page_css         = ['/assets/css/tester-style.css?v=1'];

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$tools = [
    ['monitor-tester',     'monitor',     'ทดสอบหน้าจอ',   'หา Dead Pixel จุดสว่าง/ดับ ทดสอบสีและความสม่ำเสมอแบบเต็มจอ'],
    ['keyboard-tester',    'keyboard',    'ทดสอบคีย์บอร์ด', 'เช็คทุกปุ่มว่ากดติดครบ ตรวจปุ่มค้าง ปุ่มตาย ก่อนซื้อ-ขาย'],
    ['microphone-tester',  'mic',         'ทดสอบไมโครโฟน',  'วัดระดับเสียงเข้าแบบเรียลไทม์ เช็คว่าไมค์รับเสียงปกติ'],
    ['camera-tester',      'photo_camera','ทดสอบกล้อง',     'เปิดกล้องหน้า/หลัง ดูภาพสด เช็คโฟกัสและจุดเสียบนเซนเซอร์'],
    ['sounds-tester',      'volume_up',   'ทดสอบลำโพง',     'เล่นเสียงทดสอบซ้าย-ขวา เช็คลำโพงแตก เสียงหาย หรือเบาผิดปกติ'],
    ['touchscreen-tester', 'touch_app',   'ทดสอบทัชสกรีน',  'ลากนิ้วทั่วจอเช็คจุดสัมผัสที่ตอบสนองไม่ครบหรือกระตุก'],
];

ob_start(); ?>
<meta name="description" content="<?= e($page_description) ?>">
<meta name="keywords"    content="<?= e($page_keywords) ?>">
<meta name="robots"      content="index, follow">
<link rel="canonical"    href="https://cmnsfixmac.com/tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<meta property="og:title"       content="<?= e($page_title) ?>">
<meta property="og:description" content="<?= e($page_description) ?>">
<meta property="og:type"        content="website">
<meta property="og:url"         content="https://cmnsfixmac.com/tester/">
<meta property="og:locale"      content="th_TH">
<?php $page_head_extra = ob_get_clean();

include_once '../includes/header.php';
?>

<main class="ts-main">

  <!-- ── Hero ── -->
  <section class="ts-hero">
    <div class="ts-hero-bg" aria-hidden="true"></div>
    <div class="ts-hero-inner">
      <span class="ts-eyebrow">
        <span class="material-symbols-rounded">smart_toy</span>
        ทดสอบออนไลน์ ฟรี · ไม่ต้องติดตั้ง
      </span>
      <h1 class="ts-h1">เครื่องมือ<span class="ts-h1-accent">ทดสอบอุปกรณ์</span></h1>
      <p class="ts-sub">เช็คฮาร์ดแวร์ Mac / iPhone / iPad ด้วยตัวเองก่อนซื้อ-ขายมือสอง หรือก่อนส่งซ่อม รู้ผลทันทีในเบราว์เซอร์</p>
    </div>
  </section>

  <!-- ── Tool grid ── -->
  <div class="ts-grid-wrap">
    <div class="ts-grid">
      <?php foreach ($tools as [$slug, $icon, $title, $desc]): ?>
      <a class="ts-card" href="/tester/<?= e($slug) ?>/">
        <span class="ts-card-icon"><span class="material-symbols-rounded"><?= e($icon) ?></span></span>
        <h2 class="ts-card-title"><?= e($title) ?></h2>
        <p class="ts-card-desc"><?= e($desc) ?></p>
        <span class="ts-card-cta">เริ่มทดสอบ <span class="material-symbols-rounded">arrow_forward</span></span>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="ts-tip">
      <span class="material-symbols-rounded">lightbulb</span>
      <p>เครื่องมือบางตัว (กล้อง · ไมโครโฟน) เบราว์เซอร์จะขออนุญาตเข้าถึงก่อน — กด "อนุญาต" เพื่อเริ่มทดสอบ ระบบไม่บันทึกหรือส่งข้อมูลใด ๆ ทั้งสิ้น ทุกอย่างรันในเครื่องของคุณ</p>
    </div>
  </div>

</main>

<?php include_once '../includes/footer.php'; ?>
