<?php
/**
 * admin/settings/gdrive_callback.php — Google OAuth redirect target
 * (this exact URL must be listed under "Authorized redirect URIs" in Google Cloud)
 */
session_start();
require_once '../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/gdrive_lib.php';

require_login();

$back = function (string $msg, string $type) {
    $_SESSION['gd_flash'] = [$msg, $type];
    header('Location: /admin/settings/gdrive.php');
    exit;
};

if (($_SESSION['admin_role'] ?? '') !== 'super_admin') $back('เชื่อม Google Drive ได้เฉพาะ super_admin', 'err');

$state = $_GET['state'] ?? '';
$want  = $_SESSION['gd_oauth_state'] ?? '';
unset($_SESSION['gd_oauth_state']);
if ($want === '' || !hash_equals($want, $state)) $back('ลิงก์เชื่อมหมดอายุหรือไม่ถูกต้อง — กดเชื่อมใหม่อีกครั้ง', 'err');

if (!empty($_GET['error'])) {
    $back($_GET['error'] === 'access_denied' ? 'ยกเลิกการเชื่อมแล้ว (ไม่ได้กดอนุญาต)' : 'Google ตอบกลับ: ' . $_GET['error'], 'err');
}

$err = gd_exchange_code($pdo, (string)($_GET['code'] ?? ''), (int)$_SESSION['admin_id']);
if ($err) $back('เชื่อมไม่สำเร็จ: ' . $err, 'err');

try {
    gd_root_folder($pdo);   // make "CMNS Job Photos" now, so the first upload is quicker
} catch (RuntimeException $e) {
    $back('เชื่อมแล้ว แต่สร้างโฟลเดอร์ไม่ได้: ' . $e->getMessage(), 'err');
}
$back('เชื่อม Google Drive เรียบร้อย', 'ok');
