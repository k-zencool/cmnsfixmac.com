<?php
session_start();
include_once realpath(__DIR__ . '/../includes/db.php');

if (isset($_SESSION['admin_logged_in'])) {
  header("Location: dashboard/");
  exit();
}

$error = '';

// เพิ่ม sleep(1) หลอกๆ หน่อย ให้เห็นหลอดโหลดสัก 1 วิ (ถ้าเซิร์ฟเร็วมันจะแวบเดียว)
// ถ้าเอาไปใช้จริงแล้วรำคาญ ลบบรรทัด sleep(1) ทิ้งได้เลย
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['username'])) {
  sleep(1); 
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);

  try {
      $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
      $stmt->execute([$username]);
      $admin = $stmt->fetch();
      
      if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_role'] = $admin['role']; 
        $_SESSION['LAST_ACTIVE'] = time();
        header("Location: dashboard/");
        exit();
      } else {
        $error = "ข้อมูลเข้าสู่ระบบไม่ถูกต้อง";
      }
  } catch (PDOException $e) {
      $error = "System Error: " . $e->getMessage();
  }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administrator Login | CMNS FixMac</title>
  <link rel="shortcut icon" href="/assets/img/favicon1.png" />

  <!-- PWA: allow install / standalone launch straight from the login screen -->
  <link rel="manifest" href="/admin/manifest.webmanifest">
  <meta name="theme-color" content="#0a0a0a">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="CMNS Admin">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/pwa/icon-180.png">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* --- Reset & Base --- */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Prompt', sans-serif;
      background: #f3f4f6;
      height: 100vh;
      overflow: hidden;
      display: flex;
      justify-content: center;
      align-items: center;
      color: #333;
      position: relative;
    }

    /* --- Background Animation --- */
    .area {
      background: #ffffff;  
      background: linear-gradient(to top, #dfe9f3, #ffffff);
      width: 100%; height: 100vh; position: absolute; top: 0; left: 0; z-index: -1;
    }
    .circles { position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; }
    .circles li {
      position: absolute; display: block; list-style: none;
      width: 20px; height: 20px; background: rgba(44, 62, 80, 0.05); 
      animation: animate 25s linear infinite; bottom: -150px;
    }
    .circles li:nth-child(1) { left: 25%; width: 80px; height: 80px; animation-delay: 0s; }
    /* ... (ย่อส่วน animation วงกลมไว้เหมือนเดิม) ... */
    @keyframes animate {
      0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 0; }
      100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; border-radius: 50%; }
    }

    /* --- Login Box --- */
    .login-wrapper { width: 100%; max-width: 400px; padding: 20px; z-index: 10; }
    .login-box {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid #ffffff;
      border-radius: 20px;
      padding: 40px 30px;
      text-align: center;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
      position: relative;
    }
    .logo-img { width: 100px; height: auto; margin-bottom: 15px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
    h1 { font-size: 22px; font-weight: 600; color: #2c3e50; margin-bottom: 5px; text-transform: uppercase; }
    .subtitle { font-size: 13px; color: #7f8c8d; margin-bottom: 30px; }
    .error {
      background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
      padding: 10px; border-radius: 8px; font-size: 14px; margin-bottom: 20px;
    }

    /* --- Form & Inputs --- */
    form { display: flex; flex-direction: column; gap: 15px; }
    .input-group { position: relative; }

    .input-icon {
      position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
      color: #95a5a6; font-size: 16px; z-index: 2; pointer-events: none;
    }

    input {
      width: 100%; padding: 12px 40px 12px 45px;
      background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 10px;
      font-size: 15px; color: #333; font-family: 'Prompt', sans-serif;
      transition: all 0.3s; position: relative; z-index: 1;
    }
    input:focus {
      outline: none; background: #fff; border-color: #3498db;
      box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
    }

    .toggle-password {
      position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
      cursor: pointer; color: #95a5a6; font-size: 16px; z-index: 10;
      pointer-events: auto; padding: 5px;
    }
    .toggle-password:hover { color: #3498db; }

    button {
      margin-top: 10px; padding: 12px; background: #2c3e50; color: white;
      border: none; border-radius: 10px; font-size: 16px; font-weight: 500;
      cursor: pointer; font-family: 'Prompt', sans-serif; transition: all 0.3s;
      box-shadow: 0 4px 6px rgba(44, 62, 80, 0.2);
    }
    button:hover { background: #34495e; transform: translateY(-2px); }

    /* --- Loading Overlay (ส่วนที่เพิ่มใหม่) --- */
    .loading-overlay {
      position: absolute; /* หรือ fixed ถ้าอยากให้เต็มจอ Browser */
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(255, 255, 255, 0.85); /* พื้นหลังขาวจางๆ */
      backdrop-filter: blur(5px);
      z-index: 999; /* อยู่บนสุด */
      border-radius: 20px; /* มุมมนตามกล่อง */
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      
      /* ซ่อนไว้ก่อน */
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    /* คลาสนี้จะถูกใส่ด้วย JS เมื่อกดปุ่ม */
    .loading-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .loading-text {
      font-size: 16px; font-weight: 600; color: #2c3e50; margin-bottom: 15px;
      animation: pulse 1.5s infinite;
    }

    /* หลอดโหลด */
    .progress-container {
      width: 80%; height: 6px; background: #e0e0e0; border-radius: 10px; overflow: hidden;
    }
    
    .progress-bar {
      height: 100%; width: 0%;
      background: linear-gradient(90deg, #3498db, #2c3e50);
      border-radius: 10px;
      animation: loading 2s ease-in-out infinite;
    }

    @keyframes loading {
      0% { width: 0%; transform: translateX(-100%); }
      50% { width: 70%; }
      100% { width: 100%; transform: translateX(100%); }
    }

    @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }

    /* --- Footer --- */
    .contact-help { margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; font-size: 13px; color: #95a5a6; }
    .contact-links { display: flex; justify-content: center; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
    .contact-item {
      display: inline-flex; align-items: center; gap: 6px; color: #555; text-decoration: none;
      background: #f1f3f5; padding: 6px 12px; border-radius: 30px; font-size: 12px; transition: all 0.2s;
    }
    .contact-item:hover { background: #e9ecef; color: #000; transform: translateY(-1px); }
  </style>
</head>

<body>

  <div class="area"><ul class="circles"><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li></ul></div>

  <div class="login-wrapper">
    <div class="login-box">
      
      <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-text">กำลังตรวจสอบข้อมูล...</div>
        <div class="progress-container">
          <div class="progress-bar"></div>
        </div>
      </div>

      <img src="/assets/img/Logo1.png" alt="CMNS Logo" class="logo-img">
      <h1>Administrator</h1>
      <p class="subtitle">ระบบจัดการร้าน CMNS FixMac</p>

      <?php if ($error): ?>
        <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" id="loginForm">
        <div class="input-group">
          <i class="fas fa-user-shield input-icon"></i>
          <input type="text" name="username" placeholder="ชื่อผู้ใช้" required autofocus autocomplete="off">
        </div>
        
        <div class="input-group">
          <i class="fas fa-key input-icon"></i>
          <input type="password" name="password" id="passwordInput" placeholder="รหัสผ่าน" required>
          <i class="fas fa-eye toggle-password" id="togglePassword"></i>
        </div>

        <button type="submit">เข้าสู่ระบบ</button>
      </form>

      <div class="contact-help">
        <p>ติดปัญหาการเข้าใช้งาน?</p>
        <div class="contact-links">
          <a href="tel:0612955236" class="contact-item"><i class="fas fa-phone"></i> 061-295-5236</a>
          <a href="https://www.facebook.com/search/top?q=Khun%20Natt" target="_blank" class="contact-item"><i class="fab fa-facebook-messenger"></i> Khun Natt</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. จัดการเรื่องลูกตา (Toggle Password)
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('passwordInput');

        if(togglePassword && password) {
            togglePassword.addEventListener('click', function (e) {
                e.preventDefault(); // กันพลาด
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        // 2. จัดการเรื่องหลอดโหลด (Loading Overlay)
        const loginForm = document.getElementById('loginForm');
        const loadingOverlay = document.getElementById('loadingOverlay');

        if(loginForm && loadingOverlay) {
            loginForm.addEventListener('submit', function(e) {
                // ถ้า Input ยังว่างอยู่ ไม่ต้องโชว์โหลด (ให้ Browser แจ้งเตือน required ไป)
                const inputs = loginForm.querySelectorAll('input[required]');
                let isFilled = true;
                inputs.forEach(input => {
                    if (!input.value) isFilled = false;
                });

                if (isFilled) {
                    // ถ้ากรอกครบแล้ว ให้โชว์ Loading เลย
                    loadingOverlay.classList.add('active');
                    
                    // เปลี่ยนข้อความปุ่มด้วยนิดนึงให้ดูเท่
                    const btn = loginForm.querySelector('button');
                    if(btn) btn.innerText = 'กำลังเข้าสู่ระบบ...';
                }
            });
        }
    });
  </script>

</body>
</html>