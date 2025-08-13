<?php
    // Include the ENGLISH header
    include_once __DIR__ . '/../../../includes/header_en.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Online Speaker Test (Left-Right) | Speaker Tester</title>
  <meta name="description" content="An easy-to-use online left-right speaker test tool. Get instant results with a real-time waveform and frequency visualizer." />

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/tester/sounds-tester/" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/tester/sounds-tester/" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/tester/sounds-tester/" />

  <link rel="canonical" href="https://cmnsfixmac.com/en/tester/sounds-tester/" />
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
      <h1>Test Your Speakers</h1>
      <p>Press the buttons to test the left, right, or both channels.</p>
    </section>
    <section class="waveform-section">
      <h2>Real-time Waveform</h2>
      <canvas id="waveform" width="400" height="100"></canvas>
    </section>
    <section class="volume-control">
      <label for="volume">
        <span class="material-symbols-outlined">volume_up</span> Adjust Volume
      </label><br />
      <input type="range" id="volume" min="0" max="1" step="0.01" value="1" oninput="setVolume(this.value)">
      <span id="volume-value">100%</span>
    </section>
    <section class="controls">
      <button onclick="playChannel('left')"><span class="material-symbols-outlined">volume_down</span> Left Speaker</button>
      <button onclick="playChannel('right')"><span class="material-symbols-outlined">volume_up</span> Right Speaker</button>
      <button onclick="playChannel('both')"><span class="material-symbols-outlined">spatial_audio</span> Both Sides</button>
      <button onclick="autoTest()"><span class="material-symbols-outlined">autorenew</span> Auto Test</button>
      <button onclick="stopSound()"><span class="material-symbols-outlined">stop</span> Stop Sound</button>
    </section>
    <section class="circle-visualizer">
      <h2>Frequency Circle Visualizer</h2>
      <canvas id="circleVisualizer" width="300" height="300"></canvas>
    </section>
    <section id="status">Status: Not playing yet</section>
  </main>
  <?php include_once __DIR__ . '/../../../includes/footer_en.php'; // Include English footer ?>
  <script src="/tester/sounds-tester/assets/js/script.js"></script>
</body>
</html>