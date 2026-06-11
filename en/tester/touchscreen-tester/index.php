<?php
$page_title = 'Touchscreen Tester Online Free | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=5', '/tester/touchscreen-tester/assets/css/style.css?v=3'];

ob_start(); ?>
<meta name="description" content="Test the touchscreen of your iPad / iPhone / Mac. Drag across the screen to find unresponsive or stuttering touch points. Works with touch, mouse and pen. Free online.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/en/tester/touchscreen-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<?php $page_head_extra = ob_get_clean();

include_once '../../../includes/header_en.php';
?>

<main class="ts-main">
  <div id="ts-grid" class="ts-grid" aria-hidden="true"></div>
  <canvas id="ts-canvas" class="ts-canvas"></canvas>

  <div class="ts-hud">
    <span class="ts-title">
      <span class="material-symbols-rounded">touch_app</span> Touchscreen Test
    </span>
    <span class="ts-count" id="ts-count">0 points</span>
  </div>

  <div class="ts-toolbar">
    <button class="ts-btn" data-action="clear">
      <span class="material-symbols-rounded">ink_eraser</span> Clear
    </button>
    <button class="ts-btn" data-action="grid">
      <span class="material-symbols-rounded">grid_on</span> Grid
    </button>
    <button class="ts-btn" data-action="save">
      <span class="material-symbols-rounded">download</span> Save Image
    </button>
    <button class="ts-btn" data-action="fullscreen">
      <span class="material-symbols-rounded">fullscreen</span> <span class="ts-fs-label">Fullscreen</span>
    </button>
    <a class="ts-btn" href="/en/tester/">
      <span class="material-symbols-rounded">arrow_back</span> Back
    </a>
  </div>

  <p class="ts-hint">Drag across the whole screen to find dead or stuttering touch points — works with touch, mouse and pen.</p>
</main>

<script src="assets/js/script.js?v=4" defer></script>
<?php include_once '../../../includes/footer_en.php'; ?>
