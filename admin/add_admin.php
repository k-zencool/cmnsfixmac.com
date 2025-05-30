<?php
session_start();
include_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/auth.php';

// เมื่อมีการ submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($username && $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $hashedPassword]);

        header('Location: admin_list.php');
        exit;
    }
}

include '../templates/header_admin.php';
include '../templates/sidebar_admin.php';
?>

<main class="main" id="main-content">
  <div class="topbar">
    <span>เพิ่มแอดมินใหม่</span>
    <a href="../" class="view-site" target="_blank">ดูเว็บไซต์</a>
  </div>

  <div class="form-section" style="max-width: 100%; padding: 40px;">
    <form action="" method="POST">
      <h2 style="font-size: 24px; margin-bottom: 20px;"> เพิ่มแอดมินใหม่</h2>

      <label for="username">Username:</label>
      <input type="text" name="username" id="username" required>

      <label for="password">Password:</label>
      <input type="password" name="password" id="password" required>

      <button type="submit"> เพิ่มแอดมิน</button>
      <a href="admin_list.php" class="back-link">← ย้อนกลับ</a>
    </form>
  </div>
</main>

<?php include '../templates/footer_admin.php'; ?>
