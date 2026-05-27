<?php if (empty($page_has_own_head)): ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'CMNS Fix Mac — Mac & iPhone Repair Chiang Mai' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <script src="/assets/js/theme.js"></script>
  <link rel="stylesheet" href="/assets/css/design-tokens.css?v=1">
  <link rel="stylesheet" href="/assets/css/navbar-style.css?v=4">
  <link rel="stylesheet" href="/assets/css/footer-style.css?v=1">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded&display=block" rel="stylesheet">
  <?php foreach ($page_css ?? [] as $css): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
  <?php endforeach; ?>
  <?php echo $page_head_extra ?? ''; ?>
</head>

<body>
<?php endif; ?>
  <header class="navbar navbar-top">
    <div class="nav-container">
      <div class="nav-logo">
        <a href="/en/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
      </div>
      <div class="menu-desktop-only">
        <nav class="menu">
          <a href="/en/" class="highlight-home">
            <span class="material-symbols-rounded">home</span> Home
          </a>
          <a href="/en/works/"><span class="material-symbols-rounded">construction</span>Our Work</a>
          <a href="/en/shop"><span class="material-symbols-rounded">storefront</span>Shop</a>
          <a href="/en/articles/"><span class="material-symbols-rounded">description</span>Articles</a>
          <a href="/en/buyback/"><span class="material-symbols-rounded">laptop_mac</span>Sell Your Device</a>
          <a href="/en/warranty/"><span class="material-symbols-rounded">verified</span>Warranty</a>

          <div class="menu-dropdown">
            <a href="#" class="test-device-btn" role="button" onclick="return false;">
              <span class="material-symbols-rounded">smart_toy</span>Test Device
              <span class="material-symbols-rounded arrow">expand_more</span>
            </a>
            <div class="dropdown-menu">
              <a href="/en/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span>Screen Test</a>
              <a href="/en/tester/keyboard-tester/"><span class="material-symbols-rounded">keyboard</span>Keyboard Test</a>
              <a href="/en/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span>Microphone Test</a>
              <a href="/en/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span>Camera Test</a>
              <a href="/en/tester/sounds-tester/"><span class="material-symbols-rounded">volume_up</span>Speaker Test</a>
              <a href="/en/tester/touchscreen-tester/"><span class="material-symbols-rounded">touch_app</span>Touchscreen Test</a>
            </div>
          </div>

          <div class="nav-call-container">
            <a href="#" class="nav-call" role="button" onclick="copyPhone(); return false;">
              <span class="material-symbols-rounded icon-phone">call</span> Call Now
            </a>
            <span class="phone-hover" id="phone-number">084-151-1684</span>
          </div>

          <?php
          if (isset($switch_to_lang_url) && !empty($switch_to_lang_url)) {
              $th_version_url = $switch_to_lang_url;
          } else {
              $current_uri = $_SERVER['REQUEST_URI'];
              if (strpos($current_uri, '/en') === 0) {
                  $th_version_uri = substr($current_uri, 3);
              } else {
                  $th_version_uri = $current_uri;
              }
              if (empty($th_version_uri) || $th_version_uri === '/') {
                  $th_version_uri = '/';
              }
              $th_version_url = $th_version_uri;
          }
          ?>
          <a href="<?= htmlspecialchars($th_version_url) ?>" class="language-switch-btn" title="เปลี่ยนเป็นภาษาไทย">
            <span class="material-symbols-rounded">language</span> TH
          </a>

          <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
            <span class="material-symbols-rounded theme-toggle-icon">dark_mode</span>
          </button>
        </nav>
      </div>

      <div class="mobile-controls">
        <?php
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
        <a href="<?= htmlspecialchars($th_version_url_mobile) ?>" class="language-switch-btn" title="เปลี่ยนเป็นภาษาไทย">
          <span class="material-symbols-rounded">language</span> TH
        </a>
        <div id="hamburger" class="hamburger" onclick="toggleSidebar()">
          <span></span><span></span><span></span>
        </div>
      </div>
    </div>
  </header>

  <div id="sidebar" class="sidebar">
    <div class="sidebar-header">
      <a href="/en/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
      <button class="sidebar-close" onclick="toggleSidebar()" aria-label="Close menu">
        <span class="material-symbols-rounded">close</span>
      </button>
    </div>
    <nav class="sidebar-menu">
      <a href="/en/" class="highlight-home">
        <span class="material-symbols-rounded">home</span> Home
      </a>
      <a href="/en/works/"><span class="material-symbols-rounded">construction</span> Our Work</a>
      <a href="/en/shop/"><span class="material-symbols-rounded">storefront</span> Shop</a>
      <a href="/en/articles/"><span class="material-symbols-rounded">description</span> Articles</a>
      <a href="/en/buyback/"><span class="material-symbols-rounded">laptop_mac</span> Sell Your Device</a>
      <a href="/en/warranty/"><span class="material-symbols-rounded">verified</span> Warranty</a>
      <a href="tel:0841511684"><span class="material-symbols-rounded">call</span> Call Now</a>
    </nav>

    <div class="sidebar-dropdown">
      <button class="dropdown-toggle" onclick="toggleSidebarDropdown(this)">
        <span style="display:flex;align-items:center;gap:8px">
          <span class="material-symbols-rounded">smart_toy</span> Test Device
        </span>
        <span class="material-symbols-rounded dropdown-icon">expand_more</span>
      </button>
      <div class="dropdown-submenu">
        <a href="/en/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span> Screen</a>
        <a href="/en/tester/keyboard-tester/"><span class="material-symbols-rounded">keyboard</span> Keyboard</a>
        <a href="/en/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span> Mic</a>
        <a href="/en/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span> Camera</a>
        <a href="/en/tester/sounds-tester/"><span class="material-symbols-rounded">volume_up</span> Speaker</a>
        <a href="/en/tester/touchscreen-tester/"><span class="material-symbols-rounded">touch_app</span> Touchscreen</a>
      </div>
    </div>

    <div class="sidebar-footer">
      <a href="<?= htmlspecialchars($th_version_url ?? '/') ?>" class="language-switch-btn">
        <span class="material-symbols-rounded">language</span> TH
      </a>
      <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
        <span class="material-symbols-rounded theme-toggle-icon">dark_mode</span>
      </button>
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
      navbar.classList.toggle('scrolled', shouldScroll);
      navbar.classList.toggle('navbar-top', !shouldScroll);
    }
    window.addEventListener('scroll', handleNavbarScroll, { passive: true });
    window.addEventListener('DOMContentLoaded', handleNavbarScroll);

    function toggleSidebarDropdown(button) {
      button.closest('.sidebar-dropdown').classList.toggle('open');
    }
  </script>

