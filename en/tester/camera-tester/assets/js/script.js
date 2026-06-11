/* ==========================================================
   Camera Tester engine
   - permission requested on "Start" (not on load)
   - live preview, device switch, front/back flip, snapshot
   ========================================================== */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const select   = $("cam-select");
  const video    = $("cam-video");
  const placeholder = $("cam-placeholder");
  const resLabel = $("cam-resolution");
  const startBtn = $("cam-start");
  const stopBtn  = $("cam-stop");
  const flipBtn  = $("cam-flip");
  const snapBtn  = $("cam-snap");
  const shotBox  = $("cam-shot");
  const preview  = $("cam-preview");
  const download = $("cam-download");
  const flash    = $("cam-flash");
  const canvas   = $("cam-canvas");

  let stream = null;
  let useFront = true;   // mirror only matters for front-facing
  let mirrored = false;

  /* ── in-page toast (replaces native alert) ── */
  function toast(msg, type) {
    type = type || "warn";
    let wrap = document.querySelector(".cam-toast-wrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.className = "cam-toast-wrap";
      document.body.appendChild(wrap);
    }
    const icon = type === "error" ? "error" : type === "info" ? "info" : "warning";
    const el = document.createElement("div");
    el.className = "cam-toast " + type;
    el.setAttribute("role", "status");
    el.innerHTML = '<span class="material-symbols-rounded">' + icon + "</span><span></span>";
    el.lastChild.textContent = msg;
    wrap.appendChild(el);
    requestAnimationFrame(() => el.classList.add("show"));
    setTimeout(() => { el.classList.remove("show"); setTimeout(() => el.remove(), 260); }, 3400);
  }

  /* ── device list (labels need permission, so fill after first start) ── */
  async function refreshDevices() {
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const cams = devices.filter((d) => d.kind === "videoinput");
      if (!cams.length || !cams[0].label) return;        // no permission yet
      const current = select.value;
      const keep = select.querySelector('option[value=""]');
      select.innerHTML = "";
      if (keep) select.appendChild(keep);
      cams.forEach((d, i) => {
        const o = document.createElement("option");
        o.value = d.deviceId;
        o.text = d.label || ("Camera " + (i + 1));
        select.appendChild(o);
      });
      if (current) select.value = current;
    } catch (_) { /* ignore */ }
  }

  function setMirror(on) {
    mirrored = on;
    video.classList.toggle("mirror", on);
  }

  async function start() {
    if (stream) stream.getTracks().forEach((t) => t.stop());

    const id = select.value;
    const constraints = id
      ? { video: { deviceId: { exact: id } } }
      : { video: { facingMode: useFront ? "user" : "environment" } };

    try {
      stream = await navigator.mediaDevices.getUserMedia(constraints);
    } catch (e) {
      toast("Cannot access the camera — please allow camera permission", "error");
      return;
    }

    video.srcObject = stream;
    setMirror(!id && useFront);
    placeholder.style.display = "none";

    const track = stream.getVideoTracks()[0];
    const s = track.getSettings();
    if (s.width && s.height) {
      resLabel.textContent = s.width + " × " + s.height;
      resLabel.style.display = "block";
    }

    refreshDevices();
    startBtn.style.display = "none";
    stopBtn.style.display = "inline-flex";
    flipBtn.style.display = "inline-flex";
    snapBtn.style.display = "inline-flex";
  }

  function stop() {
    if (stream) { stream.getTracks().forEach((t) => t.stop()); stream = null; }
    video.srcObject = null;
    placeholder.style.display = "flex";
    resLabel.style.display = "none";
    startBtn.style.display = "inline-flex";
    stopBtn.style.display = "none";
    flipBtn.style.display = "none";
    snapBtn.style.display = "none";
  }

  function flip() {
    useFront = !useFront;
    select.value = "";        // facingMode flip ignores explicit deviceId
    start();
  }

  function snapshot() {
    if (!stream) { toast("Click 'Start Test' first to enable the camera", "warn"); return; }

    flash.classList.add("fire");
    setTimeout(() => flash.classList.remove("fire"), 120);

    const w = video.videoWidth, h = video.videoHeight;
    if (!w || !h) { toast("Camera not ready yet, please try again", "warn"); return; }
    canvas.width = w;
    canvas.height = h;
    const cx = canvas.getContext("2d");
    if (mirrored) { cx.translate(w, 0); cx.scale(-1, 1); }
    cx.drawImage(video, 0, 0, w, h);

    const url = canvas.toDataURL("image/png");
    preview.src = url;
    download.href = url;
    shotBox.style.display = "flex";
    shotBox.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  /* ── wiring ── */
  startBtn.addEventListener("click", start);
  stopBtn.addEventListener("click", stop);
  flipBtn.addEventListener("click", flip);
  snapBtn.addEventListener("click", snapshot);
  select.addEventListener("change", () => { if (stream) start(); });
})();
