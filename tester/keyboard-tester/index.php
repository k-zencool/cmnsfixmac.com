<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- ✅ ปรับปรุง Title ให้มีคีย์เวิร์ด -->
  <title>Test Keyboard ออนไลน์ | Keyboard Tester ภาษาไทย-อังกฤษ</title>

  <!-- ✅ คำอธิบายเว็บไซต์ -->
  <meta name="description" content="เครื่องมือทดสอบคีย์บอร์ด MacBook ออนไลน์ รองรับปุ่มทุกภาษา กดแล้วแสดงผลทันที พร้อมจัด Layout ตามจริงเหมือน MacBook ของคุณ" />

  <!-- ✅ คำหลักที่เกี่ยวข้อง -->
  <meta name="keywords" content="ทดสอบแป้นพิมพ์, MacBook Keyboard, Keyboard Tester, ทดสอบปุ่ม, คีย์บอร์ดเสีย, ตรวจสอบปุ่ม, ภาษาไทย, ภาษาอังกฤษ, macbook, ทดสอบคีย์บอร์ดออนไลน์" />

  <!-- ✅ ปรับ Open Graph (สำหรับแชร์ลิงก์บน Facebook/LINE) -->
  <meta property="og:title" content="เครื่องมือทดสอบแป้นพิมพ์ MacBook ออนไลน์" />
  <meta property="og:description" content="รองรับภาษาไทย-อังกฤษ กดแล้วแสดงผลทันที จัดวางปุ่มตามจริงเหมือนบน MacBook ใช้งานฟรี 100%" />
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/keyboard-tester-thumbnail.jpg" />
  <meta property="og:url" content="https://cmnsfixmac.com/tester/keyboard-tester/" />
  <meta property="og:type" content="website" />

  <!-- ✅ สำหรับ Google -->
  <meta name="robots" content="index, follow" />
  <link rel="canonical" href="https://cmnsfixmac.com/tester/keyboard-tester/" />

  <!-- ✅ CSS & Scripts -->
  <link rel="stylesheet" href="/tester/keyboard-tester/assets/css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
  <script src="/tester/keyboard-tester/assets/js/script.js"></script>
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />
  <link rel="stylesheet" href="/assets/css/navbar-style.css">

</head>

<body>
<?php include_once '../../includes/header.php'; ?>



  <div class="container">
    <h1>Test Keyboard</h1>
        <div class="live-display">
            you press: <span id="key-log">-</span>
        </div>
    <section class="keyboard-section">
      <!-- Row 1 -->
<div class="keyboard-row row-1">
  <div class="push-left">
    <div class="key key-esc" data-key="Escape">Esc</div>
  </div>
  <div class="key key-f1" data-key="F1">F1</div>
  <div class="key key-f2" data-key="F2">F2</div>
  <div class="key key-f3" data-key="F3">F3</div>
  <div class="key key-f4" data-key="F4">F4</div>
  <div class="key key-f5" data-key="F5">F5</div>
  <div class="key key-f6" data-key="F6">F6</div>
  <div class="key key-f7" data-key="F7">F7</div>
  <div class="key key-f8" data-key="F8">F8</div>
  <div class="key key-f9" data-key="F9">F9</div>
  <div class="key key-f10" data-key="F10">F10</div>
  <div class="key key-f11" data-key="F11">F11</div>
  <div class="key key-f12" data-key="F12">F12</div>
  <div class="push-right">
    <div class="key key-power" data-key="Power">
      <span class="material-symbols-outlined">power_settings_new</span>
    </div>
  </div>
</div>


 <!-- Row 2 -->
<div class="keyboard-row row-2">
  <div class="push-left">
    <div class="key key-tilde" data-code="Backquote">~</div>
  </div>
  <div class="key key-1" data-code="Digit1">1</div>
  <div class="key key-2" data-code="Digit2">2</div>
  <div class="key key-3" data-code="Digit3">3</div>
  <div class="key key-4" data-code="Digit4">4</div>
  <div class="key key-5" data-code="Digit5">5</div>
  <div class="key key-6" data-code="Digit6">6</div>
  <div class="key key-7" data-code="Digit7">7</div>
  <div class="key key-8" data-code="Digit8">8</div>
  <div class="key key-9" data-code="Digit9">9</div>
  <div class="key key-0" data-code="Digit0">0</div>
  <div class="key key-minus" data-code="Minus">-</div>
  <div class="key key-equal" data-code="Equal">=</div>
  <div class="push-right">
    <div class="key key-delete wide" data-code="Backspace">
      <span class="label-button-right">Delete</span>
    </div>
  </div>
</div>



   <!-- Row 3 -->
<div class="keyboard-row row-3">
  <div class="push-left">
    <div class="key key-tab wide" data-code="Tab">
      <samp class="label-button-left">Tab</samp>
    </div>
  </div>
  <div class="key key-q" data-code="KeyQ">Q</div>
  <div class="key key-w" data-code="KeyW">W</div>
  <div class="key key-e" data-code="KeyE">E</div>
  <div class="key key-r" data-code="KeyR">R</div>
  <div class="key key-t" data-code="KeyT">T</div>
  <div class="key key-y" data-code="KeyY">Y</div>
  <div class="key key-u" data-code="KeyU">U</div>
  <div class="key key-i" data-code="KeyI">I</div>
  <div class="key key-o" data-code="KeyO">O</div>
  <div class="key key-p" data-code="KeyP">P</div>
  <div class="key key-bracketleft" data-code="BracketLeft">[</div>
  <div class="key key-bracketright" data-code="BracketRight">]</div>
  <div class="push-right">
    <div class="key key-backslash" data-code="Backslash">\</div>
  </div>
</div>



   <!-- Row 4 -->
<div class="keyboard-row row-4">
  <div class="push-left">
    <div class="key key-capslock wide" data-code="CapsLock">
      <span class="label-button-left">capslock</span>
    </div>
  </div>
  <div class="key key-a" data-code="KeyA">A</div>
  <div class="key key-s" data-code="KeyS">S</div>
  <div class="key key-d" data-code="KeyD">D</div>
  <div class="key key-f" data-code="KeyF">F</div>
  <div class="key key-g" data-code="KeyG">G</div>
  <div class="key key-h" data-code="KeyH">H</div>
  <div class="key key-j" data-code="KeyJ">J</div>
  <div class="key key-k" data-code="KeyK">K</div>
  <div class="key key-l" data-code="KeyL">L</div>
  <div class="key key-semicolon" data-code="Semicolon">;</div>
  <div class="key key-quote" data-code="Quote">'</div>
  <div class="push-right">
    <div class="key key-return wide" data-code="Enter">
      <span class="label-button-right">Return</span>
    </div>
  </div>
</div>


<!-- Row 5 -->
<div class="keyboard-row row-5">
  <div class="push-left">
    <div class="key key-shift-left" data-code="ShiftLeft">
      <span class="label-button-left">Shift</span>
    </div>
  </div>
  <div class="key key-z" data-code="KeyZ">Z</div>
  <div class="key key-x" data-code="KeyX">X</div>
  <div class="key key-c" data-code="KeyC">C</div>
  <div class="key key-v" data-code="KeyV">V</div>
  <div class="key key-b" data-code="KeyB">B</div>
  <div class="key key-n" data-code="KeyN">N</div>
  <div class="key key-m" data-code="KeyM">M</div>
  <div class="key key-comma" data-code="Comma">,</div>
  <div class="key key-period" data-code="Period">.</div>
  <div class="key key-slash" data-code="Slash">/</div>
  <div class="push-right">
    <div class="key key-shift-right" data-code="ShiftRight">
      <span class="label-button-right">Shift</span>
    </div>
  </div>
</div>


   <!-- Row 6 -->
<div class="keyboard-row row-6">
  <div class="push-left">
    <div class="key key-fn" data-code="Fn">
      <span class="symbol-top-right">fn</span>
      <span class="material-symbols-outlined">language</span>
    </div>
  </div>

  <div class="key key-control" data-code="ControlLeft">
    <span class="symbol-top-right">⌃</span>
    <span class="label-button">Control</span>
  </div>

  <div class="key key-option" data-code="AltLeft">
    <span class="symbol-top-right">⌥</span>
    <span class="label-button">Option</span>
  </div>

  <div class="key key-command-left" data-code="MetaLeft">
    <span class="symbol-top-right">⌘</span>
    <span class="label-button">Command</span>
  </div>

  <div class="key key-space space" data-code="Space">
    Space
  </div>

  <div class="key key-command-right" data-code="MetaRight">
    <span class="symbol-top-left">⌘</span>
    <span class="label-button">Command</span>
  </div>

  <div class="key key-option" data-code="AltRight">
    <span class="symbol-top-left">⌥</span>
    <span class="label-button">Option</span>
  </div>

  <div class="arrow-cluster">
    <div class="key key-left" data-code="ArrowLeft">
      <span class="material-symbols-outlined">arrow_left</span>
    </div>
    <div class="arrow-vertical">
      <div class="key key-up" data-code="ArrowUp">
        <span class="material-symbols-outlined">arrow_drop_up</span>
      </div>
      <div class="key key-down" data-code="ArrowDown">
        <span class="material-symbols-outlined">arrow_drop_down</span>
      </div>
    </div>
    <div class="key key-right" data-code="ArrowRight">
      <span class="material-symbols-outlined">arrow_right</span>
    </div>
  </div>
</div>



    </section>
  </div>

<script>
const keyLogEl = document.getElementById("key-log");
let keyHistory = [];

document.addEventListener("keydown", (e) => {
  const displayKey = formatKeyLabel(e);
  keyHistory.push(displayKey);

  const combined = keyHistory.join(" 👉 ");

  // ✅ ถ้ายาวเกิน 100 ตัวอักษร → รีเซ็ตใหม่
  if (combined.length > 100) {
    keyHistory = [];
    keyLogEl.textContent = "-";
    return;
  }

  keyLogEl.textContent = combined;
});

function formatKeyLabel(e) {
  const key = e.key;
  const keyMap = {
    " ": "Space",
    "Shift": "⇧ Shift",
    "Enter": "↵",
    "Tab": "⇥",
    "Backspace": "⌫",
    "Escape": "Esc",
    "Control": "Ctrl",
    "Alt": "Alt",
    "Meta": "⌘",
    "ArrowLeft": "←",
    "ArrowRight": "→",
    "ArrowUp": "↑",
    "ArrowDown": "↓"
  };
  return keyMap[key] || key;
}


</script>

</body>
</html>

