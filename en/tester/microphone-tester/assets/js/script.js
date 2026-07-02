/* ==========================================================
   Microphone Tester engine (EN)
   - permission requested on "Start" (not on load)
   - single AudioContext feeding both level meter + waveform
   - records with whichever mime the browser supports
   ========================================================== */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const micSelect   = $("mic-select");
  const startBtn    = $("start-btn");
  const stopBtn     = $("stop-btn");
  const micBar      = $("mic-bar");
  const micPercent  = $("mic-percent");
  const recordBtn   = $("record-btn");
  const stopRecBtn  = $("stop-record-btn");
  const playBtn     = $("play-btn");
  const downloadBtn = $("download-btn");
  const audioEl     = $("audio-playback");
  const recStatus   = $("recording-status");
  const waveBox     = $("waveform");

  const accent = (getComputedStyle(document.documentElement)
    .getPropertyValue("--accent") || "#fc7404").trim();

  let stream = null, ctx = null, analyser = null, raf = 0;
  let freqData, timeData, canvas, cctx;
  let recorder = null, chunks = [], blobUrl = null;

  /* ── in-page toast (replaces native alert) ── */
  function toast(msg, type) {
    type = type || "warn";
    let wrap = document.querySelector(".mic-toast-wrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.className = "mic-toast-wrap";
      document.body.appendChild(wrap);
    }
    const icon = type === "error" ? "error" : type === "info" ? "info" : "warning";
    const el = document.createElement("div");
    el.className = "mic-toast " + type;
    el.setAttribute("role", "status");
    el.innerHTML = '<span class="material-symbols-rounded">' + icon + "</span><span></span>";
    el.lastChild.textContent = msg;
    wrap.appendChild(el);
    requestAnimationFrame(() => el.classList.add("show"));
    setTimeout(() => { el.classList.remove("show"); setTimeout(() => el.remove(), 260); }, 3400);
  }

  async function refreshDevices() {
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const mics = devices.filter((d) => d.kind === "audioinput");
      if (!mics.length || !mics[0].label) return;
      const current = micSelect.value;
      micSelect.innerHTML = "";
      mics.forEach((d, i) => {
        const o = document.createElement("option");
        o.value = d.deviceId;
        o.text = d.label || ("Microphone " + (i + 1));
        micSelect.appendChild(o);
      });
      if (current) micSelect.value = current;
    } catch (_) { /* ignore */ }
  }

  function setupCanvas() {
    canvas = document.createElement("canvas");
    canvas.width = waveBox.clientWidth || 480;
    canvas.height = 110;
    waveBox.innerHTML = "";
    waveBox.appendChild(canvas);
    cctx = canvas.getContext("2d");
  }

  function loop() {
    raf = requestAnimationFrame(loop);
    if (!analyser) return;

    analyser.getByteFrequencyData(freqData);
    let sum = 0;
    for (let i = 0; i < freqData.length; i++) sum += freqData[i];
    const avg = sum / freqData.length;
    const pct = Math.min(100, Math.round(avg * 1.8));
    micBar.style.width = pct + "%";
    micPercent.textContent = pct + "%";

    analyser.getByteTimeDomainData(timeData);
    const w = canvas.width, h = canvas.height;
    cctx.clearRect(0, 0, w, h);
    cctx.lineWidth = 2;
    cctx.strokeStyle = accent;
    cctx.beginPath();
    const slice = w / timeData.length;
    for (let i = 0, x = 0; i < timeData.length; i++, x += slice) {
      const y = (timeData[i] / 128) * (h / 2);
      i === 0 ? cctx.moveTo(x, y) : cctx.lineTo(x, y);
    }
    cctx.lineTo(w, h / 2);
    cctx.stroke();
  }

  async function start() {
    const id = micSelect.value;
    if (stream) stream.getTracks().forEach((t) => t.stop());
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        audio: id ? { deviceId: { exact: id } } : true
      });
    } catch (e) {
      toast("Cannot access the microphone — please allow mic permission", "error");
      return;
    }

    ctx = new (window.AudioContext || window.webkitAudioContext)();
    analyser = ctx.createAnalyser();
    analyser.fftSize = 2048;
    ctx.createMediaStreamSource(stream).connect(analyser);
    freqData = new Uint8Array(analyser.frequencyBinCount);
    timeData = new Uint8Array(analyser.fftSize);

    setupCanvas();
    cancelAnimationFrame(raf);
    loop();
    refreshDevices();

    startBtn.style.display = "none";
    stopBtn.style.display = "inline-flex";
  }

  function stop() {
    cancelAnimationFrame(raf); raf = 0;
    if (recorder && recorder.state !== "inactive") recorder.stop();
    if (stream) { stream.getTracks().forEach((t) => t.stop()); stream = null; }
    if (ctx) { ctx.close(); ctx = null; }
    analyser = null;
    micBar.style.width = "0%";
    micPercent.textContent = "0%";
    if (cctx) cctx.clearRect(0, 0, canvas.width, canvas.height);
    startBtn.style.display = "inline-flex";
    stopBtn.style.display = "none";
    recordBtn.style.display = "inline-flex";
    stopRecBtn.style.display = "none";
    recStatus.style.display = "none";
  }

  function pickMime() {
    const types = ["audio/mp4", "audio/webm;codecs=opus", "audio/webm"];
    return types.find((t) => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || "";
  }

  function startRecording() {
    if (!stream) { toast("Click 'Start Test' first to enable the microphone", "warn"); return; }
    chunks = [];
    const mime = pickMime();
    try {
      recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
    } catch (e) {
      toast("Cannot start recording: " + e.message, "error");
      return;
    }
    recorder.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
    recorder.onstop = () => {
      const type = recorder.mimeType || mime || "audio/webm";
      const blob = new Blob(chunks, { type });
      if (blobUrl) URL.revokeObjectURL(blobUrl);
      blobUrl = URL.createObjectURL(blob);
      audioEl.src = blobUrl;
      audioEl.style.display = "block";
      playBtn.style.display = "inline-flex";
      downloadBtn.style.display = "inline-flex";
      const ext = type.includes("mp4") ? "m4a" : "webm";
      downloadBtn.onclick = () => {
        const a = document.createElement("a");
        a.href = blobUrl; a.download = "mic-recording." + ext; a.click();
      };
    };
    recorder.start();
    recordBtn.style.display = "none";
    stopRecBtn.style.display = "inline-flex";
    recStatus.style.display = "flex";
  }

  function stopRecording() {
    if (recorder && recorder.state !== "inactive") recorder.stop();
    recordBtn.style.display = "inline-flex";
    stopRecBtn.style.display = "none";
    recStatus.style.display = "none";
  }

  startBtn.addEventListener("click", start);
  stopBtn.addEventListener("click", stop);
  recordBtn.addEventListener("click", startRecording);
  stopRecBtn.addEventListener("click", stopRecording);
  playBtn.addEventListener("click", () => audioEl.play());
  micSelect.addEventListener("change", () => { if (stream) start(); });

  window.addEventListener("resize", () => { if (canvas && waveBox.clientWidth) canvas.width = waveBox.clientWidth; });
})();
