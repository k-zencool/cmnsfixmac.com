(function () {
  const canvas = document.getElementById('heroCanvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const COUNT = 40;
  const CONNECT_DIST = 120;
  const SPEED = 0.35;

  let W, H, particles, rafId;

  function resize() {
    const hero = canvas.closest('.hero');
    W = canvas.width  = hero.offsetWidth;
    H = canvas.height = hero.offsetHeight;
  }

  function mkParticle() {
    const angle = Math.random() * Math.PI * 2;
    const speed = SPEED * (0.4 + Math.random() * 0.6);
    return {
      x: Math.random() * W,
      y: Math.random() * H,
      vx: Math.cos(angle) * speed,
      vy: Math.sin(angle) * speed,
      r: 1.5 + Math.random() * 1.5,
    };
  }

  function init() {
    resize();
    particles = Array.from({ length: COUNT }, mkParticle);
  }

  function getColors() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark'
      || (window.matchMedia('(prefers-color-scheme: dark)').matches
          && document.documentElement.getAttribute('data-theme') !== 'light');
    return {
      dot:  dark ? 'rgba(252,116,4,0.50)' : 'rgba(252,116,4,0.30)',
      line: 'rgba(252,116,4,',
    };
  }

  function draw() {
    ctx.clearRect(0, 0, W, H);
    const { dot, line } = getColors();

    for (let i = 0; i < COUNT; i++) {
      const p = particles[i];

      if (p.x < 0 || p.x > W) p.vx *= -1;
      if (p.y < 0 || p.y > H) p.vy *= -1;
      p.x += p.vx;
      p.y += p.vy;

      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = dot;
      ctx.fill();

      for (let j = i + 1; j < COUNT; j++) {
        const q = particles[j];
        const dx = p.x - q.x;
        const dy = p.y - q.y;

        /* Manhattan pre-check — skip sqrt ถ้า clearly ไกลเกิน */
        if (Math.abs(dx) > CONNECT_DIST || Math.abs(dy) > CONNECT_DIST) continue;

        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < CONNECT_DIST) {
          const alpha = (1 - dist / CONNECT_DIST) * 0.35;
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(q.x, q.y);
          ctx.strokeStyle = line + alpha + ')';
          ctx.lineWidth = 0.8;
          ctx.stroke();
        }
      }
    }

    rafId = requestAnimationFrame(draw);
  }

  /* หยุดเมื่อ tab ไม่ active */
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      cancelAnimationFrame(rafId);
    } else {
      rafId = requestAnimationFrame(draw);
    }
  });

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(resize, 150);
  });

  init();
  draw();
})();
