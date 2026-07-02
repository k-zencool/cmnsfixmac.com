/* ==========================================================
   Monitor / Dead-Pixel Tester engine
   All patterns are drawn on a single canvas (crisp pixels).
   ========================================================== */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const stage  = $("mtStage");
  const canvas = $("mtCanvas");
  if (!stage || !canvas) return;
  const ctx = canvas.getContext("2d", { alpha: false });

  let W = 0, H = 0, dpr = 1;
  let idx = 0, raf = 0, open = false, usedFS = false, idleTimer = 0;
  let swiped = false, touchX = 0, touchY = 0;

  /* ── pattern definitions ──
     each: { name, swatch (css bg for chip/picker), animated, paint(t) } */
  const fill = (c) => { ctx.fillStyle = c; ctx.fillRect(0, 0, W, H); };
  const solid = (name, c) => ({ name, swatch: c, animated: false, paint: () => fill(c) });

  const RAINBOW = "linear-gradient(90deg,red,orange,yellow,#0f0,#09f,#63f,#f0f)";

  const patterns = [
    solid("ขาว",            "#ffffff"),
    solid("ดำ",             "#000000"),
    solid("แดง",            "#ff0000"),
    solid("เขียว",          "#00ff00"),
    solid("น้ำเงิน",         "#0000ff"),
    solid("ฟ้า (Cyan)",      "#00ffff"),
    solid("ม่วงแดง (Magenta)", "#ff00ff"),
    solid("เหลือง",          "#ffff00"),
    solid("เทากลาง",         "#808080"),
    {
      name: "ไล่เฉดเทา", swatch: "linear-gradient(90deg,#000,#fff)", animated: false,
      paint() {
        const g = ctx.createLinearGradient(0, 0, W, 0);
        g.addColorStop(0, "#000"); g.addColorStop(1, "#fff");
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
      }
    },
    {
      name: "ไล่เฉดสี (RGB)", swatch: RAINBOW, animated: false,
      paint() {
        const g = ctx.createLinearGradient(0, 0, W, 0);
        const stops = ["#ff0000", "#ffff00", "#00ff00", "#00ffff", "#0000ff", "#ff00ff", "#ff0000"];
        stops.forEach((c, i) => g.addColorStop(i / (stops.length - 1), c));
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
      }
    },
    {
      name: "ตารางหมากรุก", swatch: "repeating-conic-gradient(#000 0 25%,#fff 0 50%) 0/12px 12px", animated: false,
      paint() {
        const s = Math.round(18 * dpr);
        for (let y = 0; y < H; y += s) {
          for (let x = 0; x < W; x += s) {
            ctx.fillStyle = ((x / s + y / s) & 1) ? "#000" : "#fff";
            ctx.fillRect(x, y, s, s);
          }
        }
      }
    },
    {
      name: "เส้นตาราง", swatch: "linear-gradient(#bbb 1px,transparent 0) 0 0/10px 10px,linear-gradient(90deg,#bbb 1px,#fff 0) 0 0/10px 10px", animated: false,
      paint() {
        fill("#ffffff");
        const s = Math.round(24 * dpr), lw = Math.max(1, Math.round(dpr));
        ctx.fillStyle = "#999";
        for (let x = 0; x < W; x += s) ctx.fillRect(x, 0, lw, H);
        for (let y = 0; y < H; y += s) ctx.fillRect(0, y, W, lw);
      }
    },
    {
      name: "กากบาท + ขอบ", swatch: "#fff", animated: false,
      paint() {
        fill("#ffffff");
        const lw = Math.max(1, Math.round(dpr));
        ctx.fillStyle = "#ff0000";
        ctx.fillRect((W - lw) / 2, 0, lw, H);
        ctx.fillRect(0, (H - lw) / 2, W, lw);
        const b = Math.max(2, Math.round(2 * dpr));
        ctx.fillStyle = "#0000ff";
        ctx.fillRect(0, 0, W, b); ctx.fillRect(0, H - b, W, b);
        ctx.fillRect(0, 0, b, H); ctx.fillRect(W - b, 0, b, H);
        ctx.strokeStyle = "#00aa00"; ctx.lineWidth = lw;
        ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(W, H);
        ctx.moveTo(W, 0); ctx.lineTo(0, H); ctx.stroke();
      }
    },
    {
      name: "กล่องวิ่ง (Ghosting)", swatch: "#7f7f7f", animated: true,
      paint(t) {
        fill("#7f7f7f");
        const box = Math.round(Math.min(W, H) * 0.09);
        const span = W - box;
        const cycle = (t % 4000) / 4000;            // 0..1 over 4s
        const tri = cycle < 0.5 ? cycle * 2 : (1 - cycle) * 2; // ping-pong
        const x = tri * span;
        const y = (H - box) / 2;
        ctx.fillStyle = "#ff3b30";
        ctx.fillRect(x, y, box, box);
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(x, y - box * 1.4, box, box);
        ctx.fillStyle = "#000000";
        ctx.fillRect(x, y + box * 1.4, box, box);
      }
    },
    {
      name: "ไล่สีอัตโนมัติ", swatch: RAINBOW, animated: true,
      paint(t) { fill("hsl(" + ((t / 22) % 360) + ",100%,50%)"); }
    }
  ];

  const N = patterns.length;

  /* ── canvas sizing ── */
  function sizeCanvas() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = Math.floor(window.innerWidth * dpr);
    H = Math.floor(window.innerHeight * dpr);
    canvas.width = W; canvas.height = H;
  }

  /* ── render current pattern ── */
  function render() {
    cancelAnimationFrame(raf); raf = 0;
    const p = patterns[idx];
    $("mtName").textContent = p.name;
    $("mtCount").textContent = (idx + 1) + " / " + N;
    document.querySelectorAll(".mt-pick").forEach((el, i) =>
      el.classList.toggle("is-active", i === idx));
    if (p.animated) {
      const loop = (t) => { p.paint(t); raf = requestAnimationFrame(loop); };
      raf = requestAnimationFrame(loop);
    } else {
      p.paint(0);
    }
  }

  function go(d) { idx = (idx + d + N) % N; render(); showHud(); }
  function goTo(i) { idx = ((i % N) + N) % N; render(); }

  /* ── HUD auto-hide ── */
  function showHud() {
    $("mtHud").classList.add("show");
    stage.classList.remove("is-idle");
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
      if ($("mtPicker").hidden !== false) return; // keep visible while picker open
      $("mtHud").classList.remove("show");
      stage.classList.add("is-idle");
    }, 2600);
  }

  /* ── fullscreen ── */
  function requestFS() {
    const el = document.documentElement;
    if (el.requestFullscreen) el.requestFullscreen().then(() => { usedFS = true; }).catch(() => {});
  }
  function toggleFS() {
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    else requestFS();
  }
  document.addEventListener("fullscreenchange", () => {
    const fs = !!document.fullscreenElement;
    $("mtFs").querySelector(".material-symbols-rounded").textContent = fs ? "fullscreen_exit" : "fullscreen";
    if (!fs && open && usedFS) { usedFS = false; exit(); } // Esc out of native FS = leave test
  });

  /* ── open / close ── */
  function start(jump) {
    open = true;
    stage.hidden = false;
    document.body.style.overflow = "hidden";
    sizeCanvas();
    idx = jump | 0;
    render();
    showHud();
    requestFS();
    if (!localStorage.getItem("mt_hint_seen")) $("mtHint").hidden = false;
  }
  function exit() {
    open = false;
    cancelAnimationFrame(raf); raf = 0;
    stage.hidden = true;
    $("mtPicker").hidden = true;
    document.body.style.overflow = "";
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
  }

  /* ── pattern picker ── */
  function buildPicker() {
    const grid = $("mtPickerGrid");
    grid.innerHTML = "";
    patterns.forEach((p, i) => {
      const b = document.createElement("button");
      b.className = "mt-pick";
      b.innerHTML = '<span class="mt-pick-sw" style="background:' + p.swatch + '"></span><span>' + p.name + "</span>";
      b.addEventListener("click", () => { goTo(i); $("mtPicker").hidden = true; showHud(); });
      grid.appendChild(b);
    });
  }

  /* ── intro chips ── */
  function buildChips() {
    const wrap = $("mtChips");
    if (!wrap) return;
    patterns.forEach((p, i) => {
      const b = document.createElement("button");
      b.className = "mt-chip";
      b.innerHTML = '<span class="mt-chip-swatch" style="background:' + p.swatch + '"></span>' + p.name;
      b.addEventListener("click", () => start(i));
      wrap.appendChild(b);
    });
  }

  /* ── events ── */
  $("mtStart").addEventListener("click", () => start(0));
  $("mtPrev").addEventListener("click", (e) => { e.stopPropagation(); go(-1); });
  $("mtNext").addEventListener("click", (e) => { e.stopPropagation(); go(1); });
  $("mtExit").addEventListener("click", (e) => { e.stopPropagation(); exit(); });
  $("mtFs").addEventListener("click", (e) => { e.stopPropagation(); toggleFS(); });
  $("mtGrid").addEventListener("click", (e) => {
    e.stopPropagation();
    const pk = $("mtPicker");
    pk.hidden = !pk.hidden;
    showHud();
  });
  $("mtPickerClose").addEventListener("click", (e) => { e.stopPropagation(); $("mtPicker").hidden = true; });
  $("mtHintOk").addEventListener("click", (e) => {
    e.stopPropagation();
    $("mtHint").hidden = true;
    localStorage.setItem("mt_hint_seen", "1");
  });

  // click anywhere on the surface = next (ignore controls / after a swipe)
  stage.addEventListener("click", (e) => {
    if (e.target.closest(".mt-hud,.mt-picker,.mt-hint")) return;
    if (swiped) { swiped = false; return; }
    go(1);
  });

  stage.addEventListener("mousemove", showHud);

  stage.addEventListener("touchstart", (e) => {
    const t = e.changedTouches[0]; touchX = t.clientX; touchY = t.clientY; swiped = false;
  }, { passive: true });
  stage.addEventListener("touchend", (e) => {
    const t = e.changedTouches[0];
    const dx = t.clientX - touchX, dy = t.clientY - touchY;
    if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
      swiped = true; go(dx < 0 ? 1 : -1);
    }
    showHud();
  }, { passive: true });

  document.addEventListener("keydown", (e) => {
    if (!open) return;
    switch (e.key) {
      case "ArrowRight": case " ": e.preventDefault(); go(1); break;
      case "ArrowLeft": go(-1); break;
      case "Escape": exit(); break;
      case "f": case "F": toggleFS(); break;
      case "g": case "G": { const pk = $("mtPicker"); pk.hidden = !pk.hidden; showHud(); break; }
    }
  });

  window.addEventListener("resize", () => {
    if (!open) return;
    sizeCanvas();
    if (!raf) render(); // static repaint (animated loop already reads fresh W/H)
  });

  buildPicker();
  buildChips();
})();
