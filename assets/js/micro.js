/* ==========================================================
   CMNS — Micro-interactions JS
   ========================================================== */

/* ── Page Loader ── */
(function () {
  const loader = document.getElementById('page-loader');
  if (!loader) return;
  const hide = () => loader.classList.add('hidden');
  if (document.readyState === 'complete') {
    setTimeout(hide, 200);
  } else {
    window.addEventListener('load', () => setTimeout(hide, 300));
  }
})();

/* ── Scroll Progress Bar ── */
(function () {
  const bar = document.getElementById('scroll-progress');
  if (!bar) return;
  function update() {
    const scrolled = window.scrollY;
    const total    = document.documentElement.scrollHeight - window.innerHeight;
    bar.style.width = (total > 0 ? (scrolled / total) * 100 : 0) + '%';
  }
  window.addEventListener('scroll', update, { passive: true });
  update();
})();

/* ── Custom Cursor ── */
(function () {
  if (!window.matchMedia('(pointer: fine)').matches) return;

  const dot  = document.getElementById('cursor-dot');
  const ring = document.getElementById('cursor-ring');
  if (!dot || !ring) return;
  // Custom cursor is disabled via CSS (display:none) — bail out instead of
  // running a forever rAF loop + mousemove writes against hidden elements.
  if (getComputedStyle(dot).display === 'none') return;

  let mx = -100, my = -100;
  let rx = -100, ry = -100;
  let rafId;

  document.addEventListener('mousemove', (e) => {
    mx = e.clientX; my = e.clientY;
    dot.style.left = mx + 'px';
    dot.style.top  = my + 'px';
  }, { passive: true });

  function lerp(a, b, t) { return a + (b - a) * t; }

  function tick() {
    rx = lerp(rx, mx, 0.13);
    ry = lerp(ry, my, 0.13);
    ring.style.left = rx + 'px';
    ring.style.top  = ry + 'px';
    rafId = requestAnimationFrame(tick);
  }
  tick();

  // hover state on interactive elements
  document.addEventListener('mouseover', (e) => {
    if (e.target.closest('a, button, [role="button"], input, select, textarea, label')) {
      document.body.classList.add('cursor-hover');
    }
  });
  document.addEventListener('mouseout', (e) => {
    if (e.target.closest('a, button, [role="button"], input, select, textarea, label')) {
      document.body.classList.remove('cursor-hover');
    }
  });

  // click effect
  document.addEventListener('mousedown', () => document.body.classList.add('cursor-click'));
  document.addEventListener('mouseup',   () => document.body.classList.remove('cursor-click'));

  // hide when out of window
  document.addEventListener('mouseleave', () => {
    dot.style.opacity  = '0';
    ring.style.opacity = '0';
  });
  document.addEventListener('mouseenter', () => {
    dot.style.opacity  = '1';
    ring.style.opacity = '1';
  });
})();

/* ── Back to Top ── */
(function () {
  const btn = document.getElementById('back-to-top');
  if (!btn) return;

  function toggle() {
    btn.classList.toggle('visible', window.scrollY > 400);
  }
  window.addEventListener('scroll', toggle, { passive: true });
  toggle();

  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();
