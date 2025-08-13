<?php
session_start();
require_once __DIR__ . '/../../includes/db.php'; 
require_once __DIR__ . '/../../includes/auth.php'; 
require_login(); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
  $admin_id = $_SESSION['admin_id'] ?? null;

  if (!$admin_id) {
    die('Error: You must be logged in to perform this action.'); 
  }

  // Thai fields
  $title = $_POST['title'] ?? '';
  $model = $_POST['model'] ?? '';
  $issue = $_POST['issue'] ?? '';
  $fix_detail = $_POST['fix_detail'] ?? '';
  $category = $_POST['category'] ?? '';

  // NEW: English fields
  $title_en = $_POST['title_en'] ?? '';
  $issue_en = $_POST['issue_en'] ?? '';
  $fix_detail_en = $_POST['fix_detail_en'] ?? '';

  $image = ''; 
  // ## FIX: VALIDATE FOR WEBP ##
  if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
    $fileInfo = pathinfo($_FILES['image']['name']);
    $fileExtension = strtolower($fileInfo['extension']);

    if ($fileExtension === 'webp') {
        $filename = time() . '-' . uniqid() . '.webp';
        $path = '../../uploads/' . $filename;
        
        $uploadDir = dirname($path);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        if (move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
          $image = $filename;
        }
    } else {
        die('Error: รูปภาพต้องเป็นไฟล์ .webp เท่านั้น');
    }
  }

  $stmt = $pdo->prepare("INSERT INTO repairs (admin_id, title, model, issue, fix_detail, image, category, created_at, title_en, issue_en, fix_detail_en) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
  
  if ($stmt->execute([$admin_id, $title, $model, $issue, $fix_detail, $image, $category, $title_en, $issue_en, $fix_detail_en])) {
    $_SESSION['success_message'] = "ผลงานซ่อมถูกเพิ่มเรียบร้อยแล้ว";
    header("Location: index.php");
    exit;
  } else {
    // echo "Error: " . $stmt->errorInfo()[2];
  }
}
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <span>เพิ่มผลงานใหม่</span>
    <a href="index.php" class="view-site">← กลับหน้ารายการ</a>
  </div>

  <h2 style="margin: 20px 0;">เพิ่มข้อมูลผลงาน (Add New Repair Work)</h2>
  <form action="" method="POST" enctype="multipart/form-data" class="form-section">
    
    <fieldset>
        <legend>ข้อมูลภาษาไทย (Thai Content)</legend>
        <label for="title">ชื่อผลงาน (Title TH):</label>
        <input type="text" name="title" id="title" required>

        <label for="issue">ปัญหา (Issue TH):</label>
        <textarea name="issue" id="issue" rows="3" required></textarea>

        <label for="fix_detail">รายละเอียดการซ่อม (Repair Details TH):</label>
        <textarea name="fix_detail" id="fix_detail" rows="5" required></textarea>
    </fieldset>

    <hr style="margin: 20px 0;">

    <fieldset>
        <legend>ข้อมูลภาษาอังกฤษ (English Content)</legend>
        <label for="title_en">ชื่อผลงาน (Title EN):</label>
        <input type="text" name="title_en" id="title_en"> <label for="issue_en">ปัญหา (Issue EN):</label>
        <textarea name="issue_en" id="issue_en" rows="3"></textarea>

        <label for="fix_detail_en">รายละเอียดการซ่อม (Repair Details EN):</label>
        <textarea name="fix_detail_en" id="fix_detail_en" rows="5"></textarea>
    </fieldset>
    
    <hr style="margin: 20px 0;">

    <fieldset>
        <legend>ข้อมูลทั่วไป (General Information)</legend>
        <label for="model">รุ่น (Model):</label>
        <input type="text" name="model" id="model" required>

        <label for="category">หมวดหมู่ (Category):</label>
        <input type="text" name="category" id="category"> 
        <label for="image">อัปโหลดรูปภาพหลัก (Main Image):</label>
        <input type="file" name="image" id="image" accept="image/webp" onchange="previewImage(event)">
        <div id="imagePreview" style="margin-top:10px;"></div>
    </fieldset>

    <div style="margin-top: 20px;">
      <button type="submit">บันทึกข้อมูล (Save Data)</button>
      <a href="index.php" class="back-link" style="margin-left: 10px;">← ย้อนกลับ (Back)</a>
    </div>
  </form>
</main>

<?php include '../../templates/footer_admin.php'; ?>

<script>
function previewImage(event) {
  const input = event.target;
  const previewContainer = document.getElementById('imagePreview');
  previewContainer.innerHTML = ''; 

  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.maxWidth = '200px'; 
      img.style.maxHeight = '200px';
      img.style.borderRadius = '8px';
      img.style.marginTop = '10px';
      previewContainer.appendChild(img);
    }
    reader.readAsDataURL(input.files[0]);
  }
}
</script>