let backButtonTimeout;
let colorCycleInterval = null;
let animationBox;
let currentStep = 0;
let lastShownMode = null;
let currentLang = "th";

const testSteps = [
  { mode: "Solid Color: Black", color: "black" },
  { mode: "Solid Color: White", color: "white" },
  { mode: "Solid Color: Red", color: "red" },
  { mode: "Solid Color: Green", color: "green" },
  { mode: "Solid Color: Blue", color: "blue" },
  { mode: "Color Cycle", action: "cycle" },
  { mode: "Grid Checker", action: "grid" },
  { mode: "Crosshair", action: "crosshair" },
  { mode: "Moving Box", action: "animation" },
  { mode: "Dead Pixel", action: "deadpixel" },
  { mode: "Rainbow Scroll", action: "rainbowscroll" }
];

const i18n = {
  en: {
    back: "← Back to Home",
    title: "Welcome to the Monitor Testing Website",
    desc1: "This site is used to test color, background, lines, and display performance on your screen.",
    desc2: 'Click "Start Test" to enter fullscreen and begin testing.',
    start: "Start Test",
    toggleLang: "Switch Language"
  },
  th: {
    back: "← กลับหน้าแรก",
    title: "ยินดีต้อนรับสู่เว็บไซต์ทดสอบหน้าจอ",
    desc1: "เว็บไซต์นี้ใช้สำหรับทดสอบสี พื้นหลัง เส้น และการแสดงผลของหน้าจอ",
    desc2: 'กดปุ่ม "เริ่มทดสอบ" เพื่อเข้าสู่โหมดเต็มจอและเริ่มใช้งาน',
    start: "เริ่มทดสอบ",
    toggleLang: "เปลี่ยนภาษา"
  }
};

function switchLangToggle() {
  currentLang = currentLang === "th" ? "en" : "th";
  document.querySelectorAll("[data-i18n]").forEach(el => {
    const key = el.getAttribute("data-i18n");
    el.textContent = i18n[currentLang][key];
  });
  document.getElementById("lang-toggle").innerHTML =
    `<span class="material-symbols-outlined">translate</span> ${i18n[currentLang].toggleLang}`;
}

document.getElementById("lang-toggle").addEventListener("click", switchLangToggle);

function startTest() {
  const el = document.documentElement;
  el.requestFullscreen?.();
  document.body.classList.add("fullscreen");
  document.getElementById("welcome").style.display = "none";
  document.getElementById("tester").style.display = "flex";
  document.getElementById("lang-toggle").style.display = "none";
  document.querySelector("header.navbar")?.classList.add("hidden-header");
  document.querySelector(".main-container").style.display = "none";
  currentStep = 0;
  lastShownMode = null;
  runCurrentStep();
}

function goBack() {
  document.exitFullscreen?.();
  document.body.classList.remove("fullscreen");
  document.getElementById("tester").style.display = "none";
  document.getElementById("welcome").style.display = "flex";
  document.body.style.background = "#ffffff";
  document.body.style.backgroundImage = "";
  document.getElementById("lang-toggle").style.display = "inline-flex";
  document.querySelector("header.navbar")?.classList.remove("hidden-header");
  document.querySelector(".main-container").style.display = "block";
  clearAll();
}

function runCurrentStep() {
  clearAll();
  const step = testSteps[currentStep];
  if (step.mode !== lastShownMode) {
    showModeLabel(step.mode);
    lastShownMode = step.mode;
  }
  if (step.color) {
    document.body.style.backgroundColor = step.color;
    document.body.style.backgroundImage = "none";
  } else {
    switch (step.action) {
      case "cycle": startColorCycle(); break;
      case "grid": showGrid(); break;
      case "crosshair": showCrosshair(); break;
      case "animation": startAnimationBox(); break;
      case "deadpixel": showDeadPixelScreen(); break;
      case "rainbowscroll": startRainbowScroll(); break;
    }
  }
  flashBackButton();
}

function nextStep() {
  currentStep = (currentStep + 1) % testSteps.length;
  runCurrentStep();
}

function prevStep() {
  currentStep = (currentStep - 1 + testSteps.length) % testSteps.length;
  runCurrentStep();
}

document.addEventListener("keydown", e => {
  if (document.getElementById("tester").style.display !== "none") {
    if (e.key === "ArrowRight") nextStep();
    else if (e.key === "ArrowLeft") prevStep();
  }
});

document.addEventListener("click", () => {
  if (document.getElementById("tester").style.display !== "none") {
    nextStep();
  }
});

function showModeLabel(text) {
  const label = document.getElementById("modeLabel");
  label.textContent = text;
  label.classList.add("show");
  clearTimeout(label._timeout);
  label._timeout = setTimeout(() => label.classList.remove("show"), 1500);
}

function flashBackButton() {
  const btn = document.getElementById("backButton");
  btn.classList.add("show");
  clearTimeout(backButtonTimeout);
  backButtonTimeout = setTimeout(() => btn.classList.remove("show"), 2000);
}

function clearAll() {
  clearInterval(colorCycleInterval);
  colorCycleInterval = null;
  document.body.style.backgroundColor = "";
  document.body.style.backgroundImage = "";
  document.getElementById("cross-vert")?.remove();
  document.getElementById("cross-horz")?.remove();
  document.getElementById("anim-box")?.remove();
  document.getElementById("rainbow-canvas")?.remove();
}



function startColorCycle() {
  let hue = 0;
  colorCycleInterval = setInterval(() => {
    document.body.style.backgroundColor = `hsl(${hue}, 100%, 50%)`;
    document.body.style.backgroundImage = "none";
    hue = (hue + 1) % 360;
  }, 30);
}

function startRainbowScroll() {
  clearInterval(colorCycleInterval);
  document.getElementById("rainbow-canvas")?.remove();

  const canvas = document.createElement("canvas");
  canvas.id = "rainbow-canvas";
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
  canvas.style.position = "fixed";
  canvas.style.top = 0;
  canvas.style.left = 0;
  canvas.style.zIndex = -1;
  document.body.appendChild(canvas);

  const ctx = canvas.getContext("2d");
  let offset = 0;
  colorCycleInterval = setInterval(() => {
    offset = (offset + 2) % canvas.width;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    const grad = ctx.createLinearGradient(-offset, 0, canvas.width - offset, 0);
    grad.addColorStop(0, "red");
    grad.addColorStop(0.17, "orange");
    grad.addColorStop(0.33, "yellow");
    grad.addColorStop(0.5, "green");
    grad.addColorStop(0.67, "blue");
    grad.addColorStop(0.83, "indigo");
    grad.addColorStop(1, "violet");
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
  }, 30);
}


function showGrid() {
  document.body.style.backgroundColor = "white";
  document.body.style.backgroundImage = `
    linear-gradient(to right, #ccc 1px, transparent 1px),
    linear-gradient(to bottom, #ccc 1px, transparent 1px)
  `;
  document.body.style.backgroundSize = "20px 20px";
}

function showCrosshair() {
  document.body.style.backgroundColor = "white";
  const vert = document.createElement("div");
  vert.id = "cross-vert";
  Object.assign(vert.style, {
    position: "fixed", left: "50%", top: "0",
    width: "1px", height: "100vh",
    backgroundColor: "red", zIndex: 999
  });
  const horz = document.createElement("div");
  horz.id = "cross-horz";
  Object.assign(horz.style, {
    position: "fixed", top: "50%", left: "0",
    height: "1px", width: "100vw",
    backgroundColor: "red", zIndex: 999
  });
  document.body.appendChild(vert);
  document.body.appendChild(horz);
}

function startAnimationBox() {
  document.body.style.backgroundColor = "white";
  animationBox = document.createElement("div");
  animationBox.id = "anim-box";
  Object.assign(animationBox.style, {
    width: "50px", height: "50px",
    backgroundColor: "red", position: "fixed",
    top: "50%", left: "0", transform: "translateY(-50%)",
    zIndex: 999
  });
  document.body.appendChild(animationBox);
  let x = 0, dir = 1;
  colorCycleInterval = setInterval(() => {
    x += dir * 5;
    if (x >= window.innerWidth - 50 || x <= 0) dir *= -1;
    animationBox.style.left = x + "px";
  }, 16);
}

function showDeadPixelScreen() {
  document.body.style.backgroundColor = "black";
}
