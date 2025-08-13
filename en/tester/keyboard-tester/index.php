<?php
    // We can place header include here for cleaner HTML structure
    // CORRECTED PATH: From /tester/keyboard-tester/, we need to go up two levels to the root.
    include_once __DIR__ . '/../../../includes/header_en.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>Online Keyboard Test | Thai-English Keyboard Tester for Mac</title>
  <meta name="description" content="An online MacBook keyboard test tool. Supports all languages. Press any key for an instant visual test with a layout that matches your MacBook." />
  <meta name="keywords" content="keyboard test, macbook keyboard test, keyboard tester, key test, broken keyboard, check keys, thai, english, online keyboard test, mac" />

  <link rel="alternate" hreflang="th" href="https://cmnsfixmac.com/tester/keyboard-tester/" />
  <link rel="alternate" hreflang="en" href="https://cmnsfixmac.com/en/tester/keyboard-tester/" />
  <link rel="alternate" hreflang="x-default" href="https://cmnsfixmac.com/en/tester/keyboard-tester/" />

  <meta property="og:title" content="Online MacBook Keyboard Test Tool" />
  <meta property="og:description" content="Supports Thai-English keyboards with instant visual feedback and a true-to-life MacBook layout. 100% free to use." />
  <meta property="og:image" content="https://cmnsfixmac.com/assets/img/keyboard-tester-thumbnail.jpg" />
  <meta property="og:url" content="https://cmnsfixmac.com/en/tester/keyboard-tester/" />
  <meta property="og:type" content="website" />

  <meta name="robots" content="index, follow" />
  <link rel="canonical" href="https://cmnsfixmac.com/en/tester/keyboard-tester/" />
  <link rel="shortcut icon" href="/assets/img/favicon1.png" />

  <link rel="stylesheet" href="/assets/css/navbar-style.css">
  <link rel="stylesheet" href="/assets/css/footer-style.css">
  <link rel="stylesheet" href="/tester/keyboard-tester/assets/css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
  
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-3WXK9GWN7C"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-3WXK9GWN7C');
  </script>
</head>

<body>
<?php // The header is already included at the top ?>

  <div class="container">
    <h1>Online Keyboard Tester</h1>
    <div class="live-display">
        You pressed: <span id="key-log">-</span>
    </div>
    <section class="keyboard-section">
      <div class="keyboard-row row-1">
        <div class="push-left"><div class="key key-esc" data-key="Escape">Esc</div></div>
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
        <div class="push-right"><div class="key key-power" data-key="Power"><span class="material-symbols-outlined">power_settings_new</span></div></div>
      </div>

      <div class="keyboard-row row-2">
        <div class="push-left"><div class="key key-tilde" data-code="Backquote">~</div></div>
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
        <div class="push-right"><div class="key key-delete wide" data-code="Backspace"><span class="label-button-right">Delete</span></div></div>
      </div>

      <div class="keyboard-row row-3">
        <div class="push-left"><div class="key key-tab wide" data-code="Tab"><span class="label-button-left">Tab</span></div></div>
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
        <div class="push-right"><div class="key key-backslash" data-code="Backslash">\</div></div>
      </div>

      <div class="keyboard-row row-4">
        <div class="push-left"><div class="key key-capslock wide" data-code="CapsLock"><span class="label-button-left">caps lock</span></div></div>
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
        <div class="push-right"><div class="key key-return wide" data-code="Enter"><span class="label-button-right">return</span></div></div>
      </div>

      <div class="keyboard-row row-5">
        <div class="push-left"><div class="key key-shift-left wide" data-code="ShiftLeft"><span class="label-button-left">shift</span></div></div>
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
        <div class="push-right"><div class="key key-shift-right wide" data-code="ShiftRight"><span class="label-button-right">shift</span></div></div>
      </div>

      <div class="keyboard-row row-6">
        <div class="push-left"><div class="key key-fn" data-code="Fn"><span class="symbol-top-right">fn</span><span class="material-symbols-outlined">language</span></div></div>
        <div class="key key-control" data-code="ControlLeft"><span class="symbol-top-right">⌃</span><span class="label-button">control</span></div>
        <div class="key key-option" data-code="AltLeft"><span class="symbol-top-right">⌥</span><span class="label-button">option</span></div>
        <div class="key key-command-left wide" data-code="MetaLeft"><span class="symbol-top-right">⌘</span><span class="label-button">command</span></div>
        <div class="key key-space space" data-code="Space"></div>
        <div class="key key-command-right wide" data-code="MetaRight"><span class="symbol-top-left">⌘</span><span class="label-button">command</span></div>
        <div class="key key-option" data-code="AltRight"><span class="symbol-top-left">⌥</span><span class="label-button">option</span></div>
        <div class="arrow-cluster">
          <div class="key key-left" data-code="ArrowLeft"><span class="material-symbols-outlined">arrow_left</span></div>
          <div class="arrow-vertical">
            <div class="key key-up" data-code="ArrowUp"><span class="material-symbols-outlined">arrow_drop_up</span></div>
            <div class="key key-down" data-code="ArrowDown"><span class="material-symbols-outlined">arrow_drop_down</span></div>
          </div>
          <div class="key key-right" data-code="ArrowRight"><span class="material-symbols-outlined">arrow_right</span></div>
        </div>
      </div>
    </section>
  </div>
  
  <?php // No footer include needed if the header already includes it or if the layout doesn't require a standard footer. ?>
  <?php // If you have a footer_en.php, you can include it like this: ?>
  <?php  include_once __DIR__ . '/../../../includes/footer_en.php'; ?>
  <script src="/en/tester/keyboard-tester/assets/js/script.js"></script>
</body>
</html>