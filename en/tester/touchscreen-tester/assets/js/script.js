/* ==========================================================
   Touchscreen Tester engine
   - Pointer Events: works with touch, mouse and pen alike
   - tracks every active pointer, draws strokes, live count
   ========================================================== */
(function () {
  "use strict";

  const COUNT_LABEL = (n) => n + " point" + (n === 1 ? "" : "s");

  const canvas = document.getElementById("ts-canvas");
  const ctx = canvas.getContext("2d");
  const countEl = document.getElementById("ts-count");
  const gridBtn = document.querySelector('.ts-btn[data-action="grid"]');
  const fsBtn = document.querySelector('.ts-btn[data-action="fullscreen"]');
  const main = document.querySelector(".ts-main");
  const FS_ENTER = "Fullscreen";
  const FS_EXIT = "Exit";

  const accent = (getComputedStyle(document.documentElement)
    .getPropertyValue("--accent") || "#fc7404").trim();
  const gridEl = document.getElementById("ts-grid");   // grid lives on its own CSS layer

  const active = new Map();   // pointerId -> {x, y}
  let showGrid = false;
  let dpr = Math.max(1, window.devicePixelRatio || 1);

  function resize() {
    dpr = Math.max(1, window.devicePixelRatio || 1);
    canvas.width = Math.round(window.innerWidth * dpr);
    canvas.height = Math.round(window.innerHeight * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);   // draw in CSS pixels
  }

  function clearCanvas() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    active.clear();
    updateCount();
  }

  function updateCount() {
    countEl.textContent = COUNT_LABEL(active.size);
  }

  // contact radius: real touch size if available, else a sensible default
  function radiusOf(e) {
    const r = Math.max(e.width || 0, e.height || 0) / 2;
    return r > 1 ? r : 18;
  }

  function dot(x, y, r) {
    ctx.beginPath();
    ctx.arc(x, y, r, 0, 2 * Math.PI);
    ctx.fillStyle = accent;
    ctx.fill();
  }

  function line(a, b, r) {
    ctx.beginPath();
    ctx.moveTo(a.x, a.y);
    ctx.lineTo(b.x, b.y);
    ctx.strokeStyle = accent;
    ctx.lineWidth = r * 2;
    ctx.lineCap = "round";
    ctx.stroke();
  }

  canvas.addEventListener("pointerdown", (e) => {
    e.preventDefault();
    try { canvas.setPointerCapture(e.pointerId); } catch (_) {}
    const p = { x: e.clientX, y: e.clientY };
    dot(p.x, p.y, radiusOf(e));
    active.set(e.pointerId, p);
    updateCount();
  });

  canvas.addEventListener("pointermove", (e) => {
    if (!active.has(e.pointerId)) return;     // ignore hover (mouse without button)
    const prev = active.get(e.pointerId);
    const p = { x: e.clientX, y: e.clientY };
    line(prev, p, radiusOf(e));
    active.set(e.pointerId, p);
  });

  function release(e) {
    if (!active.has(e.pointerId)) return;
    active.delete(e.pointerId);
    updateCount();
  }
  canvas.addEventListener("pointerup", release);
  canvas.addEventListener("pointercancel", release);

  // ── fullscreen ──
  function fsElement() { return document.fullscreenElement || document.webkitFullscreenElement; }
  function toggleFullscreen() {
    if (fsElement()) {
      (document.exitFullscreen || document.webkitExitFullscreen).call(document);
    } else {
      (main.requestFullscreen || main.webkitRequestFullscreen).call(main);
    }
  }
  function onFsChange() {
    const on = !!fsElement();
    if (fsBtn) {
      fsBtn.querySelector(".material-symbols-rounded").textContent = on ? "fullscreen_exit" : "fullscreen";
      fsBtn.querySelector(".ts-fs-label").textContent = on ? FS_EXIT : FS_ENTER;
      fsBtn.classList.toggle("is-active", on);
    }
    resize();   // viewport changed — repaint at the new size
  }
  // iOS Safari can't fullscreen non-video elements — hide the button if unsupported
  if (fsBtn && !(main.requestFullscreen || main.webkitRequestFullscreen)) fsBtn.style.display = "none";
  document.addEventListener("fullscreenchange", onFsChange);
  document.addEventListener("webkitfullscreenchange", onFsChange);

  // ── toolbar ──
  document.querySelectorAll(".ts-btn[data-action]").forEach((b) => {
    b.addEventListener("click", () => {
      switch (b.dataset.action) {
        case "clear":
          clearCanvas();
          break;
        case "grid":
          showGrid = !showGrid;
          gridEl.classList.toggle("show", showGrid);
          gridBtn.classList.toggle("is-active", showGrid);
          break;
        case "save": {
          const link = document.createElement("a");
          link.download = "touchscreen-test.png";
          link.href = canvas.toDataURL();
          link.click();
          break;
        }
        case "fullscreen":
          toggleFullscreen();
          break;
      }
    });
  });

  window.addEventListener("resize", resize);
  resize();
  updateCount();
})();
