<?php
session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_login(); 

// --- สแกนหาไฟล์ Background ---
$background_dir = __DIR__ . '/image/';
$background_files = [];
if (is_dir($background_dir)) {
    $files = scandir($background_dir);
    foreach ($files as $file) {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($extension == 'png') {
            $background_files[] = $file;
        }
    }
}
?>

<?php include '../../templates/header_admin.php'; ?>
<?php include '../../templates/sidebar_admin.php'; ?>

<main class="main" id="main-content">
  <div class="topbar">
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
    <span>สร้างโปรเจกต์รูป Before-After</span>
    <a href="index.php" class="view-site">← กลับคลังผลงาน</a>
  </div>

  <div style="margin: 20px;">

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['last_combined_image'])): ?>
        <div class="card mb-4">
            <div class="card-header fw-bold">รูปภาพล่าสุดที่สร้างเสร็จ</div>
            <div class="card-body text-center">
                <?php
                    $preview_image_path = $_SESSION['last_combined_image'];
                    unset($_SESSION['last_combined_image']);
                ?>
                <img src="/<?php echo htmlspecialchars($preview_image_path); ?>?t=<?php echo time(); ?>" class="img-fluid rounded" alt="Generated Image Preview" style="max-height: 500px; border: 1px solid #ddd;">
                <p class="mt-2"><a href="/<?php echo htmlspecialchars($preview_image_path); ?>" target="_blank">เปิดรูปในแท็บใหม่</a></p>
            </div>
        </div>
        <hr>
    <?php endif; ?>

    <div class="form-section">
      <h2 style="margin-bottom: 20px;">สร้างโปรเจกต์รูปใหม่</h2>
      <form action="process_upload.php" method="post" enctype="multipart/form-data" id="project-form">
        
        <div class="mb-3">
            <label for="background_choice" class="form-label fw-bold">เลือก Background (.png เท่านั้น)</label>
            <select class="form-select" name="background_choice" id="background_choice" required>
                <?php if (empty($background_files)): ?>
                    <option value="" disabled>ไม่พบไฟล์ Background (.png) ในโฟลเดอร์ image/</option>
                <?php else: ?>
                    <?php foreach ($background_files as $bg_file): ?>
                        <option value="<?php echo htmlspecialchars($bg_file); ?>"><?php echo htmlspecialchars($bg_file); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="job_number" class="form-label">เลขที่งานซ่อม (ของร้าน)</label>
                <input type="text" class="form-control" id="job_number" name="job_number" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="device_model" class="form-label">ชื่อรุ่นอุปกรณ์</label>
                <input type="text" class="form-control" id="device_model" name="device_model" required>
            </div>
        </div>
        <div class="mb-3">
            <label for="job_description" class="form-label">รายละเอียดโปรเจกต์</label>
            <textarea class="form-control" id="job_description" name="job_description" rows="3"></textarea>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="before_image" class="form-label fw-bold">รูปภาพ Before</label>
                <input type="file" class="form-control" id="before_image" name="before_image" accept="image/*" onchange="previewImage(event, 'before-preview')" required>
                <div id="before-preview" class="mt-2"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="after_image" class="form-label fw-bold">รูปภาพ After</label>
                <input type="file" class="form-control" id="after_image" name="after_image" accept="image/*" onchange="previewImage(event, 'after-preview')" required>
                <div id="after-preview" class="mt-2"></div>
            </div>
        </div>
        <div style="margin-top: 20px;">
          <button type="submit" class="btn btn-primary" id="submit-button">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
            <span class="button-text">สร้างโปรเจกต์ใหม่</span>
          </button>
        </div>
      </form>
    </div>

  </div>
</main>

<script>
function previewImage(event, previewId) {
  const previewContainer = document.getElementById(previewId);
  previewContainer.innerHTML = ''; 

  const input = event.target;
  if (input.files && input.files[0]) {
    const reader = new FileReader();

    reader.onload = function(e) {
      const img = document.createElement('img');
      img.src = e.target.result;
      img.style.maxWidth = '200px';
      img.style.maxHeight = '200px';
      img.style.marginTop = '10px';
      img.classList.add('img-thumbnail');
      previewContainer.appendChild(img);
    }

    reader.readAsDataURL(input.files[0]);
  }
}

document.getElementById('project-form').addEventListener('submit', function() {
  const submitButton = document.getElementById('submit-button');
  const buttonText = submitButton.querySelector('.button-text');
  const spinner = submitButton.querySelector('.spinner-border');

  submitButton.disabled = true;
  spinner.style.display = 'inline-block';
  buttonText.textContent = 'กำลังประมวลผล...';
});
</script>

<?php include '../../templates/footer_admin.php'; ?>