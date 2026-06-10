<?php
$page_title = 'Microphone Tester Online Free | CMNS FixMac';
$page_css   = ['assets/css/style.css'];

ob_start(); ?>
<meta name="description" content="Test the microphone of your Mac / iPhone / iPad. Real-time input level meter and recording playback to verify the mic works. Free online.">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://cmnsfixmac.com/en/tester/microphone-tester/">
<link rel="shortcut icon" href="/assets/img/favicon1.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
<script src="https://unpkg.com/wavesurfer.js"></script>
<script src="https://unpkg.com/@ffmpeg/ffmpeg@0.12.10/dist/umd/ffmpeg.js"></script>
<script type="module" src="assets/js/script.js" defer></script>
<?php $page_head_extra = ob_get_clean();

include_once '../../../includes/header_en.php';
?>

  <main class="main-container">

    <button id="lang-toggle" class="lang-btn">
      <span class="material-symbols-outlined">translate</span> เปลี่ยนภาษา</button>


    <label for="mic-select"></label>
    <select id="mic-select">
      <option>กำลังโหลด...</option>
    </select>

    <button id="start-btn"><span class="material-symbols-outlined">play_arrow</span></button>
    <button id="stop-btn" style="display:none;"><span class="material-symbols-outlined">stop</span></button>

    <!-- แถบระดับเสียง -->
    <div class="mic-level">
      <div class="mic-bar" id="mic-bar"></div>
    </div>
    <p id="mic-percent">0%</p>

    <!-- waveform -->
    <div id="waveform"></div>

    <!-- สถานะอัดเสียง -->
    <div id="recording-status" style="display:none;"></div>

    <!-- ปุ่มควบคุมการอัดเสียง -->
    <div id="record-controls">
      <button id="record-btn"><span class="material-symbols-outlined">mic</span></button>
      <button id="stop-record-btn" style="display:none;"><span class="material-symbols-outlined">stop_circle</span></button>
      <button id="play-btn" style="display:none;"><span class="material-symbols-outlined">play_circle</span></button>
      <button id="download-btn" style="display:none;"><span class="material-symbols-outlined">download</span></button>
      <audio id="audio-playback" controls style="display:none; width:100%; margin-top: 1rem;"></audio>
    </div>
  </main>
