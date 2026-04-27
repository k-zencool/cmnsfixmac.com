<?php
session_start();

// ดึงไฟล์เชื่อมต่อฐานข้อมูล
require_once '../../includes/db.php';

// 1. เช็คว่าล็อกอินอยู่ไหม ถ้าไม่ก็เตะกลับไปหน้า Login
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. ดักว่าส่งข้อมูลมาแบบ POST จริงๆ ไม่ใช่พิมพ์ URL เข้ามามั่วๆ
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $admin_id = $_SESSION['admin_id'];

    // 3. รับค่าจากฟอร์ม และใช้ trim() ตัดช่องว่างหัวท้ายทิ้ง (เผื่อย้อนแย้งพิมพ์เว้นวรรคมา)
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');

    // 4. ดักเช็คค่าว่าง (ชื่อที่แสดงบังคับใส่)
    if (empty($full_name)) {
        $_SESSION['error'] = "มึงต้องใส่ชื่อที่แสดงด้วยดิ จะปล่อยว่างได้ไง!";
        header("Location: index.php");
        exit();
    }

    try {
        // 5. เตรียมคำสั่ง SQL อัปเดตข้อมูล
        $sql = "UPDATE admin_users 
                SET full_name = :full_name, 
                    phone = :phone, 
                    email = :email, 
                    bio = :bio 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        
        // 6. ยัดค่าลงไปและรันคำสั่ง
        $stmt->execute([
            ':full_name' => $full_name,
            ':phone'     => $phone,
            ':email'     => $email,
            ':bio'       => $bio,
            ':id'        => $admin_id
        ]);

        // 7. ตั้งค่า Session ให้ SweetAlert2 แจ้งเตือนสีเขียวหล่อๆ
        $_SESSION['success'] = "อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้วเพื่อน!";
        
    } catch (PDOException $e) {
        // เผื่อเซิร์ฟพังหรือ DB เน่า จะได้รู้ว่า Error อะไร
        $_SESSION['error'] = "อัปเดตไม่ผ่านว่ะ: " . $e->getMessage();
    }

    // 8. ทำเสร็จปุ๊บ เด้งกลับไปหน้าโปรไฟล์
    header("Location: index.php");
    exit();

} else {
    // ถ้าไม่ได้ส่งฟอร์มมา แต่แอบเปิดไฟล์นี้ตรงๆ ให้เด้งกลับไป
    header("Location: index.php");
    exit();
}