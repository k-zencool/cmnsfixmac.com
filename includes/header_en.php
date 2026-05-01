<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CMNS Mac Repair</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="/assets/css/navbar-style.css?v=3">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded&display=block" rel="stylesheet" />
</head>

<body>
  <header class="navbar navbar-top">
    <div class="nav-container">
      <div class="nav-logo">
        <a href="/en/"> <img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo">
        </a>
      </div>
      <div class="menu-desktop-only">

        <nav class="menu">
          <a href="/en/" class="highlight-home"> <span class="material-symbols-rounded">home</span> Home</a>

          <a href="/en/works.php"><span class="material-symbols-rounded">construction</span>Our Work</a>
          <a href="/en/shop"><span class="material-symbols-rounded">storefront</span>Shop</a>
          <a href="/en/articles.php"><span class="material-symbols-rounded">description</span>Articles</a>
          <a href="/en/buyback.php"><span class="material-symbols-rounded">laptop_mac</span>Sell Your Device</a>
          <a href="/en/warranty.php">
            <span class="material-symbols-rounded">verified</span> warranty
          </a>

          <div class="menu-dropdown">
            <a href="#" class="test-device-btn" role="button" onclick="return false;"> <span
                class="material-symbols-rounded">smart_toy</span>Test Device 
                <span class="material-symbols-rounded arrow">expand_more</span>
            </a>

            <div class="dropdown-menu">
              <a href="/en/tester/monitor-tester"><span class="material-symbols-rounded">monitor</span>Screen Test</a>
              <a href="/en/tester/keyboard-tester"><span class="material-symbols-rounded">keyboard</span>Keyboard Test</a>
              <a href="/en/tester/microphone-tester"><span class="material-symbols-rounded">mic</span>Microphone Test</a>
              <a href="/en/tester/camera-tester"><span class="material-symbols-rounded">photo_camera</span>Camera Test</a>
              <a href="/en/tester/sounds-tester"><span class="material-symbols-rounded">volume_up</span>Speaker Test</a>
            </div>
          </div>

          <div class="nav-call-container">
            <a href="#" class="nav-call" role="button" onclick="copyPhone(); return false;"> <span
                class="material-symbols-rounded icon-phone">call</span> Call Now
            </a>
            <span class="phone-hover" id="phone-number">084-151-1684</span>
          </div>

          <?php
          // [GEMINI FIXED] Logic Switch Language (Relative Path for Localhost support)
          if (isset($switch_to_lang_url) && !empty($switch_to_lang_url)) {
              // ถ้ามี URL เฉพาะส่งมา (จาก article detail) ใช้ตัวนั้น
              $th_version_url = $switch_to_lang_url;
          } else {
              // ตัด /en ออกจาก Path ปัจจุบัน
              $current_uri = $_SERVER['REQUEST_URI'];
              if (strpos($current_uri, '/en') === 0) {
                  $th_version_uri = substr($current_uri, 3); // ตัด 3 ตัวแรก (/en)
              } else {
                  $th_version_uri = $current_uri;
              }
              
              // ถ้าตัดแล้วว่าง ให้ไปหน้าแรก /
              if (empty($th_version_uri) || $th_version_uri === '/') {
                  $th_version_uri = '/';
              }
              $th_version_url = $th_version_uri;
          }
          ?>
          <a href="<?= htmlspecialchars($th_version_url) ?>" class="language-switch-btn" title="เปลี่ยนเป็นภาษาไทย">
            <span class="material-symbols-rounded">language</span> TH
          </a>
        </nav>

      </div>

      <div class="mobile-controls">
        <?php
        // Logic มือถือ เหมือนข้างบน
        if (isset($switch_to_lang_url) && !empty($switch_to_lang_url)) {
            $th_version_url_mobile = $switch_to_lang_url;
        } else {
            $current_uri = $_SERVER['REQUEST_URI'];
            if (strpos($current_uri, '/en') === 0) {
                $th_version_uri_mobile = substr($current_uri, 3);
            } else {
                $th_version_uri_mobile = $current_uri;
            }

            if (empty($th_version_uri_mobile) || $th_version_uri_mobile === '/') {
              $th_version_uri_mobile = '/';
            }
            $th_version_url_mobile = $th_version_uri_mobile;
        }
        ?>
        <a href="<?= htmlspecialchars($th_version_url_mobile) ?>" class="language-switch-btn"
          title="เปลี่ยนเป็นภาษาไทย">
          <span class="material-symbols-rounded">language</span> TH
        </a>

        <div id="hamburger" class="hamburger" onclick="toggleSidebar()">
          <span></span>
          <span></span>
          <span></span>
        </div>
      </div>
    </div>
  </header>

  <div id="sidebar" class="sidebar">
    <div class="sidebar-header">
      <a href="/en/"> <img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"
          style="height: 36px; margin-bottom: 16px;">
      </a>
      <span class="close-btn" onclick="toggleSidebar()">✕</span>
    </div>
    <nav class="sidebar-menu">
      <a href="/en/" class="highlight-home"> <span class="material-symbols-rounded">home</span>
        Home
      </a>
      <a href="/en/works.php"><span class="material-symbols-rounded">construction</span> OurWork</a>
      <a href="/en/shop"><span class="material-symbols-rounded">storefront</span> Shop</a>
      <a href="/en/articles.php"><span class="material-symbols-rounded">description</span>Articles</a>
      <a href="/en/buyback.php"><span class="material-symbols-rounded">laptop_mac</span>Sell YourDevice</a>

      <a href="/en/warranty.php">
        <span class="material-symbols-rounded">verified</span> warranty
      </a>

      <a href="tel:0841511684"><span class="material-symbols-rounded">call</span> Call Now</a>
    </nav>

    <div class="sidebar-dropdown">
      <button class="dropdown-toggle" onclick="toggleSidebarDropdown(this)">
        <span class="material-symbols-rounded">smart_toy</span> Test Device
        <span class="material-symbols-rounded dropdown-icon">expand_more</span>
      </button>
      <div class="dropdown-submenu">
        <a href="/en/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span> Screen</a>
        <a href="/en/tester/keyboard-tester/"><span class="material-symbols-rounded">keyboard</span> Keyboard</a>
        <a href="/en/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span> Mic</a>
        <a href="/en/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span> Camera</a>
        <a href="/en/tester/sounds-tester/"><span class="material-symbols-rounded">volume_up</span> Speaker</a>
        <a href="/tester/touchscreen-tester/"><span class="material-symbols-rounded">touch_app</span> Touchscreen Test</a>
      </div>
    </div>
  </div>

  <div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

  <script>
    function toggleSidebar() {
      document.getElementById("sidebar").classList.toggle("open");
      document.getElementById("sidebar-overlay").classList.toggle("show");
      document.getElementById("hamburger").classList.toggle("open");
    }

    function copyPhone() {
      const phone = document.getElementById('phone-number').innerText;
      navigator.clipboard.writeText(phone).then(() => {
        alert("Phone number copied: " + phone); 
      }).catch(() => {
        alert("Could not copy phone number."); 
      });
    }

    let _navScrolled = false;
    function handleNavbarScroll() {
      const navbar = document.querySelector('.navbar');
      const shouldScroll = window.scrollY > 30;
      if (shouldScroll === _navScrolled) return;
      _navScrolled = shouldScroll;
      if (shouldScroll) {
        navbar.classList.remove('navbar-top');
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.add('navbar-top');
        navbar.classList.remove('scrolled');
      }
    }

    window.addEventListener('scroll', handleNavbarScroll, { passive: true });
    window.addEventListener('DOMContentLoaded', handleNavbarScroll);
  </script>

  <script>
    function toggleSidebarDropdown(button) {
      const dropdown = button.closest('.sidebar-dropdown');
      dropdown.classList.toggle('open');
    }
  </script>

</body>
</html>