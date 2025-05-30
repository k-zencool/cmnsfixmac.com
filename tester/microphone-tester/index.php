<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ทดสอบไมโครโฟน</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />

  <script src="https://unpkg.com/wavesurfer.js"></script>
  <script src="https://unpkg.com/@ffmpeg/ffmpeg@0.12.1/dist/ffmpeg.min.js"></script>
  <script type="module" src="assets/js/script.js" defer></script>

</head>
<body>
  <div class="header-toggle">
    <?php include_once '../../includes/header.php'; ?>
  </div>
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
</body>
</html>
