<?php if (empty($page_has_own_head)): ?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'CMNS Fix Mac — ซ่อม Mac & iPhone เชียงใหม่' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <script src="/assets/js/theme.js"></script>
  <link rel="stylesheet" href="/assets/css/design-tokens.css?v=1">
  <link rel="stylesheet" href="/assets/css/navbar-style.css?v=10">
  <link rel="stylesheet" href="/assets/css/footer-style.css?v=2">
  <link rel="stylesheet" href="/assets/css/micro.css?v=2">
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
        <a href="/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
      </div>

      <!-- Desktop nav -->
      <div class="menu-desktop-only">
        <nav class="menu">

          <a href="/" class="highlight-home">
            <span class="material-symbols-rounded">home</span> หน้าแรก
          </a>

          <!-- Services mega-dropdown -->
          <div class="menu-dropdown">
            <a href="#services" class="test-device-btn" onclick="return false;">
              <span class="material-symbols-rounded">build</span> บริการ
              <span class="material-symbols-rounded arrow">expand_more</span>
            </a>
            <div class="dropdown-menu dropdown-menu-services">
              <a href="/services/macbook.php">
                <span class="material-symbols-rounded svc-icon">laptop_mac</span>
                <div><strong>ซ่อม MacBook</strong><span>เปลี่ยนจอ บอร์ด แบต</span></div>
              </a>
              <a href="/services/imac.php">
                <span class="material-symbols-rounded svc-icon">desktop_mac</span>
                <div><strong>ซ่อม iMac</strong><span>อัปเกรด SSD, RAM</span></div>
              </a>
              <a href="/services/iphone.php">
                <span class="material-symbols-rounded svc-icon">phone_iphone</span>
                <div><strong>ซ่อม iPhone / iPad</strong><span>เปลี่ยนจอ แบต กล้อง</span></div>
              </a>
              <a href="/services/apple-watch.php">
                <span class="material-symbols-rounded svc-icon">watch</span>
                <div><strong>ซ่อม Apple Watch</strong><span>เปลี่ยนจอ แบต</span></div>
              </a>
              <a href="/services/airpods.php">
                <span class="material-symbols-rounded svc-icon">headphones</span>
                <div><strong>ซ่อม AirPods</strong><span>แบต ไมค์ ชาร์จ</span></div>
              </a>
              <a href="/services/software.php">
                <span class="material-symbols-rounded svc-icon">terminal</span>
                <div><strong>Software & OS</strong><span>ลง macOS โปรแกรม</span></div>
              </a>
            </div>
          </div>

          <a href="/works/">
            <span class="material-symbols-rounded">construction</span> ผลงาน
          </a>
          <a href="/shop">
            <span class="material-symbols-rounded">storefront</span> ร้านค้า
          </a>
          <a href="/articles/">
            <span class="material-symbols-rounded">description</span> บทความ
          </a>
          <a href="/buyback/">
            <span class="material-symbols-rounded">laptop_mac</span> รับซื้อ
          </a>
          <a href="/warranty/">
            <span class="material-symbols-rounded">verified</span> ประกัน
          </a>

          <!-- Device testers dropdown -->
          <div class="menu-dropdown">
            <a href="/tester/" class="test-device-btn">
              <span class="material-symbols-rounded">smart_toy</span> ทดสอบ
              <span class="material-symbols-rounded arrow">expand_more</span>
            </a>
            <div class="dropdown-menu">
              <a href="/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span>หน้าจอ</a>
              <a href="/tester/keyboard-tester/"><span class="material-symbols-rounded">keyboard</span>คีย์บอร์ด</a>
              <a href="/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span>ไมโครโฟน</a>
              <a href="/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span>กล้อง</a>
              <a href="/tester/sounds-tester/"><span class="material-symbols-rounded">volume_up</span>ลำโพง</a>
              <a href="/tester/touchscreen-tester/"><span class="material-symbols-rounded">touch_app</span>ทัชสกรีน</a>
            </div>
          </div>

          <?php
          if (isset($switch_to_lang_url) && !empty($switch_to_lang_url)) {
              $en_version_url = $switch_to_lang_url;
          } else {
              $en_version_url = '/en' . $_SERVER['REQUEST_URI'];
              $en_version_url = str_replace('/index.php', '/', $en_version_url);
          }
          ?>
          <a href="<?= htmlspecialchars($en_version_url) ?>" class="language-switch-btn" title="Switch to English">
            <span class="material-symbols-rounded">language</span> EN
          </a>

          <button class="theme-toggle" onclick="toggleTheme()" aria-label="เปลี่ยนธีม">
            <span class="material-symbols-rounded theme-toggle-icon">dark_mode</span>
          </button>

          <a href="tel:0841511684" class="nav-call-cta">
            <span class="material-symbols-rounded">call</span> โทรปรึกษาฟรี
          </a>

        </nav>
      </div>

      <!-- Mobile controls -->
      <div class="mobile-controls">
        <?php
        if (isset($switch_to_lang_url) && !empty($switch_to_lang_url)) {
            $en_version_url_mobile = $switch_to_lang_url;
        } else {
            $en_version_url_mobile = '/en' . $_SERVER['REQUEST_URI'];
            $en_version_url_mobile = str_replace('/index.php', '/', $en_version_url_mobile);
        }
        ?>
        <a href="<?= htmlspecialchars($en_version_url_mobile) ?>" class="language-switch-btn" title="Switch to English">
          <span class="material-symbols-rounded">language</span> EN
        </a>
        <button class="theme-toggle" onclick="toggleTheme()" aria-label="เปลี่ยนธีม">
          <span class="material-symbols-rounded theme-toggle-icon">dark_mode</span>
        </button>
        <div id="hamburger" class="hamburger" onclick="toggleSidebar()">
          <span></span><span></span><span></span>
        </div>
      </div>

    </div>
  </header>

  <!-- Sidebar -->
  <div id="sidebar" class="sidebar">
    <div class="sidebar-header">
      <a href="/"><img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo"></a>
      <button class="sidebar-close" onclick="toggleSidebar()" aria-label="ปิดเมนู">
        <span class="material-symbols-rounded">close</span>
      </button>
    </div>

    <nav class="sidebar-menu">
      <a href="/" class="highlight-home">
        <span class="material-symbols-rounded">home</span> หน้าแรก
      </a>
      <a href="/works/"><span class="material-symbols-rounded">construction</span> ผลงาน</a>
      <a href="/shop"><span class="material-symbols-rounded">storefront</span> ร้านค้า</a>
      <a href="/articles/"><span class="material-symbols-rounded">description</span> บทความ</a>
      <a href="/buyback/"><span class="material-symbols-rounded">laptop_mac</span> รับซื้อเครื่อง</a>
      <a href="/warranty/"><span class="material-symbols-rounded">verified</span> ตรวจสอบประกัน</a>
    </nav>

    <div class="sidebar-divider"></div>

    <!-- Services sub-menu -->
    <div class="sidebar-dropdown">
      <button class="dropdown-toggle" onclick="toggleSidebarDropdown(this)">
        <span style="display:flex;align-items:center;gap:8px">
          <span class="material-symbols-rounded">build</span> บริการซ่อม
        </span>
        <span class="material-symbols-rounded dropdown-icon">expand_more</span>
      </button>
      <div class="dropdown-submenu">
        <a href="/services/macbook.php"><span class="material-symbols-rounded">laptop_mac</span> MacBook</a>
        <a href="/services/imac.php"><span class="material-symbols-rounded">desktop_mac</span> iMac</a>
        <a href="/services/iphone.php"><span class="material-symbols-rounded">smartphone</span> iPhone / iPad</a>
        <a href="/services/apple-watch.php"><span class="material-symbols-rounded">watch</span> Apple Watch</a>
        <a href="/services/airpods.php"><span class="material-symbols-rounded">headphones</span> AirPods</a>
        <a href="/services/software.php"><span class="material-symbols-rounded">terminal</span> Software & OS</a>
      </div>
    </div>

    <!-- Tester sub-menu -->
    <div class="sidebar-dropdown">
      <button class="dropdown-toggle" onclick="toggleSidebarDropdown(this)">
        <span style="display:flex;align-items:center;gap:8px">
          <span class="material-symbols-rounded">smart_toy</span> ทดสอบอุปกรณ์
        </span>
        <span class="material-symbols-rounded dropdown-icon">expand_more</span>
      </button>
      <div class="dropdown-submenu">
        <a href="/tester/"><span class="material-symbols-rounded">apps</span> ดูทั้งหมด</a>
        <a href="/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span> หน้าจอ</a>
        <a href="/tester/keyboard-tester/"><span class="material-symbols-rounded">keyboard</span> คีย์บอร์ด</a>
        <a href="/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span> ไมค์</a>
        <a href="/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span> กล้อง</a>
        <a href="/tester/sounds-tester/"><span class="material-symbols-rounded">volume_up</span> ลำโพง</a>
        <a href="/tester/touchscreen-tester/"><span class="material-symbols-rounded">touch_app</span> ทัชสกรีน</a>
      </div>
    </div>

    <div class="sidebar-divider"></div>

    <a href="tel:0841511684" class="sidebar-call-cta">
      <span class="material-symbols-rounded">call</span> โทรปรึกษาฟรี 084-151-1684
    </a>

    <div class="sidebar-footer">
      <a href="<?= htmlspecialchars($en_version_url ?? '/en/') ?>" class="language-switch-btn">
        <span class="material-symbols-rounded">language</span> EN
      </a>
      <div class="sidebar-theme-row">
        <span class="sidebar-theme-label">โหมดมืด</span>
        <button class="theme-toggle" onclick="toggleTheme()" aria-label="เปลี่ยนธีม">
          <span class="material-symbols-rounded theme-toggle-icon">dark_mode</span>
        </button>
      </div>
    </div>
  </div>

  <div id="sidebar-overlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

  <!-- Page loader -->
  <div id="page-loader">
    <div class="loader-logo"><img src="/assets/img/Logo1.png" alt="CMNS FixMac"></div>
    <div class="loader-ring"></div>
  </div>

  <!-- Scroll progress bar -->
  <div id="scroll-progress"></div>

  <!-- Custom cursor (desktop only) -->
  <div id="cursor-dot"></div>
  <div id="cursor-ring"></div>

  <!-- Back to top -->
  <button id="back-to-top" aria-label="กลับด้านบน">
    <span class="material-symbols-rounded">keyboard_arrow_up</span>
  </button>

  <script src="/assets/js/micro.js?v=2" defer></script>

  <script>
    function toggleSidebar() {
      document.getElementById("sidebar").classList.toggle("open");
      document.getElementById("sidebar-overlay").classList.toggle("show");
      document.getElementById("hamburger").classList.toggle("open");
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
      const dropdown = button.closest('.sidebar-dropdown');
      const allDropdowns = document.querySelectorAll('.sidebar-dropdown');
      allDropdowns.forEach(d => { if (d !== dropdown) d.classList.remove('open'); });
      dropdown.classList.toggle('open');
    }
  </script>
