<?php
session_start();
require_once '../../includes/db.php';

// 1. เช็คว่าล็อกอินอยู่ไหม
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_id = $_SESSION['admin_id'];

    // 2. รับค่าจากฟอร์ม
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 3. ดักเช็คค่าว่าง (เผื่อมันเจาะข้าม HTML HTML5 Validation มา)
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "มึงกรอกข้อมูลให้ครบทุกช่องดิวะ!";
        header("Location: index.php");
        exit();
    }

    // 4. เช็คว่ารหัสใหม่ 2 ช่องตรงกันไหม
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "รหัสผ่านใหม่ 2 ช่องไม่ตรงกัน ตาเหล่เหรอมึง พิมพ์ดีๆ!";
        header("Location: index.php");
        exit();
    }

    // 5. เช็คความยาวรหัสผ่าน (ขั้นต่ำ 6 ตัว)
    if (strlen($new_password) < 6) {
        $_SESSION['error'] = "รหัสผ่านสั้นไปสัส! เอาให้มันเกิน 6 ตัวหน่อย จะได้เดายากๆ";
        header("Location: index.php");
        exit();
    }

    try {
        // 6. ดึงรหัสผ่านเก่าที่เข้ารหัสไว้จาก Database มาเช็ค
        $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = :id");
        $stmt->execute([':id' => $admin_id]);
        $hashed_password = $stmt->fetchColumn();

        // 7. เทียบรหัสผ่านเก่าที่พิมพ์มา กับของเดิมใน DB ว่าตรงกันไหม (ด้วยฟังก์ชัน password_verify)
        if (!password_verify($current_password, $hashed_password)) {
            $_SESSION['error'] = "รหัสผ่านปัจจุบันไม่ถูกต้อง! มึงเป็นใครเนี่ย แอบมาเปลี่ยนรหัสป่าว?";
            header("Location: index.php");
            exit();
        }

        // 8. ถ้ารหัสเก่าถูก ก็เอารหัสใหม่มาเข้ารหัส (Hash) ให้ปลอดภัย
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // 9. อัปเดตลง Database
        $updateStmt = $pdo->prepare("UPDATE admin_users SET password = :password WHERE id = :id");
        $updateStmt->execute([
            ':password' => $new_hashed_password,
            ':id'       => $admin_id
        ]);

        // สำเร็จ!
        $_SESSION['success'] = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว! อย่าลืมรหัสใหม่ล่ะสัส!";

    } catch (PDOException $e) {
        $_SESSION['error'] = "Database Error: " . $e->getMessage();
    }

    // เด้งกลับหน้าโปรไฟล์
    header("Location: index.php");
    exit();

} else {
    // แอบเข้าไฟล์นี้ตรงๆ เตะกลับ
    header("Location: index.php");
    exit();
}