<?php

/**
 * =================================================================
 * Secure Database Connection using Composer and .env
 * =================================================================
 */

// 1. โหลด Autoloader ของ Composer
//    บรรทัดนี้จำเป็นมาก มันจะทำให้โปรเจครู้จัก Library ทั้งหมดที่ติดตั้งไว้
//    ใช้ __DIR__ . '/../' เพื่อบอกว่า "จากโฟลเดอร์ปัจจุบัน (includes) ให้ถอยกลับไปหนึ่งขั้น"
//    เพื่อหาโฟลเดอร์ vendor ที่อยู่ข้างนอก
require_once __DIR__ . '/../vendor/autoload.php';

// 2. โหลดตัวแปรจากไฟล์ .env
//    สร้าง instance ของ Dotenv ให้มันไปหาไฟล์ .env ที่ root ของโปรเจค
//    (เราก็ใช้ __DIR__ . '/../' เพื่อชี้ตำแหน่งไปที่ root เหมือนเดิม)
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load(); // โหลดไฟล์ .env
    $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'])->notEmpty(); // เช็คว่ามีค่าครบ
} catch (Exception $e) {
    // ถ้าไม่มีไฟล์ .env หรือค่าไม่ครบ ให้หยุดทำงานทันที
    error_log('ENV Error: ' . $e->getMessage());
    die('เกิดข้อผิดพลาดในการตั้งค่า Environment ของโปรเจค กรุณาตรวจสอบไฟล์ .env');
}


// 3. ดึงค่าจาก Environment Variables (ที่โหลดมาจาก .env)
//    ใช้ $_ENV['KEY_NAME'] ในการดึงค่าออกมา
$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

// 4. เชื่อมต่อฐานข้อมูลด้วย PDO (เหมือนโค้ดเดิมของมึง แต่ใช้ตัวแปรที่ปลอดภัยแล้ว)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Log error ไปยัง server แต่ไม่แสดงต่อผู้ใช้
    error_log("DB Connection Error: " . $e->getMessage());
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
}

// ณ จุดนี้ มึงจะมีตัวแปร $pdo พร้อมใช้งานในไฟล์อื่นๆ ที่เรียกใช้ db.php นี้
?>