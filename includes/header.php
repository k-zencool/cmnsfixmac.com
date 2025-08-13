<!DOCTYPE html>
<html lang="th">

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
        <a href="https://cmnsfixmac.com/">
          <img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo">
        </a>
      </div>
      <div class="menu-desktop-only">

        <nav class="menu">
          <a href="https://cmnsfixmac.com" class="highlight-home"> <span class="material-symbols-rounded">home</span> หน้าแรก</a>

          <a href="https://cmnsfixmac.com/works.php"><span class="material-symbols-rounded">construction</span>ผลงาน</a>
          <a href="https://cmnsfixmac.com/products.php"><span class="material-symbols-rounded">storefront</span>ร้านค้า</a>
          <a href="https://cmnsfixmac.com/articles.php"><span class="material-symbols-rounded">description</span>บทความ</a>
          <a href="https://cmnsfixmac.com/buyback.php"><span class="material-symbols-rounded">laptop_mac</span>รับซื้อเครื่อง</a>


          <div class="menu-dropdown">
            <a href="#" class="test-device-btn" role="button" onclick="return false;"> <span
                class="material-symbols-rounded">smart_toy</span>ทดสอบอุปกรณ์
                <span class="material-symbols-rounded arrow">expand_more</span>
            </a>

            <div class="dropdown-menu">
              <a href="/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span>ทดสอบหน้าจอ</a>
              <a href="/tester/keyboard-tester/"><span class=  "material-symbols-rounded" >keyboard</span>ทดสอบคีย์บอร์ด</a>
              <a href="/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span>ทดสอบไมโครโฟน</a>
              <a href="/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span>ทดสอบกล้อง</a>
              <a href="/en/tester/sounds-tester"><span class="material-symbols-rounded">volume_up</span>ทดสอบเสียงลำโพง</a>

            </div>
          </div>

          <div class="nav-call-container">
            <a href="#" class="nav-call" role="button" onclick="copyPhone(); return false;"> <span
                class="material-symbols-rounded icon-phone">call</span> โทรเลย
            </a>
            <span class="phone-hover" id="phone-number">084-151-1684</span>
          </div>

          <?php
          // สร้าง URL ของหน้าภาษาอังกฤษที่คู่กัน
          $en_version_url = 'https://cmnsfixmac.com/en' . $_SERVER['REQUEST_URI'];
          // จัดการหน้า index.php ให้สวยงาม
          $en_version_url = str_replace('/index.php', '/', $en_version_url);
          ?>
          <a href="<?= htmlspecialchars($en_version_url) ?>" class="language-switch-btn" title="Switch to English">
            <span class="material-symbols-rounded">language</span> EN
          </a>
        </nav>


      </div>

      <div class="mobile-controls">
        <?php
        // สร้าง URL ของหน้าภาษาอังกฤษที่คู่กัน (สำหรับมือถือ)
        $en_version_url_mobile = 'https://cmnsfixmac.com/en' . $_SERVER['REQUEST_URI'];
        // จัดการหน้า index.php ให้สวยงาม
        $en_version_url_mobile = str_replace('/index.php', '/', $en_version_url_mobile);
        ?>
        <a href="<?= htmlspecialchars($en_version_url_mobile) ?>" class="language-switch-btn"
          title="Switch to English">
          <span class="material-symbols-rounded">language</span> EN
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
      <a href="https://cmnsfixmac.com/">
        <img src="/assets/img/Logo1.png" alt="CMNS FixMac Logo" style="height: 36px; margin-bottom: 16px;">
      </a>
      <span class="close-btn" onclick="toggleSidebar()">✕</span>
    </div>
    <nav class="sidebar-menu">
      <a href="https://cmnsfixmac.com" class="highlight-home">
        <span class="material-symbols-rounded">home</span> หน้าแรก
      </a>
      <a href="https://cmnsfixmac.com/works.php"><span class="material-symbols-rounded">construction</span> ผลงาน</a>
      <a href="https://cmnsfixmac.com/products.php"><span class="material-symbols-rounded">storefront</span> ร้านค้า</a>
      <a href="https://cmnsfixmac.com/articles.php"><span class="material-symbols-rounded">description</span> บทความ</a>
      <a href="https://cmnsfixmac.com/buyback.php"><span class="material-symbols-rounded">laptop_mac</span>รับซื้อเครื่อง</a>

      <a href="tel:0841511684"><span class="material-symbols-rounded">call</span> โทรเลย</a>
    </nav>


    <div class="sidebar-dropdown">
      <button class="dropdown-toggle" onclick="toggleSidebarDropdown(this)">
        <span class="material-symbols-rounded">smart_toy</span> ทดสอบอุปกรณ์
        <span class="material-symbols-rounded dropdown-icon">expand_more</span>
      </button>
      <div class="dropdown-submenu">
        <a href="/tester/monitor-tester/"><span class="material-symbols-rounded">monitor</span> หน้าจอ</a>
        <a href="/tester/keyboard-tester/"><span class="material-symbols-rounded">keyboard</span> คีย์บอร์ด</a>
        <a href="/tester/microphone-tester/"><span class="material-symbols-rounded">mic</span> ไมค์</a>
        <a href="/tester/camera-tester/"><span class="material-symbols-rounded">photo_camera</span> กล้อง</a>
        <a href="/tester/sounds-tester/"><span class="material-symbols-rounded">volume_up</span> ลำโพง</a>
        <a href="/tester/touchscreen-tester/"><span class="material-symbols-rounded">touch_app</span> ตรวจทัชสกรีน</a>
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
        alert("คัดลอกเบอร์โทรแล้ว: " + phone);
      }).catch(() => {
        alert("ไม่สามารถคัดลอกเบอร์ได้");
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