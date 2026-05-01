<?php
// admin/templates/header_admin.php

// 1. เริ่ม Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --------------------------------------------------------
// 🌍 ROBUST PATH CONFIGURATION (แบบระบุพิกัดเต็ม)
// --------------------------------------------------------
// วิธีนี้จะสร้าง URL เต็มๆ เช่น http://localhost/admin/templates/assets/
// ทำให้เรียกไฟล์เจอแน่นอน ไม่ว่าจะเข้าจากหน้าไหน
// --------------------------------------------------------

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

// ⚠️ สำคัญ: ถ้าโปรเจกต์อยู่ในโฟลเดอร์ย่อย เช่น localhost/fixmac/ ให้แก้ตรงนี้เป็น '/fixmac'
// ถ้าใช้ localhost:8000 หรือเป็นเว็บจริงแล้ว ให้ปล่อยว่างไว้ ''
$project_root = ''; 

// สร้าง Base URL สำหรับ Assets
$assets_base = $protocol . "://" . $host . $project_root . "/admin/templates/assets/";

// ชื่อหน้า (Title)
$pageTitle = isset($pageTitle) ? $pageTitle : 'Admin Panel';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    
    <title><?= htmlspecialchars($pageTitle) ?> | FixMac Admin</title>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('admin_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            const bgColor = savedTheme === 'dark' ? '#0a0a0a' : '#f1f5f9';
            document.documentElement.style.backgroundColor = bgColor;

            // apply sidebar state ก่อน browser render เพื่อกัน flash
            if (localStorage.getItem('sidebarState') === 'collapsed' && window.innerWidth > 991) {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

    <link rel="stylesheet" href="<?= $assets_base ?>css/admin.css?v=<?= time(); ?>">
    
</head>

<body>
    <div class="wrapper">
        
        <?php 
        if (file_exists(__DIR__ . '/sidebar_admin.php')) {
            include __DIR__ . '/sidebar_admin.php'; 
        } else {
            echo "<div style='color:red; padding:20px;'>⚠️ Error: sidebar_admin.php not found</div>";
        }
        ?>

        <main class="main">
            
            <?php 
            if (file_exists(__DIR__ . '/navbar_admin.php')) {
                include __DIR__ . '/navbar_admin.php'; 
            } else {
                 // Fallback กรณีลืมสร้างไฟล์ Navbar
                 echo "<div style='padding:10px; background:#f8d7da; color:#721c24;'>⚠️ Error: navbar_admin.php not found</div>";
            }
            ?>

            <div class="content-padding">