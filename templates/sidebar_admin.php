<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span class="logo-icon material-symbols-rounded">settings</span>
    <h2>Admin Panel</h2>
    <button class="close-btn" onclick="toggleSidebar()" aria-label="ปิดเมนู">✖</button>
  </div>

  <nav class="sidebar-nav">
    <a href="/admin/dashboard/">
        <span class="material-symbols-rounded">space_dashboard</span> Dashboard
    </a>

    <a href="/admin/products/">
      <span class="material-symbols-rounded">shopping_bag</span> จัดการสินค้า/บริการ
    </a>

    <a href="/admin/repairs/">
      <span class="material-symbols-rounded">handyman</span> ผลงานทั้งหมด
    </a>

    <a href="/admin/before_after/">
      <span class="material-symbols-rounded">auto_awesome_mosaic</span> สร้างรูป Before-After
    </a>

    <a href="/admin/articles/">
      <span class="material-symbols-rounded">description</span> จัดการบทความ
    </a>

    <a href="/admin/youtube/">
      <span class="material-symbols-rounded">smart_display</span> จัดการวิดีโอ
    </a>

    <a href="/admin/pricing/">
      <span class="material-symbols-rounded">sell</span> จัดการราคาซ่อม
    </a>

    <hr style="margin: 10px 15px; border-color: #f0f0f0;">

    <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin'): ?>
      <a href="/admin/user/">
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

<script>
  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('overlay').classList.toggle('show');
  }
</script>