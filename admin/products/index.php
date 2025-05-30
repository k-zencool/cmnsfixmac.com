<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll();

include '../../templates/header_admin.php';
include '../../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <span>จัดการสินค้า/บริการ</span>
    <a href="../dashboard.php" class="view-site">กลับแดชบอร์ด</a>
  </div>

  <div class="section-header">
    <h2>รายการสินค้า/บริการ</h2>
    <a href="add_product.php" class="btn-primary">+ เพิ่มสินค้าใหม่</a>
  </div>


  <div class="table-container">
    <table class="data-table">
     <thead>
  <tr>
    <th>#</th>
    <th>รูป</th>
    <th>ชื่อสินค้า</th>
    <th>หมวดหมู่</th>
    <th>ราคา</th>
    <th>วันที่เพิ่ม</th>
    <th>สถานะ</th> <!-- ✅ เพิ่ม -->
    <th>จัดการ</th>
  </tr>
</thead>
<tbody>
<?php foreach ($products as $index => $product): ?>
  <tr>
    <td><?= $index + 1 ?></td>
    <td>
      <?php if (!empty($product['main_image'])): ?>
        <img src="../../uploads/<?= htmlspecialchars($product['main_image']) ?>" class="thumb">
      <?php else: ?>
        <span style="color:#aaa;">ไม่มีรูป</span>
      <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($product['name']) ?></td>
    <td><?= htmlspecialchars($product['category']) ?></td>
    <td><?= number_format($product['price'], 0) ?> บาท</td>
    <td><?= date('d/m/Y', strtotime($product['created_at'])) ?></td>
    <td>
      <?php if ($product['status']): ?>
        <span class="badge badge-success">แสดง</span>
      <?php else: ?>
        <span class="badge badge-danger">ไม่แสดง</span>
      <?php endif; ?>
    </td>
    <td>
      <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn-edit">แก้ไข</a>
      <a href="delete_product.php?id=<?= $product['id'] ?>" class="btn-delete" onclick="return confirm('คุณแน่ใจว่าจะลบสินค้านี้?')">ลบ</a>
    </td>
  </tr>
<?php endforeach; ?>
</tbody>

    </table>
  </div>
</main>

<?php include '../../templates/footer_admin.php'; ?>
