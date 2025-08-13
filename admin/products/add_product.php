<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

// if (!isAdmin()) { exit('Access Denied'); }

// ==================================================================
//  จุดที่ 1: ดึง ID ของแอดมินจาก Session
// ==================================================================
$admin_id = $_SESSION['admin_id'] ?? null;

if (!$admin_id) {
    die('Error: You must be logged in to perform this action.'); 
}

$productdetails = $_POST['productdetails'] ?? '';
$productdetails_en = $_POST['productdetails_en'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Thai Fields
  $name = $_POST['name'] ?? '';
  $details = $_POST['productdetails'] ?? '';

  // NEW: English Fields
  $name_en = $_POST['name_en'] ?? '';
  $details_en = $_POST['productdetails_en'] ?? '';

  // General Fields
  $price = $_POST['price'] ?? 0.00;
  $category = $_POST['category'] ?? '';
  $status = isset($_POST['status']) ? 1 : 0;

  $main_image = '';
  if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK) {
    $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.\-\_]/", "_", basename($_FILES['main_image']['name']));
    move_uploaded_file($_FILES['main_image']['tmp_name'], __DIR__ . '/../../uploads/' . $filename);
    $main_image = $filename;
  }
  
  // ==================================================================
  //  จุดที่ 2: เพิ่ม admin_id เข้าไปในคำสั่ง INSERT
  // ==================================================================
  $stmt = $pdo->prepare("INSERT INTO products (admin_id, name, price, category, productdetails, main_image, status, created_at, name_en, productdetails_en) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");

  // ==================================================================
  //  จุดที่ 3: เพิ่ม $admin_id เข้าไปใน execute() เป็นตัวแรก
  // ==================================================================
  if ($stmt->execute([$admin_id, $name, $price, $category, $details, $main_image, $status, $name_en, $details_en])) {
    $product_id = $pdo->lastInsertId();

    if (!empty($_FILES['additional_images']['name'][0])) {
      foreach ($_FILES['additional_images']['tmp_name'] as $index => $tmpName) {
        if (isset($_FILES['additional_images']['error'][$index]) && $_FILES['additional_images']['error'][$index] === UPLOAD_ERR_OK) {
          $image_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.\-\_]/", "_", basename($_FILES['additional_images']['name'][$index]));
          move_uploaded_file($tmpName, __DIR__ . '/../../uploads/' . $image_name);

          $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image) VALUES (?, ?)");
          $imgStmt->execute([$product_id, 'uploads/' . $image_name]); 
        }
      }
    }

    header('Location: index.php');
    exit;
  }
}
?>


<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>เพิ่มสินค้าใหม่ (Add New Product)</span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <h2 style="margin: 20px 0;">เพิ่มสินค้าใหม่</h2>

  <form id="productForm" action="" method="POST" enctype="multipart/form-data" class="form-section">

    <fieldset>
      <legend>ข้อมูลภาษาไทย (Thai Content)</legend>
      <label for="name">ชื่อสินค้า (Product Name TH):</label>
      <input type="text" id="name" name="name" required>

      <label for="productdetails">รายละเอียดสินค้า (Product Details TH):</label>
      <textarea name="productdetails" id="productdetails" class="rich-text"><?= htmlspecialchars($productdetails) ?></textarea>
    </fieldset>

    <hr style="margin: 20px 0;">

    <fieldset>
      <legend>ข้อมูลภาษาอังกฤษ (English Content)</legend>
      <label for="name_en">ชื่อสินค้า (Product Name EN):</label>
      <input type="text" id="name_en" name="name_en"> <label for="productdetails_en">รายละเอียดสินค้า (Product Details EN):</label>
      <textarea name="productdetails_en" id="productdetails_en" class="rich-text"><?= htmlspecialchars($productdetails_en) ?></textarea>
    </fieldset>

    <hr style="margin: 20px 0;">

    <fieldset>
      <legend>ข้อมูลทั่วไป (General Information)</legend>
      <label for="price">ราคา (บาท) (Price in THB):</label>
      <input type="number" step="0.01" id="price" name="price" required>

      <label for="category">หมวดหมู่ (Category):</label>
      <input type="text" id="category" name="category">

      <label for="main_image">รูปหลัก (Main Image):</label>
      <input type="file" id="main_image" name="main_image" accept="image/*" onchange="previewMainImage(event)">
      <div id="mainImagePreview" style="margin-top:10px;"></div>

      <label for="additionalInput">รูปเพิ่มเติม (Additional Images):</label>
      <input type="file" id="additionalInput" name="additional_images[]" multiple accept="image/*" onchange="previewAdditionalImages(event)">
      <div id="additionalImagesPreview" style="margin-top:10px; display: flex; flex-wrap: wrap; gap: 10px;"></div>

      <div class="form-actions" style="margin-top: 20px;">
        <div class="form-checkbox">
          <input type="checkbox" name="status" id="status" checked>
          <label for="status">แสดงสินค้า (Visible)</label>
        </div>
      </div>
    </fieldset>

    <div style="margin-top: 20px;">
      <button type="submit">บันทึกสินค้า (Save Product)</button>
      <a href="index.php" class="back-link" style="margin-left: 10px;">← ย้อนกลับ (Back)</a>
    </div>
  </form>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/tinymce@5/tinymce.min.js"></script>
<script>
  tinymce.init({
    selector: 'textarea.rich-text',
    menubar: false,
    height: 300,
    plugins: 'lists link image paste code',
    toolbar: 'undo redo | formatselect | bold italic underline | bullist numlist | link image | removeformat | code',
    branding: false
  });
</script>

<script>
  function previewMainImage(event) {
    const preview = document.getElementById('mainImagePreview');
    preview.innerHTML = '';
    if (event.target.files && event.target.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.maxWidth = '200px';
        img.style.maxHeight = '200px';
        img.style.borderRadius = '8px';
        img.style.marginTop = '10px';
        preview.appendChild(img);
      }
      reader.readAsDataURL(event.target.files[0]);
    }
  }

  function previewAdditionalImages(event) {
    const preview = document.getElementById('additionalImagesPreview');
    preview.innerHTML = '';
    if (event.target.files && event.target.files.length > 0) {
      Array.from(event.target.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e) {
          const img = document.createElement('img');
          img.src = e.target.result;
          img.style.maxWidth = '100px';
          img.style.maxHeight = '100px';
          img.style.borderRadius = '8px';
          img.style.objectFit = 'cover';
          preview.appendChild(img);
        };
        reader.readAsDataURL(file);
      });
    }
  }
</script>