<!-- Sidebar Layout -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span class="logo-icon material-symbols-rounded">settings</span>
    <h2>Admin Panel</h2>
<!-- ปุ่ม X ปิด Sidebar -->
<button class="close-btn" onclick="toggleSidebar()" aria-label="ปิดเมนู">✖</button>
  </div>

  <nav class="sidebar-nav">
    <a href="/admin/dashboard.php">
      <span class="material-symbols-rounded">space_dashboard</span> Dashboard
    </a>

    <a href="/admin/products/index.php">
      <span class="material-symbols-rounded">shopping_bag</span> จัดการสินค้า/บริการ
    </a>

    <a href="/admin/repairs/index.php">
      <span class="material-symbols-rounded">handyman</span> ผลงานทั้งหมด
    </a>

    <a href="/admin/articles/index.php">
      <span class="material-symbols-rounded">description</span> จัดการบทความ
    </a>

    <a href="/admin/youtube/index.php">
      <span class="material-symbols-rounded">smart_display</span> จัดการวิดีโอ
    </a>

    <a href="/admin/add_admin.php">
      <span class="material-symbols-rounded">person_add</span> เพิ่มแอดมิน
    </a>

    <a href="/admin/admin_list.php">
      <span class="material-symbols-rounded">group</span> รายชื่อแอดมิน
    </a>

    <a href="/admin/change_password.php">
      <span class="material-symbols-rounded">lock_reset</span> เปลี่ยนรหัสผ่าน
    </a>

    <a href="/admin/logout.php">
      <span class="material-symbols-rounded">logout</span> ออกจากระบบ
    </a>
  </nav>
</aside>

<!-- ปุ่มเปิด Sidebar (เฉพาะมือถือ) -->
<button class="menu-btn" onclick="toggleSidebar()">☰</button>

<!-- Overlay -->
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>

<!-- Script toggle sidebar -->
<script>
  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('overlay').classList.toggle('show');
  }

  document.addEventListener('DOMContentLoaded', function () {
    const closeBtn = document.getElementById('close-sidebar');
    if (closeBtn) {
      closeBtn.addEventListener('click', toggleSidebar);
    }
  });
</script>
