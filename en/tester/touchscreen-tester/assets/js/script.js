const canvas = document.getElementById('touchCanvas');
const ctx = canvas.getContext('2d');
const touchCountDisplay = document.getElementById('touchCountDisplay');
let lastTouches = {};
let showGrid = false;

function resizeCanvas() {
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
  if (showGrid) drawGrid();
}

function drawGrid(rows = 8, cols = 6) {
  ctx.strokeStyle = '#dddddd';
  ctx.lineWidth = 1;
  const rowHeight = canvas.height / rows;
  const colWidth = canvas.width / cols;
  for (let r = 1; r < rows; r++) {
    ctx.beginPath();
    ctx.moveTo(0, r * rowHeight);
    ctx.lineTo(canvas.width, r * rowHeight);
    ctx.stroke();
  }
  for (let c = 1; c < cols; c++) {
    ctx.beginPath();
    ctx.moveTo(c * colWidth, 0);
    ctx.lineTo(c * colWidth, canvas.height);
    ctx.stroke();
  }
}

function toggleGrid() {
  showGrid = !showGrid;
  if (showGrid) drawGrid();
  else clearCanvas();
}

function downloadImage() {
  const link = document.createElement('a');
  link.download = 'touchscreen-test.png';
  link.href = canvas.toDataURL();
  link.click();
}

function clearCanvas() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  lastTouches = {};
  if (showGrid) drawGrid();
}

function handleTouch(e) {
  e.preventDefault();
  touchCountDisplay.textContent = `สัมผัส: ${e.touches.length} จุด`;

  for (let i = 0; i < e.touches.length; i++) {
    const touch = e.touches[i];
    const x = touch.clientX;
    const y = touch.clientY;
    const id = touch.identifier;
    const radius = touch.radiusX || 20;

    if (lastTouches[id]) {
      ctx.beginPath();
      ctx.moveTo(lastTouches[id].x, lastTouches[id].y);
      ctx.lineTo(x, y);
      ctx.strokeStyle = '#0df05f';
      ctx.lineWidth = radius * 2;
      ctx.lineCap = 'round';
      ctx.stroke();
    } else {
      ctx.beginPath();
      ctx.arc(x, y, radius, 0, 2 * Math.PI);
      ctx.fillStyle = '#0df05f';
      ctx.fill();
    }

    lastTouches[id] = { x, y };
  }
}

function endTouch(e) {
  for (let i = 0; i < e.changedTouches.length; i++) {
    delete lastTouches[e.changedTouches[i].identifier];
  }
  touchCountDisplay.textContent = `สัมผัส: ${e.touches.length} จุด`;
}

window.addEventListener('resize', resizeCanvas);
resizeCanvas();

canvas.addEventListener('touchstart', handleTouch, false);
canvas.addEventListener('touchmove', handleTouch, false);
canvas.addEventListener('touchend', endTouch, false);

let headerVisible = true;

canvas.addEventListener('click', () => {
  headerVisible = !headerVisible;
  const headerEl = document.querySelector('.header-toggle');
  if (headerEl) {
    headerEl.classList.toggle('hidden', !headerVisible);
  }
});
