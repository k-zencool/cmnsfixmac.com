<?php
$page_title = 'Camera Tester Online Free | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=5', '/tester/camera-tester/assets/css/style.css?v=2'];

ob_start(); ?>
<meta name="description" content="Test the front/rear camera of your Mac / iPhone / iPad live in the browser. Check focus, color and sensor spots, and snap a photo to compare. Free online, no install.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/en/tester/camera-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<?php $page_head_extra = ob_get_clean();

include_once '../../../includes/header_en.php';
?>

<main class="cam-main">

  <section class="cam-hero">
    <span class="cam-eyebrow">
      <span class="material-symbols-rounded">photo_camera</span> Camera Test
    </span>
    <h1>Camera <span class="cam-accent">Tester</span></h1>
    <p class="cam-lead">
      See a live feed from the front/rear camera in your browser. Check focus, color
      and sensor spots, then snap a photo to compare — the browser will ask for
      camera permission when you press "Start Test".
    </p>
  </section>

  <div class="cam-card">
    <label class="cam-field-label" for="cam-select">Camera</label>
    <select id="cam-select" class="cam-select">
      <option value="">Default camera (system choice)</option>
    </select>

    <div class="cam-stage">
      <video id="cam-video" class="cam-video" autoplay playsinline muted></video>
      <div class="cam-placeholder" id="cam-placeholder">
        <span class="material-symbols-rounded">videocam_off</span>
        <span>Camera not started</span>
      </div>
      <span class="cam-resolution" id="cam-resolution" style="display:none"></span>
    </div>

    <div class="cam-controls">
      <button id="cam-start" class="cam-btn cam-btn-primary">
        <span class="material-symbols-rounded">play_arrow</span> Start Test
      </button>
      <button id="cam-stop" class="cam-btn cam-btn-danger" style="display:none">
        <span class="material-symbols-rounded">stop</span> Stop
      </button>
      <button id="cam-flip" class="cam-btn cam-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">flip_camera_android</span> Flip Camera
      </button>
      <button id="cam-snap" class="cam-btn cam-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">photo_camera</span> Snapshot
      </button>
    </div>

    <div id="cam-shot" class="cam-shot" style="display:none">
      <img id="cam-preview" class="cam-preview" alt="Captured photo" />
      <a id="cam-download" class="cam-btn cam-btn-ghost" download="camera-test.png">
        <span class="material-symbols-rounded">download</span> Download Image
      </a>
    </div>
  </div>

  <a class="cam-back" href="/en/tester/">
    <span class="material-symbols-rounded">arrow_back</span> All testing tools
  </a>

</main>

<div id="cam-flash" class="cam-flash"></div>
<canvas id="cam-canvas" style="display:none"></canvas>

<script src="assets/js/script.js?v=2" defer></script>

<?php include_once '../../../includes/footer_en.php'; ?>
