<?php
session_start();
include_once realpath(__DIR__ . '/../includes/db.php');

if (isset($_SESSION['admin_logged_in'])) {
  header("Location: dashboard.php");
  exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['username'])) {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
  $stmt->execute([$username]);
  $admin = $stmt->fetch();

  if ($admin && password_verify($password, $admin['password'])) {
    session_regenerate_id(true); // ป้องกัน session hijacking
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['LAST_ACTIVE'] = time();
    header("Location: dashboard.php");
    exit();
  } else {
    $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
  }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เข้าสู่ระบบ | Admin</title>
  <link rel="stylesheet" href="../assets/css/login-style.css">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />

</head>
<body>
  <div class="login-container">
    <div class="login-box">
      <h1>เข้าสู่ระบบผู้ดูแล</h1>

      <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST">
        <input type="text" name="username" placeholder="Username" required autofocus>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">เข้าสู่ระบบ</button>
      </form>
    </div>
  </div>
</body>
</html>
