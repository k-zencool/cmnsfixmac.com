<?php
// admin/user/delete.php
// เดิม hard-delete ตรงๆ — ตอนนี้เป็น 2 ขั้น: ปิดใช้งาน (soft) ก่อน แล้วค่อยลบถาวรได้เฉพาะบัญชีที่ปิดใช้งานอยู่แล้ว
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_perms_json(['users.manage']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'CSRF token ไม่ถูกต้อง']);
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบผู้ใช้งาน']);
    exit;
}
if ($id === (int)$_SESSION['admin_id']) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่สามารถทำรายการกับบัญชีตัวเองได้']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, is_active FROM admin_users WHERE id = ?");
$stmt->execute([$id]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบผู้ใช้งานนี้']);
    exit;
}

try {
    if ($action === 'deactivate') {
        $pdo->prepare("UPDATE admin_users SET is_active = 0, deleted_at = NOW() WHERE id = ?")->execute([$id]);
        // ตัด session ที่ออนไลน์อยู่ทั้งหมดของ user นี้ทันที
        $pdo->prepare("UPDATE admin_sessions SET revoked_at = NOW() WHERE admin_id = ? AND revoked_at IS NULL")->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'ปิดใช้งานบัญชีเรียบร้อยแล้ว']);

    } elseif ($action === 'activate') {
        $pdo->prepare("UPDATE admin_users SET is_active = 1, deleted_at = NULL WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'เปิดใช้งานบัญชีเรียบร้อยแล้ว']);

    } elseif ($action === 'hard_delete') {
        if ((int)$target['is_active'] === 1) {
            echo json_encode(['ok' => false, 'msg' => 'ต้องปิดใช้งานบัญชีนี้ก่อน ถึงจะลบถาวรได้']);
            exit;
        }
        $pdo->prepare("DELETE FROM admin_sessions WHERE admin_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'ลบผู้ใช้งานถาวรเรียบร้อยแล้ว']);

    } else {
        echo json_encode(['ok' => false, 'msg' => 'ไม่ทราบคำสั่ง']);
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Database Error: ' . $e->getMessage()]);
}
