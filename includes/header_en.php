<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CMNS Mac Repair</title>
  <link rel="stylesheet" href="/assets/css/navbar-style.css">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
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
          <a href="/en/products.php"><span class="material-symbols-rounded">storefront</span>Shop</a>
          <a href="/en/articles.php"><span class="material-symbols-rounded">description</span>Articles</a>
          <a href="/en/buyback.php"><span class="material-symbols-rounded">laptop_mac</span>Sell Your Device</a>


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
          // สร้าง URL ของหน้าภาษาไทยที่คู่กัน โดยการตัด /en/ ออก
          $th_version_uri = substr($_SERVER['REQUEST_URI'], 3); // ตัด 3 ตัวแรก ('/en')
          if (empty($th_version_uri)) {
            $th_version_uri = '/'; // ถ้าเป็นหน้าแรกสุดของ /en/ ให้มันกลายเป็น /
          }
          $th_version_url = 'https://cmnsfixmac.com' . $th_version_uri;
          ?>
          <a href="<?= htmlspecialchars($th_version_url) ?>" class="language-switch-btn" title="เปลี่ยนเป็นภาษาไทย">
            <span class="material-symbols-rounded">language</span> TH
          </a>
        </nav>


      </div>

      <div class="mobile-controls">
        <?php
        // สร้าง URL ของหน้าภาษาไทยที่คู่กัน (สำหรับมือถือ)
        $th_version_uri_mobile = substr($_SERVER['REQUEST_URI'], 3);
        if (empty($th_version_uri_mobile)) {
          $th_version_uri_mobile = '/';
        }
        $th_version_url_mobile = 'https://cmnsfixmac.com' . $th_version_uri_mobile;
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
      <a href="https://cmnsfixmac.com/en/"> <img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"
          style="height: 36px; margin-bottom: 16px;">
      </a>
      <span class="close-btn" onclick="toggleSidebar()">✕</span>
    </div>
    <nav class="sidebar-menu">
      <a href="https://cmnsfixmac.com/en/" class="highlight-home"> <span class="material-symbols-rounded">home</span>
        Home
      </a>
      <a href="https://cmnsfixmac.com/en/works.php"><span class="material-symbols-rounded">construction</span> OurWork</a>
      <a href="https://cmnsfixmac.com/en/products.php"><span class="material-symbols-rounded">storefront</span> Shop</a>
      <a href="https://cmnsfixmac.com/en/articles.php"><span class="material-symbols-rounded">description</span>Articles</a>
      <a href="https://cmnsfixmac.com/en/buyback.php"><span class="material-symbols-rounded">laptop_mac</span>Sell YourDevice</a>

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
        alert("Phone number copied: " + phone); // Translated alert
      }).catch(() => {
        alert("Could not copy phone number."); // Translated alert
      });
    }

    function handleNavbarScroll() {
      const navbar = document.querySelector('.navbar');
      if (window.scrollY > 30) {
        navbar.classList.remove('navbar-top');
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.add('navbar-top');
        navbar.classList.remove('scrolled');
      }
    }

    window.addEventListener('scroll', handleNavbarScroll);
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