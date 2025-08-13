<?php
// ★★★ ไฟล์นี้ต้องไม่มีช่องว่างก่อนบรรทัดนี้เด็ดขาด ★★★

// สร้างรหัสลับ เพื่อให้ไฟล์ลูกรู้ว่าถูกเรียกใช้อย่างถูกต้อง
define('IN_APP', true);

// 1. เรียกใช้ไฟล์จำเป็นทั้งหมด และเช็คการล็อกอิน
session_start();
// ★★★ แก้ไข Path ตรงนี้ ★★★
require_once __DIR__ . '/../../includes/db.php'; 
require_once __DIR__ . '/../../includes/auth.php';
require_login();

// 2. เรียกไฟล์ Logic มาประมวลผลข้อมูล
require_once __DIR__ . '/dashboard_data.php';

// 3. เรียกไฟล์ View มาแสดงผล
require_once __DIR__ . '/dashboard_view.php';

?>