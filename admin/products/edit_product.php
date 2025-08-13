<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

// Add the e() function for security
function e($string) { 
  return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

if (!isset($_GET['id'])) {
  header('Location: index.php');
  exit;
}

$id = intval($_GET['id']);

// Fetch product data, including new English fields
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
  echo "ไม่พบสินค้านี้ (Product not found)"; // Translated error
  exit;
}

// Fetch additional images
$stmtImages = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
$stmtImages->execute([$id]);
$images = $stmtImages->fetchAll(PDO::FETCH_ASSOC);

// Handle POST request for updating the product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Thai fields
  $name = $_POST['name'] ?? '';
  $details = $_POST['productdetails'] ?? '';
  
  // NEW: English fields
  $name_en = $_POST['name_en'] ?? '';
  $details_en = $_POST['productdetails_en'] ?? '';

  // General fields
  $price = $_POST['price'] ?? 0;
  $category = $_POST['category'] ?? '';
  $status = isset($_POST['status']) ? 1 : 0;

  $main_image = $product['main_image']; // Keep old image by default

  // Handle new main image upload
  if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] == UPLOAD_ERR_OK) {
    $newImageName = time() . '_' . preg_replace("/[^a-zA-Z0-9.\-\_]/", "_", basename($_FILES['main_image']['name']));
    $targetPath = __DIR__ . '/../../uploads/' . $newImageName;
    if (move_uploaded_file($_FILES['main_image']['tmp_name'], $targetPath)) {
      // Delete old image if it exists and is not a placeholder
      if ($main_image && $main_image !== 'placeholder.png' && file_exists(__DIR__ . "/../../uploads/" . $main_image)) {
        unlink(__DIR__ . "/../../uploads/" . $main_image);
      }
      $main_image = $newImageName; // Update to new image name
    }
  }

  // UPDATED: SQL UPDATE statement to include English fields
  $stmt = $pdo->prepare("UPDATE products SET 
                            name=?, price=?, category=?, productdetails=?, main_image=?, status=?, 
                            name_en=?, productdetails_en=? 
                        WHERE id=?");
  
  // UPDATED: Execute array to include English variables
  $stmt->execute([
    $name, $price, $category, $details, $main_image, $status, 
    $name_en, $details_en, 
    $id
  ]);

  // Handle adding new additional images
  if (!empty($_FILES['additional_images']['name'][0])) {
    foreach ($_FILES['additional_images']['tmp_name'] as $index => $tmpName) {
      if (isset($_FILES['additional_images']['error'][$index]) && $_FILES['additional_images']['error'][$index] === UPLOAD_ERR_OK) {
        $image_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.\-\_]/", "_", basename($_FILES['additional_images']['name'][$index]));
        move_uploaded_file($tmpName, __DIR__ . '/../../uploads/' . $image_name);

        $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image) VALUES (?, ?)"); // Uses 'image' column
        $imgStmt->execute([$id, $image_name]);
      }
    }
  }

  // $_SESSION['success_message'] = "แก้ไขสินค้าเรียบร้อยแล้ว";
  header("Location: index.php"); // Redirect to product list
  exit;
}
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>แก้ไขสินค้า (Edit Product #<?= e($id) ?>)</span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <h2 style="margin: 20px 0;">แก้ไขสินค้า</h2>

  <form action="" method="POST" enctype="multipart/form-data" class="form-section">
    
    <fieldset>
        <legend>ข้อมูลภาษาไทย (Thai Content)</legend>
        <label for="name">ชื่อสินค้า (Product Name TH):</label>
        <input type="text" id="name" name="name" value="<?= e($product['name'] ?? '') ?>" required>
        
        <label for="productdetails">รายละเอียดสินค้า (Product Details TH):</label>
        <textarea name="productdetails" id="productdetails" class="rich-text"><?= e($product['productdetails'] ?? '') ?></textarea>
    </fieldset>

    <hr style="margin: 20px 0;">

    <fieldset>
        <legend>ข้อมูลภาษาอังกฤษ (English Content)</legend>
        <label for="name_en">ชื่อสินค้า (Product Name EN):</label>
        <input type="text" id="name_en" name="name_en" value="<?= e($product['name_en'] ?? '') ?>">

        <label for="productdetails_en">รายละเอียดสินค้า (Product Details EN):</label>
        <textarea name="productdetails_en" id="productdetails_en" class="rich-text"><?= e($product['productdetails_en'] ?? '') ?></textarea>
    </fieldset>

    <hr style="margin: 20px 0;">

    <fieldset>
        <legend>ข้อมูลทั่วไป (General Information)</legend>
        <label for="price">ราคา (บาท) (Price in THB):</label>
        <input type="number" step="0.01" id="price" name="price" value="<?= e($product['price'] ?? '0.00') ?>" required>

        <label for="category">หมวดหมู่ (Category):</label>
        <input type="text" id="category" name="category" value="<?= e($product['category'] ?? '') ?>">

        <label for="main_image">อัปโหลดรูปหลักใหม่ (ถ้าต้องการเปลี่ยน) (New Main Image - Optional):</label>
        <input type="file" id="main_image" name="main_image" accept="image/*" onchange="previewMainImage(event)">
        <div id="mainImagePreview">
          <?php if (!empty($product['main_image'])): ?>
            <p style="margin-top: 5px;">รูปปัจจุบัน:</p>
            <img src="../../uploads/<?= e($product['main_image']) ?>" class="preview-thumb" style="max-width:200px; border-radius:8px;">
          <?php endif; ?>
        </div>

        <label for="additionalInput" style="margin-top:20px; display:block;">เพิ่มรูปเพิ่มเติม (Add More Images):</label>
        <input type="file" id="additionalInput" name="additional_images[]" multiple accept="image/*">
        
        <label style="margin-top:20px; display:block;">รูปเพิ่มเติมที่มีอยู่ (Existing Additional Images):</label>
        <div id="existingImagesPreview" style="display: flex; flex-wrap: wrap; gap: 10px;">
          <?php foreach ($images as $img): ?>
            <div class="image-wrapper" style="position:relative;">
              <img src="../../uploads/<?= e($img['image']) ?>" class="preview-thumb" style="max-width:100px; border-radius:8px;" />
              <a href="delete_image.php?id=<?= e($img['id']) ?>&product_id=<?= e($id) ?>" class="remove-btn" onclick="return confirm('แน่ใจนะว่าจะลบรูปนี้?')">✕</a>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="form-actions" style="margin-top:20px;">
          <div class="form-checkbox">
            <input type="checkbox" name="status" id="status" <?= !empty($product['status']) ? 'checked' : '' ?>>
            <label for="status">แสดงสินค้า (Visible)</label>
          </div>
        </div>
    </fieldset>

    <div style="margin-top: 20px;">
      <button type="submit">บันทึกการแก้ไข (Save Changes)</button>
      <a href="index.php" class="back-link" style="margin-left: 10px;">← ย้อนกลับ (Back)</a>
    </div>
  </form>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/tinymce@5/tinymce.min.js"></script>
<script>
// UPDATED: TinyMCE selector to target both Thai and English textareas
tinymce.init({
  selector: 'textarea.rich-text', // Target by class
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
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.maxWidth = '200px';
      img.style.maxHeight = '200px';
      img.style.borderRadius = '8px';
      preview.appendChild(img);
    }
    reader.readAsDataURL(file);
  } else if (<?= json_encode(!empty($product['main_image'])) ?>) {
    // Restore original if selection is cleared
    preview.innerHTML = `<img src="../../uploads/<?= e($product['main_image']) ?>" class="preview-thumb" style="max-width:200px; border-radius:8px;">`;
  }
}
</script>