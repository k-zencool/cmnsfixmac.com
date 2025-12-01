<?php
// admin/sidebar_admin.php — Sidebar พร้อมเมนูย่อยแบบ Hover/Click รองรับ PHP < 7.4
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

function has($needle)
{
  global $uri;
  return strpos($uri, $needle) !== false;
}

$partsOpen = has('/admin/parts/'); // เปิดเมนูย่อยอัตโนมัติถ้าอยู่ในหน้า parts
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span class="logo-icon material-symbols-rounded">settings</span>
    <h2>Admin Panel</h2>
    <button class="close-btn" onclick="toggleSidebar()" aria-label="ปิดเมนู">✖</button>
  </div>

  <nav class="sidebar-nav">
    <a class="<?php echo has('/admin/dashboard/') ? 'active' : ''; ?>" href="/admin/dashboard/">
      <span class="material-symbols-rounded">space_dashboard</span> Dashboard
    </a>




    <?php // <-- [NEW] เพิ่มเมนูนี้เข้าไป 
    ?>
    <a class="<?php echo has('/admin/shop/') ? 'active' : ''; ?>" href="/admin/shop/">
      <span class="material-symbols-rounded">storefront</span> จัดการหน้าร้าน (Shop)
    </a>

    <a class="<?php echo has('/admin/repairs/') ? 'active' : ''; ?>" href="/admin/repairs/">
      <span class="material-symbols-rounded">handyman</span> ผลงานทั้งหมด
    </a>

    <a class="<?php echo has('/admin/before_after/') ? 'active' : ''; ?>" href="/admin/before_after/">
      <span class="material-symbols-rounded">auto_awesome_mosaic</span> สร้างรูป Before-After
    </a>

    <a class="<?php echo has('/admin/articles/') ? 'active' : ''; ?>" href="/admin/articles/">
      <span class="material-symbols-rounded">description</span> จัดการบทความ
    </a>

    <a class="<?php echo has('/admin/youtube/') ? 'active' : ''; ?>" href="/admin/youtube/">
      <span class="material-symbols-rounded">smart_display</span> จัดการวิดีโอ
    </a>

    <a class="<?php echo has('/admin/pricing/') ? 'active' : ''; ?>" href="/admin/pricing/">
      <span class="material-symbols-rounded">sell</span> จัดการราคาซ่อม
    </a>

    <a class="<?php echo has('/admin/warranty/') ? 'active' : ''; ?>" href="/admin/warranty/">
      <span class="material-symbols-rounded">verified_user</span> รับประกัน (Warranty)
    </a>

    <!-- ===== เมนูอะไหล่ (Parts) ===== -->
    <div class="has-sub <?php echo $partsOpen ? 'open' : ''; ?>">
      <div class="sub-head">
        <a class="<?php echo $partsOpen ? 'active' : ''; ?>" href="/admin/parts/index.php">
          <span class="material-symbols-rounded">inventory_2</span> อะไหล่ (Parts)
        </a>
        <button type="button"
          class="sub-toggle"
          aria-label="สลับเมนูย่อยอะไหล่"
          aria-expanded="<?php echo $partsOpen ? 'true' : 'false'; ?>"
          onclick="toggleSubmenu(event, 'parts-sub')">
          <span class="material-symbols-rounded">expand_more</span>
        </button>
      </div>

      <div id="parts-sub" class="submenu <?php echo $partsOpen ? 'open' : ''; ?>">
        <a href="/admin/parts/index.php?tab=new">
          <span class="material-symbols-rounded">chevron_right</span> อะไหล่มือ 1
        </a>
        <a href="/admin/parts/index.php?tab=used">
          <span class="material-symbols-rounded">chevron_right</span> อะไหล่มือ 2
        </a>
        <a href="/admin/parts/index.php?tab=donor">
          <span class="material-symbols-rounded">chevron_right</span> เครื่อง
        </a>
        <a href="/admin/parts/index.php?tab=history">
          <span class="material-symbols-rounded">chevron_right</span> ประวัติ
        </a>
      </div>
    </div>
    <!-- ===== จบเมนูอะไหล่ ===== -->

    <hr style="margin: 10px 15px; border-color: #f0f0f0;">

    <?php if (!empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
      <a class="<?php echo has('/admin/user/') ? 'active' : ''; ?>" href="/admin/user/">
        <span class="material-symbols-rounded">manage_accounts</span> จัดการผู้ใช้งาน
      </a>
    <?php endif; ?>

    <a href="/admin/logout.php">
      <span class="material-symbols-rounded">logout</span> ออกจากระบบ
    </a>
  </nav>
</aside>

<button class="menu-btn" onclick="toggleSidebar()">☰</button>
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>

<style>
  .has-sub .sub-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  .has-sub .sub-toggle {
    background: transparent;
    border: 0;
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
  }

  .submenu {
    display: none;
    padding-left: 36px;
  }

  .submenu a {
    display: block;
  }

  .has-sub:hover .submenu {
    display: block;
  }

  .submenu.open {
    display: block;
  }

  .has-sub.open .sub-toggle .material-symbols-rounded {
    transform: rotate(180deg);
    transition: transform .15s;
  }
</style>

<script>
  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('overlay').classList.toggle('show');
  }

  function toggleSubmenu(e, id) {
    e.preventDefault();
    e.stopPropagation();
    var sub = document.getElementById(id);
    var wrapper = e.currentTarget.closest('.has-sub');
    var expanded = e.currentTarget.getAttribute('aria-expanded') === 'true';
    if (sub) sub.classList.toggle('open');
    if (wrapper) wrapper.classList.toggle('open');
    e.currentTarget.setAttribute('aria-expanded', expanded ? 'false' : 'true');
  }
</script>