<?php
// admin/logout.php

session_start();
// ล้าง Session ทั้งหมดทิ้ง (Logout จริงๆ)
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ออกจากระบบ | CMNS FixMac</title>
  <link rel="shortcut icon" href="/assets/img/favicon1.png" />
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* --- ใช้ CSS ชุดเดียวกับ Login เพื่อความต่อเนื่อง --- */
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

    /* Background Animation */
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
    .circles li:nth-child(2) { left: 10%; width: 20px; height: 20px; animation-delay: 2s; animation-duration: 12s; }
    .circles li:nth-child(3) { left: 70%; width: 20px; height: 20px; animation-delay: 4s; }
    .circles li:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 18s; }
    
    @keyframes animate {
      0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 0; }
      100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; border-radius: 50%; }
    }

    /* Box Styling */
    .logout-wrapper { width: 100%; max-width: 400px; padding: 20px; z-index: 10; }
    .logout-box {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid #ffffff;
      border-radius: 20px;
      padding: 50px 30px;
      text-align: center;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .logo-img { width: 100px; height: auto; margin-bottom: 25px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
    
    h2 {
      font-size: 20px;
      color: #2c3e50;
      margin-bottom: 10px;
      font-weight: 600;
    }

    p {
      color: #7f8c8d;
      font-size: 14px;
      margin-bottom: 30px;
    }

    /* Progress Bar Animation for Logout */
    .progress-container {
      width: 100%;
      height: 6px;
      background: #edf2f7;
      border-radius: 10px;
      overflow: hidden;
      margin-top: 10px;
    }

    .progress-bar {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, #3498db, #2c3e50);
      border-radius: 10px;
      animation: logoutProgress 2s ease-in-out forwards; /* เล่นรอบเดียวจบ */
    }

    @keyframes logoutProgress {
      0% { width: 0%; }
      100% { width: 100%; }
    }

    .check-icon {
      font-size: 50px;
      color: #27ae60;
      margin-bottom: 20px;
      display: none; /* ซ่อนไว้ก่อน เดี๋ยวโผล่มาตอนจบ */
      animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    @keyframes popIn {
      0% { transform: scale(0); opacity: 0; }
      100% { transform: scale(1); opacity: 1; }
    }

  </style>
</head>
<body>

  <div class="area"><ul class="circles"><li></li><li></li><li></li><li></li><li></li></ul></div>

  <div class="logout-wrapper">
    <div class="logout-box">
      <img src="/assets/img/Logo1.png" alt="CMNS Logo" class="logo-img">
      
      <div id="statusContent">
        <h2>ออกจากระบบเรียบร้อย</h2>
        <p>กำลังพาท่านกลับสู่หน้าเข้าสู่ระบบ...</p>
        <div class="progress-container">
          <div class="progress-bar"></div>
        </div>
      </div>

    </div>
  </div>

  <script>
    // ตั้งเวลา 2.2 วินาที แล้วเด้งกลับไปหน้า Login
    setTimeout(function() {
      window.location.href = 'login.php';
    }, 2200);
  </script>

</body>
</html>