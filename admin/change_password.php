<?php
session_start();
include_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/auth.php';

// ดึงข้อมูลแอดมินปัจจุบัน
$username = $_SESSION['admin_username'] ?? '';

// เมื่อมีการ submit ฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ดึงข้อมูลผู้ใช้จากฐานข้อมูล
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($current_password, $admin['password'])) {
        if ($new_password === $confirm_password) {
            $hashedNewPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedNewPassword, $admin['id']]);
            $success = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว";
        } else {
            $error = "ยืนยันรหัสผ่านใหม่ไม่ตรงกัน";
        }
    } else {
        $error = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
    }
}

include '../templates/header_admin.php';
include '../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>เปลี่ยนรหัสผ่าน</span>
    <a href="../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="form-section" style="max-width: 100%; padding: 40px;">
    <form action="" method="POST">
      <h2 style="font-size: 24px; margin-bottom: 20px;">เปลี่ยนรหัสผ่าน</h2>

      <?php if (isset($success)): ?>
        <div class="message success" style="color:#22c55e; margin-bottom:15px; text-align:center;"><?= htmlspecialchars($success) ?></div>
      <?php elseif (isset($error)): ?>
        <div class="message error" style="color:#ef4444; margin-bottom:15px; text-align:center;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <label for="current_password">รหัสผ่านปัจจุบัน:</label>
      <input type="password" name="current_password" id="current_password" required>

      <label for="new_password">รหัสผ่านใหม่:</label>
      <input type="password" name="new_password" id="new_password" required>

      <label for="confirm_password">ยืนยันรหัสผ่านใหม่:</label>
      <input type="password" name="confirm_password" id="confirm_password" required>

      <button type="submit">บันทึกการเปลี่ยนแปลง</button>
    </form>
  </div>
</main>

<?php include '../templates/footer_admin.php'; ?>
