<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ทดสอบเสียงลำโพง</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

</head>
<body>

<?php include_once '../../includes/header.php'; ?>


  <main class="main-container">


    <section class="intro">
      <h1>ทดสอบเสียงลำโพงของคุณ</h1>
      <p>กดปุ่มเพื่อทดสอบเสียงฝั่งซ้าย ขวา หรือทั้งสอง</p>
    </section>

    <section class="waveform-section">
      <h2>คลื่นเสียงแบบเรียลไทม์</h2>
      <canvas id="waveform" width="400" height="100"></canvas>
    </section>

<section class="volume-control">
  <label for="volume">
    <span class="material-symbols-outlined">volume_up</span> ปรับระดับเสียง
  </label><br />
  <input type="range" id="volume" min="0" max="1" step="0.01" value="1" oninput="setVolume(this.value)">
  <span id="volume-value">100%</span>
</section>

<section class="controls">
  <button onclick="playChannel('left')">
    <span class="material-symbols-outlined">volume_down</span> ลำโพงซ้าย
  </button>
  <button onclick="playChannel('right')">
    <span class="material-symbols-outlined">volume_up</span> ลำโพงขวา
  </button>
  <button onclick="playChannel('both')">
    <span class="material-symbols-outlined">spatial_audio</span> ทั้งสองฝั่ง
  </button>

  <button onclick="autoTest()">
    <span class="material-symbols-outlined">autorenew</span> ทดสอบอัตโนมัติ
  </button>

  <button onclick="stopSound()">
    <span class="material-symbols-outlined">stop</span> หยุดเสียง
  </button>
</section>


    <section class="circle-visualizer">
      <h2>วงกลมแสดงความถี่เสียง</h2>
      <canvas id="circleVisualizer" width="300" height="300"></canvas>
    </section>



    <section id="status">ยังไม่ได้เล่นเสียง</section>
  </main>

  <footer class="footer">
    © 2025 Website Sounds Tester
  </footer>

  <script src="assets/js/script.js"></script>
</body>
</html>
