<?php
// ⚙️ ตั้งค่าการเชื่อมต่อฐานข้อมูล (ใช้กับ MAMP)
$host = 'localhost';
$db   = 'cmnsfixmac_db';
$user = 'cmnsfixmac_db';
$pass = 'pwdFWeLg6ML8AzeK5B3J'; // ถ้าใช้ MAMP ปกติคือ root

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // 🔐 Log error ไปยัง server แต่ไม่แสดงต่อผู้ใช้
    error_log("DB Connection Error: " . $e->getMessage());
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล"); // ไม่แสดงรายละเอียดจริง
}
?>
