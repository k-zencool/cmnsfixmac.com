<?php
session_start();
session_destroy();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>กำลังออกจากระบบ...</title>
  <link rel="stylesheet" href="../assets/css/dashboard-style.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
  <link rel="shortcut icon" href="https://cmnsfixmac.com/assets/img/favicon1.png" />

  <style>
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background: #f6f7fb;
      margin: 0;
      font-family: 'Sarabun', sans-serif;
      overflow: hidden;
    }
    .logout-container {
      text-align: center;
      background: #ffffff;
      padding: 40px 30px;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
      animation: fadeInUp 1s ease;
      width: 100%;
      max-width: 400px;
    }
    .logout-container .icon {
      font-size: 60px;
      color: #2563eb;
      animation: bounce 1s infinite alternate;
    }
    .logout-container h1 {
      margin-top: 20px;
      font-size: 24px;
      color: #333;
    }
    .logout-container p {
      margin-top: 10px;
      font-size: 16px;
      color: #6b7280;
    }
    .progress-bar {
      width: 100%;
      background: #e5e7eb;
      height: 10px;
      border-radius: 6px;
      margin-top: 20px;
      overflow: hidden;
    }
    .progress-bar-fill {
      height: 100%;
      width: 0%;
      background: #2563eb;
      border-radius: 6px;
      transition: width 2.5s linear;
    }

    @keyframes fadeInUp {
      0% { opacity: 0; transform: translateY(30px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    @keyframes bounce {
      0% { transform: translateY(0); }
      100% { transform: translateY(-10px); }
    }
  </style>
  <script>
    window.onload = function() {
      const progress = document.querySelector('.progress-bar-fill');
      setTimeout(() => {
        progress.style.width = '100%';
      }, 100); // start after short delay

      setTimeout(() => {
        window.location.href = 'login.php'; // หรือเปลี่ยนเป็น login.php ตามระบบ
      }, 2600);
    }
  </script>
</head>
<body>

<div class="logout-container">
  <div class="icon material-symbols-rounded">logout</div>
  <h1>กำลังออกจากระบบ...</h1>
  <p>โปรดรอสักครู่ กำลังพากลับไปยังหน้าเข้าสู่ระบบ</p>

  <div class="progress-bar">
    <div class="progress-bar-fill"></div>
  </div>
</div>

</body>
</html>
