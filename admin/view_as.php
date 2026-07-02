<?php
/**
 * view_as.php — สลับมุมมองยศ (เฉพาะ super_admin จริง).
 *
 * ?role=staff|admin|manager|viewer  → สวมมุมมองยศนั้น
 * ?role=exit (หรือ super_admin/ว่าง) → กลับมุมมองเดิม
 *
 * เก็บ role จริงไว้ที่ $_SESSION['real_admin_role'] เสมอ จึงปลด/สลับกลับได้ตลอด.
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();

$back = $_SERVER['HTTP_REFERER'] ?? '/admin/dashboard/';

// ตั้ง/ปลดได้เฉพาะ super_admin จริงเท่านั้น
if (!is_super_admin()) {
    header("Location: $back");
    exit;
}

$role = $_REQUEST['role'] ?? '';

if ($role === '' || $role === 'exit' || $role === 'super_admin') {
    // กลับมุมมองเดิม
    if (isset($_SESSION['real_admin_role'])) {
        $_SESSION['admin_role'] = $_SESSION['real_admin_role'];
        unset($_SESSION['real_admin_role']);
    }
    unset($_SESSION['view_as']);
} elseif (in_array($role, view_as_roles(), true)) {
    // สวมมุมมองยศใหม่ — เก็บ role จริงไว้ครั้งแรก
    if (!isset($_SESSION['real_admin_role'])) {
        $_SESSION['real_admin_role'] = $_SESSION['admin_role'] ?? 'super_admin';
    }
    $_SESSION['admin_role'] = $role;
    $_SESSION['view_as']    = $role;
}

header("Location: $back");
exit;
