<?php
require_once '../../includes/db.php';

$page_title       = 'Free Online Apple Device Testers | CMNS FixMac';
$page_description = 'Test your Mac / iPhone / iPad screen, keyboard, microphone, camera, speakers and touchscreen online for free — no install. Check before buying or selling used.';
$page_keywords    = 'screen test, keyboard test, camera test, microphone test, used Mac check, dead pixel test';
$page_css         = ['/assets/css/tester-style.css?v=4'];

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// [slug, icon, title, desc, is_feature]
$tools = [
    ['monitor-tester',     'monitor',     'Screen Test',      'Find dead/stuck pixels and check colour uniformity in fullscreen.',          true],
    ['keyboard-tester',    'keyboard',    'Keyboard Test',    'Verify every key registers — spot stuck or dead keys before a deal.',        false],
    ['microphone-tester',  'mic',         'Microphone Test',  'See your input level in real time and confirm the mic picks up sound.',      false],
    ['camera-tester',      'photo_camera','Camera Test',      'Open front/rear camera, view the live feed, check focus and sensor spots.',  false],
    ['sounds-tester',      'volume_up',   'Speaker Test',     'Play left/right test tones to catch blown, silent or weak speakers.',        false],
    ['touchscreen-tester', 'touch_app',   'Touchscreen Test', 'Drag across the screen to find unresponsive or jittery touch zones.',        true],
];

ob_start(); ?>
<meta name="description" content="<?= e($page_description) ?>">
<meta name="keywords"    content="<?= e($page_keywords) ?>">
<meta name="robots"      content="index, follow">
<link rel="canonical"    href="https://cmnsfixmac.com/en/tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<meta property="og:title"       content="<?= e($page_title) ?>">
<meta property="og:description" content="<?= e($page_description) ?>">
<meta property="og:type"        content="website">
<meta property="og:url"         content="https://cmnsfixmac.com/en/tester/">
<meta property="og:locale"      content="en_US">
<?php $page_head_extra = ob_get_clean();

include_once '../../includes/header_en.php';
?>

<main class="ts-main">

  <!-- ── Floating orbs ── -->
  <div class="ts-orbs" aria-hidden="true">
    <span class="ts-orb ts-orb-1"></span>
    <span class="ts-orb ts-orb-2"></span>
    <span class="ts-orb ts-orb-3"></span>
    <span class="ts-orb ts-orb-4"></span>
  </div>

  <!-- ── Hero ── -->
  <section class="ts-hero">
    <div class="ts-hero-bg" aria-hidden="true"></div>
    <div class="ts-hero-inner">
      <span class="ts-eyebrow">
        <span class="material-symbols-rounded">smart_toy</span>
        Free online tests · No install
      </span>
      <h1 class="ts-h1">Device <span class="ts-h1-accent">Testers</span></h1>
      <p class="ts-sub">Check your Mac / iPhone / iPad hardware yourself before buying, selling or sending in for repair — instant results, right in your browser.</p>
    </div>
  </section>

  <!-- ── Tool grid ── -->
  <div class="ts-grid-wrap">
    <div class="ts-bento">
      <?php foreach ($tools as [$slug, $icon, $title, $desc, $feature]): ?>
      <a class="ts-tile<?= $feature ? ' is-feature' : '' ?>" href="/en/tester/<?= e($slug) ?>/">
        <span class="ts-tile-icon"><span class="material-symbols-rounded"><?= e($icon) ?></span></span>
        <h2 class="ts-tile-title"><?= e($title) ?></h2>
        <p class="ts-tile-desc"><?= e($desc) ?></p>
        <span class="ts-tile-cta">Start test <span class="material-symbols-rounded">arrow_forward</span></span>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="ts-tip">
      <span class="material-symbols-rounded">lightbulb</span>
      <p>Some tools (camera · microphone) will ask for browser permission first — tap "Allow" to begin. Nothing is recorded or uploaded; everything runs locally on your device.</p>
    </div>
  </div>

</main>

<?php include_once '../../includes/footer_en.php'; ?>
