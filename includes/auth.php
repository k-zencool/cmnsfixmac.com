<?php
// ================== SESSION ==================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ========== ของเดิม (คงไว้ให้เข้ากันได้) ==========
 */
function is_logged_in(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /admin/login.php'); // แก้ path ให้ตรงระบบมึง
        exit;
    }
}

/**
 * require_role แบบเดิม: ใช้ตามหน้าที่ต้องการแค่เช็ค "ยศ"
 * ตัวนี้ยังใช้ได้เหมือนเดิม
 */
function require_role(array $allowed_roles = []): void
{
    require_login();
    $role = $_SESSION['admin_role'] ?? '';
    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        die("403 Forbidden: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
    }
}

/**
 * ========== ของใหม่ (ละเอียดเป็น permission) ==========
 * ใช้ค่า role เดิมจาก $_SESSION['admin_role']
 * ถ้าภายหลังอยากย้ายไป user_role ก็ได้ เรารองรับ fallback ให้แล้ว
 */

// ช่วยให้รองรับ PHP 7
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        if ($needle === '') return true;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

function current_role(): string
{
    // ใช้ admin_role เป็นหลัก, รองรับ user_role เผื่ออนาคต
    return (string)($_SESSION['admin_role'] ?? $_SESSION['user_role'] ?? '');
}

/**
 * เมทริกซ์ Permission: ปรับตามงานจริง
 * คีย์ = role, ค่า = รายการ permission (รองรับ wildcard)
 *
 * คีย์มาตรฐานที่พูดคุยกัน:
 *  - parts.new.*      (แท็บมือ 1)
 *  - parts.used.*     (แท็บมือ 2)
 *  - parts.donor.*    (เครื่องซาก)
 *  - parts.history.*  (ประวัติ)
 * Action ย่อยที่ใช้บ่อย: view / create / update / restock / consume / split
 */
function permission_matrix(): array
{
    return [
        'super_admin' => ['*'],
        'manager'     => ['parts.*'],
        'admin'       => [
            'parts.new.view',
            'parts.new.consume',
            'parts.new.restock',
            'parts.new.create',
            'parts.new.update',
            'parts.used.view',
            'parts.used.create',
            'parts.used.update',
            'parts.used.consume',
            'parts.used.delete',
            'parts.donor.view',
            'parts.donor.create',
            'parts.donor.update',
            'parts.donor.split',
            'parts.donor.delete',
            'parts.history.view',
        ],
        'staff'       => [
            // ดูทุกแท็บ
            'parts.new.view',
            'parts.used.view',
            'parts.donor.view',
            'parts.history.view',
            // เบิกได้หมด (มือ 1 และมือ 2)
            'parts.new.consume',
            'parts.used.consume',
            'parts.donor.split',
            // ไม่ให้ create/update/delete/restock/split ใดๆ
        ],
        'viewer'      => [
            'parts.new.view',
            'parts.used.view',
            'parts.donor.view',
            'parts.history.view'
        ],
    ];
}


/** เปรียบเทียบ permission ที่มี กับที่ต้องการ (รองรับ wildcard) */
function perm_match(string $needed, string $have): bool
{
    if ($have === '*') return true;
    if ($have === $needed) return true;
    if (str_ends_with($have, '.*')) {
        $prefix = substr($have, 0, -2); // ตัด .*
        return str_starts_with($needed, $prefix . '.') || $needed === $prefix;
    }
    return false;
}

/** ใช้ฝั่ง UI: โชว์/ซ่อนปุ่มตามสิทธิ์ */
function can(string $perm): bool
{
    $role = current_role();
    $grants = permission_matrix()[$role] ?? [];
    foreach ($grants as $g) {
        if (perm_match($perm, $g)) return true;
    }
    return false;
}

/** ใช้ฝั่งเซิร์ฟเวอร์: บังคับสิทธิ์จริง กันยิง URL ตรง */
function require_perms(array $perms): void
{
    require_login();
    foreach ($perms as $p) {
        if (!can($p)) {
            // โยนกลับหน้าก่อน พร้อมข้อความ
            $to = $_SERVER['HTTP_REFERER'] ?? '/admin/';
            $q  = (strpos($to, '?') !== false ? '&' : '?');
            header("Location: {$to}{$q}err=" . rawurlencode("ไม่มีสิทธิ์ ($p)"));
            exit;
        }
    }
}
