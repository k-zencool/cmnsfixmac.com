<?php
$page_title = 'Microphone Tester Online Free | CMNS FixMac';
$page_css   = ['/assets/css/tester-style.css?v=4', '/tester/microphone-tester/assets/css/style.css?v=3'];

ob_start(); ?>
<meta name="description" content="Test the microphone of your Mac / iPhone / iPad. Real-time input level meter and recording playback to verify the mic picks up sound. Free online.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/en/tester/microphone-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<?php $page_head_extra = ob_get_clean();

include_once '../../../includes/header_en.php';
?>

<main class="mic-main">

  <section class="mic-hero">
    <span class="mic-eyebrow">
      <span class="material-symbols-rounded">mic</span> Microphone Test
    </span>
    <h1>Microphone <span class="mic-accent">Tester</span></h1>
    <p class="mic-lead">
      Watch your input level in real time, then record and play it back to confirm
      the mic picks up sound clearly — no dropouts or low volume.
      The browser will ask for mic permission when you press "Start Test".
    </p>
  </section>

  <div class="mic-card">
    <label class="mic-field-label" for="mic-select">Microphone</label>
    <select id="mic-select" class="mic-select">
      <option value="">Default microphone (system choice)</option>
    </select>

    <button id="start-btn" class="mic-btn mic-btn-primary">
      <span class="material-symbols-rounded">play_arrow</span> Start Test
    </button>
    <button id="stop-btn" class="mic-btn mic-btn-danger" style="display:none">
      <span class="material-symbols-rounded">stop</span> Stop
    </button>

    <div class="mic-meter">
      <span class="mic-meter-label">Input level</span>
      <div class="mic-level"><div class="mic-bar" id="mic-bar"></div></div>
      <span id="mic-percent">0%</span>
    </div>

    <div id="waveform" class="mic-wave"></div>

    <div id="recording-status" class="mic-rec-status" style="display:none">
      <span class="dot"></span> Recording...
    </div>

    <div id="record-controls" class="mic-rec">
      <button id="record-btn" class="mic-btn mic-btn-ghost">
        <span class="material-symbols-rounded">mic</span> Start Recording
      </button>
      <button id="stop-record-btn" class="mic-btn mic-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">stop_circle</span> Stop Recording
      </button>
      <button id="play-btn" class="mic-btn mic-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">play_circle</span> Play
      </button>
      <button id="download-btn" class="mic-btn mic-btn-ghost" style="display:none">
        <span class="material-symbols-rounded">download</span> Download
      </button>
      <audio id="audio-playback" controls style="display:none"></audio>
    </div>
  </div>

  <a class="mic-back" href="/en/tester/">
    <span class="material-symbols-rounded">arrow_back</span> All testing tools
  </a>

</main>

<script src="assets/js/script.js?v=3" defer></script>

<?php include_once '../../../includes/footer_en.php'; ?>
