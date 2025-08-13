<?php
// ทำให้แน่ใจว่า session ถูกสตาร์ทเสมอ เมื่อไฟล์นี้ถูกเรียกใช้
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ฟังก์ชันที่ 1: "is_logged_in" (เครื่องมือเช็คบัตร)
 * แค่ถามเฉยๆ ว่า "ล็อกอินอยู่ปะ?" แล้วคืนค่า true หรือ false
 * @return bool
 */
function is_logged_in()
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * ฟังก์ชันที่ 2: "require_login" (ยามกันคนนอก)
 * ถ้ายังไม่ล็อกอิน ยามจะโยนออกไปเลย
 */
function require_login()
{
    if (!is_logged_in()) {
        // !!! แก้ Path ไปหน้า login ของมึงให้ถูก !!!
        header('Location: /admin/login.php'); 
        exit;
    }
}

/**
 * ฟังก์ชันที่ 3: "require_role" (ยามสแกนยศ)
 * ใช้สำหรับหน้าที่ต้องการสิทธิ์สูงๆ
 * @param array $allowed_roles - รายชื่อ Role ที่อนุญาตให้เข้าได้
 */
function require_role($allowed_roles = [])
{
    require_login(); // เช็คก่อนว่ามีบัตรมั้ย

    if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], $allowed_roles)) {
        // ถ้ายศไม่ถึง ยามจะบล็อกไว้
        http_response_code(403);
        die("403 Forbidden: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
        exit;
    }
}