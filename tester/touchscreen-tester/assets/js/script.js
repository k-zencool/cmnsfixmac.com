/* ==========================================================
   Touchscreen Tester engine
   - Pointer Events: works with touch, mouse and pen alike
   - tracks every active pointer, draws strokes, live count
   ========================================================== */
(function () {
  "use strict";

  const COUNT_LABEL = (n) => n + " จุด";

  const canvas = document.getElementById("ts-canvas");
  const ctx = canvas.getContext("2d");
  const countEl = document.getElementById("ts-count");
  const gridBtn = document.querySelector('.ts-btn[data-action="grid"]');

  const css = getComputedStyle(document.documentElement);
  const accent = (css.getPropertyValue("--accent") || "#fc7404").trim();
  const gridColor = (css.getPropertyValue("--border-strong") || "#d0d0d5").trim();

  const active = new Map();   // pointerId -> {x, y}
  let showGrid = false;
  let dpr = Math.max(1, window.devicePixelRatio || 1);

  function resize() {
    dpr = Math.max(1, window.devicePixelRatio || 1);
    canvas.width = Math.round(window.innerWidth * dpr);
    canvas.height = Math.round(window.innerHeight * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);   // draw in CSS pixels
    if (showGrid) drawGrid();
  }

  function drawGrid(rows = 8, cols = 6) {
    const w = window.innerWidth, h = window.innerHeight;
    ctx.save();
    ctx.strokeStyle = gridColor;
    ctx.lineWidth = 1;
    for (let r = 1; r < rows; r++) {
      const y = (h / rows) * r;
      ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke();
    }
    for (let c = 1; c < cols; c++) {
      const x = (w / cols) * c;
      ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, h); ctx.stroke();
    }
    ctx.restore();
  }

  function clearCanvas() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    active.clear();
    updateCount();
    if (showGrid) drawGrid();
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

  // ── toolbar ──
  document.querySelectorAll(".ts-btn[data-action]").forEach((b) => {
    b.addEventListener("click", () => {
      switch (b.dataset.action) {
        case "clear":
          clearCanvas();
          break;
        case "grid":
          showGrid = !showGrid;
          gridBtn.classList.toggle("is-active", showGrid);
          if (showGrid) drawGrid();
          else clearCanvas();
          break;
        case "save": {
          const link = document.createElement("a");
          link.download = "touchscreen-test.png";
          link.href = canvas.toDataURL();
          link.click();
          break;
        }
      }
    });
  });

  window.addEventListener("resize", resize);
  resize();
  updateCount();
})();
