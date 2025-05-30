<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$productdetails = $_POST['productdetails'] ?? '';

// บันทึกสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'] ?? '';
  $price = $_POST['price'] ?? '';
  $category = $_POST['category'] ?? '';
  $details = $_POST['productdetails'] ?? '';
  $status = isset($_POST['status']) ? 1 : 0;

  $main_image = '';
  if (!empty($_FILES['main_image']['name'])) {
    $main_image = time() . '_' . $_FILES['main_image']['name'];
    move_uploaded_file($_FILES['main_image']['tmp_name'], __DIR__ . '/../../uploads/' . $main_image);
  }

  $stmt = $pdo->prepare("INSERT INTO products (name, price, category, productdetails, main_image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
  $stmt->execute([$name, $price, $category, $details, $main_image, $status]);
  $product_id = $pdo->lastInsertId();

  // อัปโหลดรูปเพิ่มเติม
  if (!empty($_FILES['additional_images']['name'][0])) {
    foreach ($_FILES['additional_images']['tmp_name'] as $index => $tmpName) {
      if ($_FILES['additional_images']['error'][$index] === UPLOAD_ERR_OK) {
        $image_name = time() . '_' . basename($_FILES['additional_images']['name'][$index]);
        move_uploaded_file($tmpName, __DIR__ . '/../../uploads/' . $image_name);

        $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image) VALUES (?, ?)");
        $imgStmt->execute([$product_id, $image_name]);
      }
    }
  }

  header('Location: index.php');
  exit;
}
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
      <span>เพิ่มสินค้าใหม่</span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <h2 style="margin: 20px 0;">เพิ่มสินค้าใหม่</h2>

  <form id="productForm" action="" method="POST" enctype="multipart/form-data" class="form-section">
    <label>ชื่อสินค้า:</label>
    <input type="text" name="name" required>

    <label>ราคา (บาท):</label>
    <input type="number" step="0.01" name="price" required>

    <label>หมวดหมู่:</label>
    <input type="text" name="category">

    <label>รายละเอียดสินค้า:</label>
    <textarea name="productdetails" class="rich-text"><?= htmlspecialchars($productdetails) ?></textarea>

    <label>รูปหลัก:</label>
    <input type="file" name="main_image" accept="image/*" onchange="previewMainImage(event)">
    <div id="mainImagePreview"></div>

    <label>รูปเพิ่มเติม:</label>
    <input type="file" id="additionalInput" name="additional_images[]" multiple accept="image/*" onchange="handleAdditionalImages(event)">
    <div id="additionalImagesPreview"></div>

    <div class="form-actions">
      <div class="form-checkbox">
        <input type="checkbox" name="status" id="status" checked>
        <label for="status">แสดงสินค้า</label>
      </div>
    </div>

      <button type="submit">บันทึกสินค้า</button>
      <a href="index.php" class="back-link">← ย้อนกลับ</a>
  </form>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<!-- TinyMCE -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@5/tinymce.min.js"></script>
<script>
tinymce.init({
  selector: 'textarea[name="productdetails"]',
  menubar: false,
  height: 300,
  plugins: 'lists link',
  toolbar: 'undo redo | bold italic underline | bullist numlist | link removeformat',
  branding: false
});
</script>

<!-- Preview รูปหลัก + รูปเพิ่มเติม -->
<script>
let selectedAdditionalFiles = [];

function previewMainImage(event) {
  const preview = document.getElementById('mainImagePreview');
  preview.innerHTML = '';
  const file = event.target.files[0];
  if (file) {
    const img = document.createElement('img');
    img.src = URL.createObjectURL(file);
    img.style.width = '100px';
    img.style.borderRadius = '8px';
    img.style.marginTop = '10px';
    preview.appendChild(img);
  }
}

function handleAdditionalImages(event) {
  const preview = document.getElementById('additionalImagesPreview');
  const input = document.getElementById('additionalInput');
  preview.innerHTML = '';
  selectedAdditionalFiles = Array.from(input.files);

  selectedAdditionalFiles.forEach((file, index) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'image-wrapper';

    const img = document.createElement('img');
    img.src = URL.createObjectURL(file);

    const removeBtn = document.createElement('button');
    removeBtn.innerText = '✕';
    removeBtn.className = 'remove-btn';
    removeBtn.onclick = (e) => {
      e.preventDefault();
      selectedAdditionalFiles.splice(index, 1);
      updateAdditionalInput();
      handleAdditionalImages({ target: { files: selectedAdditionalFiles } });
    };

    wrapper.appendChild(img);
    wrapper.appendChild(removeBtn);
    preview.appendChild(wrapper);
  });
}

function updateAdditionalInput() {
  const dataTransfer = new DataTransfer();
  selectedAdditionalFiles.forEach(file => dataTransfer.items.add(file));
  document.getElementById('additionalInput').files = dataTransfer.files;
}
</script>
