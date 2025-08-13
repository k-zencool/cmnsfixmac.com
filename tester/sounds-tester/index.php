<?php
    // Include the standard THAI header
    include_once __DIR__ . '/../../includes/header.php'; 
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ทดสอบเสียงลำโพงออนไลน์ (ซ้าย-ขวา) | Speaker Tester</title>
  <meta name="description" content="เครื่องมือทดสอบเสียงลำโพงซ้าย-ขวาออนไลน์ ใช้งานง่าย รู้ผลทันที พร้อม Visualizer แสดงคลื่นเสียงและความถี่แบบเรียลไทม์" />

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/tester/sounds-tester/" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/tester/sounds-tester/" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/tester/sounds-tester/" />

  <link rel="canonical" href="https://cmnsfixmac.com/tester/sounds-tester/" />
  <link rel="shortcut icon" href="/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/assets/css/navbar-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="stylesheet" href="/tester/sounds-tester/assets/css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>
</head>
<body>
<?php // The header is already included at the top ?>
  <main class="main-container">
    <section class="intro">
      <h1>ทดสอบเสียงลำโพงของคุณ</h1>
      <p>กดปุ่มเพื่อทดสอบเสียงฝั่งซ้าย, ขวา, หรือทั้งสองฝั่ง</p>
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
      <button onclick="playChannel('left')"><span class="material-symbols-outlined">volume_down</span> ลำโพงซ้าย</button>
      <button onclick="playChannel('right')"><span class="material-symbols-outlined">volume_up</span> ลำโพงขวา</button>
      <button onclick="playChannel('both')"><span class="material-symbols-outlined">spatial_audio</span> ทั้งสองฝั่ง</button>
      <button onclick="autoTest()"><span class="material-symbols-outlined">autorenew</span> ทดสอบอัตโนมัติ</button>
      <button onclick="stopSound()"><span class="material-symbols-outlined">stop</span> หยุดเสียง</button>
    </section>
    <section class="circle-visualizer">
      <h2>วงกลมแสดงความถี่เสียง</h2>
      <canvas id="circleVisualizer" width="300" height="300"></canvas>
    </section>
    <section id="status">สถานะ: ยังไม่ได้เล่นเสียง</section>
  </main>
  <?php include_once __DIR__ . '/../../includes/footer.php'; // Include Thai footer ?>
  <script src="/tester/sounds-tester/assets/js/script.js"></script>
</body>
</html>