<?php
// Include Thai header
include_once __DIR__ . '/../../includes/header.php';

// ---- สแกนโฟลเดอร์ /tester/sounds-tester/sounds ----
$soundWebPath  = '/tester/sounds-tester/sounds';               // path ที่ browser จะเข้าถึงได้
$soundDiskPath = __DIR__ . '/sounds';                          // path บนดิสก์จริง
$files = [];
if (is_dir($soundDiskPath)) {
  $allowed = ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'oga', 'flac'];
  foreach (scandir($soundDiskPath) as $f) {
    if ($f === '.' || $f === '..') continue;
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (in_array($ext, $allowed, true)) {
      $files[] = [
        'url'  => $soundWebPath . '/' . rawurlencode($f),
        'name' => preg_replace('/\.[^.]+$/', '', $f)
      ];
    }
  }
}

// sort ตามชื่อไฟล์
usort($files, function ($a, $b) {
  return strnatcasecmp($a['name'], $b['name']);
});

// ==== เลือกเพลงเริ่มต้นแบบสุ่ม ====
$defaultUrl = '';
if (!empty($files)) {
  $randKey    = array_rand($files);
  $defaultUrl = $files[$randKey]['url'];
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ทดสอบเสียงลำโพง (ซ้าย-ขวา) | Speaker Tester</title>
  <meta name="description" content="ทดสอบลำโพงซ้าย-ขวา เล่นสวีป 20–20kHz, white/pink noise และเปิดเพลงจากโฟลเดอร์ /sounds พร้อมสลับช่องเสียงได้ทันที มีคลื่นเสียง/สเปกตรัมแบบเรียลไทม์" />

  <!-- i18n / canonical -->
  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/tester/sounds-tester/" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/tester/sounds-tester/" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/tester/sounds-tester/" />
  <link rel="canonical" href="https://cmnsfixmac.com/tester/sounds-tester/" />

  <link rel="shortcut icon" href="/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/assets/css/navbar-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="stylesheet" href="/tester/sounds-tester/assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />

  <!-- GA -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
      dataLayer.push(arguments)
    };
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>
</head>

<body>
  <main class="main-container" id="app">
    <header class="page-head">
      <h1 class="headline">ทดสอบเสียงลำโพง</h1>
      <p class="subhead">สลับซ้าย-ขวา ตรวจความถี่ และลองเล่นเพลงจาก <code>/sounds</code> ได้ทันที</p>
    </header>

    <!-- Controls หลัก -->
    <section class="card" aria-labelledby="intro-h">
      <h2 id="intro-h">ควบคุมเสียงทดสอบ</h2>
      <p class="muted">
        คีย์ลัด: <span class="kbd">L</span> ซ้าย <span class="kbd">R</span> ขวา <span class="kbd">B</span> ทั้งคู่ <span class="kbd">S</span> หยุด
      </p>
      <div class="controls" role="group">
        <button class="btn" data-action="left"><span class="material-symbols-outlined">volume_down</span> ซ้าย</button>
        <button class="btn" data-action="right"><span class="material-symbols-outlined">volume_up</span> ขวา</button>
        <button class="btn primary" data-action="both"><span class="material-symbols-outlined">spatial_audio</span> ทั้งคู่</button>
        <button class="btn warn" data-action="sweep"><span class="material-symbols-outlined">timeline</span> สวีป 20–20kHz</button>
        <button class="btn" data-action="white"><span class="material-symbols-outlined">grain</span> White noise</button>
        <button class="btn" data-action="pink"><span class="material-symbols-outlined">texture</span> Pink noise</button>
        <button class="btn danger" data-action="stop"><span class="material-symbols-outlined">stop</span> หยุด</button>
      </div>

      <div class="kv" style="margin-top:12px">
        <label for="volume" class="muted">ระดับเสียงทั้งหมด</label>
        <div class="row">
          <input type="range" id="volume" min="0" max="1" step="0.01" value="0.9" />
          <output id="volumeValue">90%</output>
        </div>
      </div>
    </section>

    <!-- Player: เลือกเพลงจาก /sounds + channel switch ไม่รีสตาร์ท -->
    <section class="card" aria-labelledby="player-h">
      <h2 id="player-h">เลือกเพลง/ไฟล์เสียง</h2>
      <div class="kv">
        <label for="soundSelect" class="muted">โฟลเดอร์ /sounds</label>
        <select id="soundSelect" class="field">
          <?php if (empty($files)): ?>
            <option value="">— ไม่มีไฟล์ใน /sounds —</option>
          <?php else: ?>
            <?php foreach ($files as $f): ?>
              <option value="<?= htmlspecialchars($f['url'], ENT_QUOTES) ?>"
                <?= ($f['url'] === $defaultUrl ? 'selected' : '') ?>>
                <?= htmlspecialchars($f['name']) ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>

      </div>

      <div class="kv" style="margin-top:10px">
        <label for="filePick" class="muted">หรือลากไฟล์จากเครื่อง</label>
        <input id="filePick" type="file" accept="audio/*" />
      </div>


      <div class="controls" style="margin-top:12px">
        <button class="btn ok" id="playBtn"><span class="material-symbols-outlined">play_arrow</span> เล่น</button>
        <button class="btn" id="pauseBtn"><span class="material-symbols-outlined">pause</span> พัก</button>
        <button class="btn danger" id="stopBtn"><span class="material-symbols-outlined">stop</span> หยุด</button>
      </div>

      <div class="kv" style="margin-top:12px">
        <label class="muted">ส่งออกช่อง (ไม่รีสตาร์ทเพลง)</label>
        <div class="controls">
          <button class="btn" id="chLeft"><span class="material-symbols-outlined">volume_down</span> ซ้าย</button>
          <button class="btn" id="chRight"><span class="material-symbols-outlined">volume_up</span> ขวา</button>
          <button class="btn primary" id="chBoth"><span class="material-symbols-outlined">spatial_audio</span> ทั้งคู่</button>
        </div>
      </div>

      <div class="kv" style="margin-top:10px">
        <label for="seek" class="muted">ตำแหน่ง</label>
        <div class="row" style="flex:1">
          <input type="range" id="seek" min="0" max="1000" value="0" />
          <output id="timeLabel">00:00 / 00:00</output>
        </div>
      </div>

      <audio id="audioEl" preload="metadata" style="display:none"
        src="<?= htmlspecialchars($defaultUrl, ENT_QUOTES) ?>"></audio>
    </section>

    <!-- Visualizers -->
    <section class="grid" aria-label="Visualizers">
      <div class="card">
        <h2>คลื่นเสียง (Waveform)</h2>
        <canvas id="waveform" width="900" height="200"></canvas>
      </div>
      <div class="card">
        <h2>สเปกตรัมความถี่</h2>
        <canvas id="spectrum" width="900" height="200"></canvas>
      </div>
    </section>

    <section class="card">
      <h2>สถานะระบบ</h2>
      <ul class="list">
        <li>Sample rate: <strong id="sampleRate">-</strong></li>
        <li>Channel: <strong id="channelState">-</strong></li>
        <li>สถานะ: <strong id="status">ยังไม่ได้เล่นเสียง</strong></li>
      </ul>
    </section>

    <div class="status-bar" role="status" aria-live="polite">
      <span>คีย์ลัด <span class="kbd">L</span> <span class="kbd">R</span> <span class="kbd">B</span> <span class="kbd">S</span> • หมุนล้อเมาส์ปรับเสียง</span>
      <button class="btn ok" id="resumeBtn" style="display:none"><span class="material-symbols-outlined">play_arrow</span> Resume Audio</button>
    </div>

    <div class="toast" id="toast"></div>
  </main>

  <?php include_once __DIR__ . '/../../includes/footer.php'; ?>

  <script>
    ;
    (function() {
      'use strict';

      const d = document;
      const AudioCtx = window.AudioContext || window.webkitAudioContext;

      // ===== DOM =====
      const btns = d.querySelectorAll('.btn[data-action]');
      const volume = d.getElementById('volume');
      const volumeValue = d.getElementById('volumeValue');
      const statusEl = d.getElementById('status');
      const sampleRateEl = d.getElementById('sampleRate');
      const channelStateEl = d.getElementById('channelState');
      const resumeBtn = d.getElementById('resumeBtn');
      const toastEl = d.getElementById('toast');

      // player
      const soundSelect = d.getElementById('soundSelect');
      const filePick = d.getElementById('filePick');
      const playBtn = d.getElementById('playBtn');
      const pauseBtn = d.getElementById('pauseBtn');
      const stopBtn = d.getElementById('stopBtn');
      const chLeftBtn = d.getElementById('chLeft');
      const chRightBtn = d.getElementById('chRight');
      const chBothBtn = d.getElementById('chBoth');
      const seek = d.getElementById('seek');
      const timeLabel = d.getElementById('timeLabel');
      const mediaEl = d.getElementById('audioEl');

      // canvases
      const waveCanvas = d.getElementById('waveform');
      const specCanvas = d.getElementById('spectrum');
      const wctx = waveCanvas.getContext('2d');
      const sctx = specCanvas.getContext('2d');

      // ===== Audio Graph =====
      let ctx = null;
      let gainNode, merger, analyserTime, analyserFreq;
      let osc = null,
        noiseNode = null,
        sweepRAF = 0;

      // Media element + constant split gains (ไม่ต้อง reconnect เวลาเปลี่ยนช่อง)
      let mediaNode = null,
        mediaGainL = null,
        mediaGainR = null;

      const STATE = {
        channel: 'both',
        mode: 'idle'
      };

      function showToast(t) {
        toastEl.textContent = t;
        toastEl.classList.add('show');
        setTimeout(() => toastEl.classList.remove('show'), 1500);
      }

      function setStatus(t) {
        statusEl.textContent = t;
      }

      function setChannel(c) {
        STATE.channel = c;
        channelStateEl.textContent = c;
      }

      function ensureCtx() {
        if (ctx) return;
        ctx = new AudioCtx();
        gainNode = ctx.createGain();
        gainNode.gain.value = parseFloat(volume.value || 0.9);

        merger = ctx.createChannelMerger(2);

        analyserTime = ctx.createAnalyser();
        analyserTime.fftSize = 2048;
        analyserFreq = ctx.createAnalyser();
        analyserFreq.fftSize = 2048;
        analyserFreq.smoothingTimeConstant = 0.85;

        merger.connect(gainNode);
        gainNode.connect(analyserTime);
        analyserTime.connect(analyserFreq);
        analyserFreq.connect(ctx.destination);

        sampleRateEl.textContent = ctx.sampleRate + ' Hz';
      }

      // สำหรับ tone/noise/sweep
      function routeToChannel(node, ch = 'both') {
        const gl = ctx.createGain(),
          gr = ctx.createGain();
        gl.gain.value = (ch === 'right') ? 0 : 1;
        gr.gain.value = (ch === 'left') ? 0 : 1;
        node.connect(gl);
        node.connect(gr);
        gl.connect(merger, 0, 0);
        gr.connect(merger, 0, 1);
        return {
          gl,
          gr
        };
      }

      // Media graph ถาวร: media -> L/R gains -> merger
      function ensureMediaGraph() {
        ensureCtx();
        if (!mediaNode) {
          mediaNode = ctx.createMediaElementSource(mediaEl);
          mediaGainL = ctx.createGain();
          mediaGainR = ctx.createGain();
          mediaNode.connect(mediaGainL);
          mediaNode.connect(mediaGainR);
          mediaGainL.connect(merger, 0, 0);
          mediaGainR.connect(merger, 0, 1);
        }
        // อัปเดต gain ตามช่องปัจจุบัน
        applyMediaChannel(STATE.channel || 'both');
      }

      function applyMediaChannel(ch) {
        if (!mediaGainL || !mediaGainR) return;
        mediaGainL.gain.value = (ch === 'right') ? 0 : 1;
        mediaGainR.gain.value = (ch === 'left') ? 0 : 1;
        setChannel(ch);
      }

      // ===== Sources =====
      function stopAll() {
        if (osc) {
          try {
            osc.stop();
          } catch {}
          try {
            osc.disconnect();
          } catch {}
          osc = null;
        }
        if (noiseNode) {
          try {
            noiseNode.disconnect();
          } catch {}
          noiseNode = null;
        }
        if (sweepRAF) {
          cancelAnimationFrame(sweepRAF);
          sweepRAF = 0;
        }
        setStatus('หยุดเสียง');
        STATE.mode = 'idle';
      }

      function playTone(ch) {
        ensureCtx();
        stopAll();
        const o = ctx.createOscillator();
        o.type = 'sine';
        o.frequency.value = 440;
        routeToChannel(o, ch);
        o.start();
        osc = o;
        setChannel(ch);
        STATE.mode = 'tone';
        setStatus('เล่นโทน 440 Hz (' + ch + ')');
      }

      function playNoise(kind = 'white', ch = 'both') {
        ensureCtx();
        stopAll();
        const len = 2 * ctx.sampleRate;
        const buf = ctx.createBuffer(1, len, ctx.sampleRate);
        const out = buf.getChannelData(0);
        if (kind === 'white') {
          for (let i = 0; i < len; i++) out[i] = Math.random() * 2 - 1;
        } else {
          let b0 = 0,
            b1 = 0,
            b2 = 0,
            b3 = 0,
            b4 = 0,
            b5 = 0,
            b6 = 0;
          for (let i = 0; i < len; i++) {
            const w = Math.random() * 2 - 1;
            b0 = 0.99886 * b0 + w * 0.0555179;
            b1 = 0.99332 * b1 + w * 0.0750759;
            b2 = 0.969 * b2 + w * 0.153852;
            b3 = 0.8665 * b3 + w * 0.3104856;
            b4 = 0.55 * b4 + w * 0.5329522;
            b5 = -0.7616 * b5 - w * 0.016898;
            out[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + w * 0.5362) * 0.11;
            b6 = w * 0.115926;
          }
        }
        const src = ctx.createBufferSource();
        src.buffer = buf;
        src.loop = true;
        routeToChannel(src, ch);
        src.start(0);
        noiseNode = src;
        setChannel(ch);
        STATE.mode = kind + '-noise';
        setStatus((kind === 'white' ? 'White' : 'Pink') + ' noise (' + ch + ')');
      }

      function playSweep(ch = 'both') {
        ensureCtx();
        stopAll();
        const o = ctx.createOscillator();
        o.type = 'sine';
        const start = 20,
          end = 20000,
          dur = 6,
          t0 = ctx.currentTime;
        o.frequency.setValueAtTime(start, t0);
        o.frequency.exponentialRampToValueAtTime(end, t0 + dur);
        routeToChannel(o, ch);
        o.start();
        osc = o;
        setChannel(ch);
        STATE.mode = 'sweep';
        setStatus('สวีป 20–20kHz (' + ch + ')');
        sweepRAF = requestAnimationFrame(function tick() {
          if (!osc) return;
          if (ctx.currentTime - t0 >= dur) {
            stopAll();
            return;
          }
          sweepRAF = requestAnimationFrame(tick);
        });
      }

      // ===== Visualizers =====
      const waveData = new Uint8Array(2048);
      let freqData;

      function draw() {
        if (!ctx) {
          requestAnimationFrame(draw);
          return;
        }
        if (!freqData) freqData = new Uint8Array(analyserFreq.frequencyBinCount);
        analyserTime.getByteTimeDomainData(waveData);
        analyserFreq.getByteFrequencyData(freqData);

        // waveform
        wctx.clearRect(0, 0, waveCanvas.width, waveCanvas.height);
        wctx.lineWidth = 2;
        wctx.strokeStyle = '#007aff';
        wctx.beginPath();
        const slice = waveCanvas.width / waveData.length;
        for (let i = 0, x = 0; i < waveData.length; i++, x += slice) {
          const y = (waveData[i] / 128) * waveCanvas.height / 2;
          if (i === 0) wctx.moveTo(x, y);
          else wctx.lineTo(x, y);
        }
        wctx.stroke();

        // spectrum
        sctx.clearRect(0, 0, specCanvas.width, specCanvas.height);
        const barW = specCanvas.width / freqData.length * 2.4;
        for (let i = 0, x = 0; i < freqData.length; i++, x += barW + 1) {
          const v = freqData[i],
            y = (v / 255) * specCanvas.height;
          sctx.fillStyle = 'rgba(0,122,255,' + (0.25 + v / 255 * 0.75) + ')';
          sctx.fillRect(x, specCanvas.height - y, barW, y);
        }
        requestAnimationFrame(draw);
      }
      requestAnimationFrame(draw);

      // ===== Player helpers =====
      function fmt(t) {
        if (!isFinite(t)) return '00:00';
        const m = Math.floor(t / 60),
          s = Math.floor(t % 60);
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
      }

      function updateSeek() {
        if (!mediaEl || !isFinite(mediaEl.duration) || mediaEl.duration <= 0) {
          seek.value = 0;
          timeLabel.textContent = '00:00 / 00:00';
          return;
        }
        seek.value = Math.max(0, Math.min(1000, (mediaEl.currentTime / mediaEl.duration) * 1000 | 0));
        timeLabel.textContent = fmt(mediaEl.currentTime) + ' / ' + fmt(mediaEl.duration);
        requestAnimationFrame(updateSeek);
      }

      function loadFromFile(file) {
        if (!file) return;
        const url = URL.createObjectURL(file);
        mediaEl.src = url;
        mediaEl.load();
        showToast('โหลดไฟล์: ' + (file.name || 'audio'));
        soundSelect.value = ''; // เคลียร์ dropdown
      }


      // ===== UI bindings =====
      btns.forEach(b => {
        b.addEventListener('click', async () => {
          const act = b.dataset.action;
          try {
            ensureCtx();
            if (ctx.state === 'suspended') await ctx.resume();
            resumeBtn.style.display = 'none';
          } catch {}
          switch (act) {
            case 'left':
              playTone('left');
              break;
            case 'right':
              playTone('right');
              break;
            case 'both':
              playTone('both');
              break;
            case 'white':
              playNoise('white', STATE.channel || 'both');
              break;
            case 'pink':
              playNoise('pink', STATE.channel || 'both');
              break;
            case 'sweep':
              playSweep(STATE.channel || 'both');
              break;
            case 'stop':
              stopAll();
              break;
          }
        });
      });

      volume.addEventListener('input', () => {
        ensureCtx();
        gainNode.gain.value = parseFloat(volume.value);
        volumeValue.textContent = Math.round(volume.value * 100) + '%';
      });


      // Player events
      soundSelect?.addEventListener('change', () => {
        if (!soundSelect.value) return;
        mediaEl.src = soundSelect.value;
        mediaEl.load();
        showToast('โหลดเพลง: ' + soundSelect.options[soundSelect.selectedIndex].text);
      });
      filePick?.addEventListener('change', e => loadFromFile(e.target.files?.[0]));

      playBtn?.addEventListener('click', () => {
        ensureMediaGraph();
        mediaEl.play().then(() => {
            STATE.mode = 'media';
            setStatus('เล่นเพลง');
            requestAnimationFrame(updateSeek);
          })
          .catch(() => {
            resumeBtn.style.display = 'inline-flex';
            showToast('ต้องกดอนุญาตเสียงก่อน');
          });
      });
      pauseBtn?.addEventListener('click', () => {
        try {
          mediaEl.pause();
          setStatus('พัก');
        } catch {}
      });
      stopBtn?.addEventListener('click', () => {
        try {
          mediaEl.pause();
          mediaEl.currentTime = 0;
          setStatus('หยุด');
        } catch {}
      });

      chLeftBtn?.addEventListener('click', () => {
        ensureMediaGraph();
        applyMediaChannel('left');
        showToast('ส่งออก: ซ้าย');
      });
      chRightBtn?.addEventListener('click', () => {
        ensureMediaGraph();
        applyMediaChannel('right');
        showToast('ส่งออก: ขวา');
      });
      chBothBtn?.addEventListener('click', () => {
        ensureMediaGraph();
        applyMediaChannel('both');
        showToast('ส่งออก: ทั้งคู่');
      });

      seek?.addEventListener('input', () => {
        if (!isFinite(mediaEl.duration) || mediaEl.duration <= 0) return;
        mediaEl.currentTime = (seek.value / 1000) * mediaEl.duration;
        timeLabel.textContent = fmt(mediaEl.currentTime) + ' / ' + fmt(mediaEl.duration);
      });

      // Keyboard
      document.addEventListener('keydown', async e => {
        const k = e.key.toLowerCase();
        if (['l', 'r', 'b', 's'].includes(k)) {
          ensureCtx();
          if (ctx.state === 'suspended') try {
            await ctx.resume();
          } catch {}
          resumeBtn.style.display = 'none';
        }
        if (k === 'l') playTone('left');
        else if (k === 'r') playTone('right');
        else if (k === 'b') playTone('both');
        else if (k === 's') stopAll();
      });

      // Autoplay helpers
      document.addEventListener('click', async () => {
        if (!ctx) return;
        if (ctx.state === 'suspended') {
          try {
            await ctx.resume();
            resumeBtn.style.display = 'none';
            showToast('Audio resumed');
          } catch {}
        }
      });
      resumeBtn.addEventListener('click', async () => {
        if (!ctx) return;
        try {
          await ctx.resume();
          resumeBtn.style.display = 'none';
          showToast('Audio resumed');
        } catch {}
      });

      // แสดง resume ป้องกัน autoplay
      setTimeout(() => {
        try {
          ensureCtx();
          if (ctx.state === 'suspended') resumeBtn.style.display = 'inline-flex';
        } catch {}
      }, 400);
    })();
  </script>
</body>

</html>