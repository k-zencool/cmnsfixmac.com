<?php
$page_title = 'Display / Monitor Tester (Dead Pixel & Color) Online Free | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=5', 'assets/css/style.css?v=4'];

ob_start(); ?>
<meta name="description" content="Test your Mac / iPhone / iPad display for dead pixels, bright/dark spots, backlight bleed and colour uniformity with fullscreen patterns. Free online, no install.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/en/tester/monitor-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<?php $page_head_extra = ob_get_clean();

include_once '../../../includes/header_en.php';
?>

<main class="mt-intro">

  <section class="mt-hero">
    <span class="mt-eyebrow">
      <span class="material-symbols-rounded">monitor</span>
      Display Test · Dead Pixel
    </span>
    <h1>Test your display<span class="mt-accent"> fullscreen</span></h1>
    <p class="mt-lead">
      Cycle through solid colours, grids, gradients and dead-pixel patterns
      to spot dead / stuck pixels, backlight bleed, colour shift and ghosting
      on your Mac / iPhone / iPad — runs in the browser, no install.
    </p>
    <div class="mt-hero-actions">
      <button class="mt-start" id="mtStart">
        <span class="material-symbols-rounded">play_arrow</span> Start fullscreen test
      </button>
      <a class="mt-back-link" href="/en/tester/">
        <span class="material-symbols-rounded">arrow_back</span> All testing tools
      </a>
    </div>
  </section>

  <section class="mt-guide">
    <h2 class="mt-guide-title">Or jump straight to a pattern</h2>
    <div class="mt-chips" id="mtChips"><!-- filled by JS --></div>
  </section>

  <section class="mt-tips">
    <div class="mt-tip">
      <span class="material-symbols-rounded">ads_click</span>
      <div><strong>Click / tap</strong><span>Next pattern</span></div>
    </div>
    <div class="mt-tip">
      <span class="material-symbols-rounded">swipe</span>
      <div><strong>Swipe left–right</strong><span>Change pattern (touch)</span></div>
    </div>
    <div class="mt-tip">
      <span class="material-symbols-rounded">keyboard</span>
      <div><strong>← → keys</strong><span>Move · Esc to exit</span></div>
    </div>
  </section>

</main>

<!-- ── Fullscreen test stage (covers everything) ── -->
<div class="mt-stage" id="mtStage" hidden>
  <canvas class="mt-canvas" id="mtCanvas"></canvas>

  <!-- pattern picker -->
  <div class="mt-picker" id="mtPicker" hidden>
    <div class="mt-picker-head">
      <span>Choose a pattern</span>
      <button class="mt-icon-btn" id="mtPickerClose" aria-label="Close">
        <span class="material-symbols-rounded">close</span>
      </button>
    </div>
    <div class="mt-picker-grid" id="mtPickerGrid"><!-- filled by JS --></div>
  </div>

  <!-- control bar -->
  <div class="mt-hud" id="mtHud">
    <button class="mt-icon-btn" id="mtPrev" aria-label="Previous pattern">
      <span class="material-symbols-rounded">chevron_left</span>
    </button>
    <div class="mt-hud-info">
      <span class="mt-hud-name" id="mtName">—</span>
      <span class="mt-hud-count" id="mtCount">1 / 1</span>
    </div>
    <button class="mt-icon-btn" id="mtNext" aria-label="Next pattern">
      <span class="material-symbols-rounded">chevron_right</span>
    </button>
    <span class="mt-hud-sep"></span>
    <button class="mt-icon-btn" id="mtGrid" aria-label="Choose pattern">
      <span class="material-symbols-rounded">grid_view</span>
    </button>
    <button class="mt-icon-btn" id="mtFs" aria-label="Toggle fullscreen">
      <span class="material-symbols-rounded">fullscreen</span>
    </button>
    <button class="mt-icon-btn mt-exit" id="mtExit" aria-label="Exit test">
      <span class="material-symbols-rounded">close</span>
    </button>
  </div>

  <!-- first-run hint -->
  <div class="mt-hint" id="mtHint" hidden>
    <div class="mt-hint-card">
      <span class="material-symbols-rounded">touch_app</span>
      <p>Click/tap = next pattern · swipe to change · move the mouse for controls · press <kbd>Esc</kbd> to exit</p>
      <button id="mtHintOk">Got it</button>
    </div>
  </div>
</div>

<script src="assets/js/script.js?v=2" defer></script>

<?php include_once '../../../includes/footer_en.php'; ?>
