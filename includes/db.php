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

// ── PHP 7.4 polyfills (production host runs PHP 7.4; these are PHP 8 builtins) ──
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

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
// getenv() picks up the DB_HOST that docker-compose injects into the web
// container; on the real host getenv() is empty so we fall back to .env (127.0.0.1)
$host = getenv('DB_HOST') ?: $_ENV['DB_HOST'];
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