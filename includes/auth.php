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
 * ========== เมทริกซ์ Permission (ออกแบบใหม่ 2026-07) ==========
 * โมเดล: "tier + gate จุดอ่อนไหว" — การ "ดู" ทำได้ทุกยศที่ล็อกอิน
 * (ไม่ต้องมี perm) ส่วนที่ต้อง gate จริงคือ "การเขียน/การเงิน" เท่านั้น
 *
 * ยศ (map กับงานจริงในร้าน):
 *   super_admin = เจ้าของ   → ทุกอย่าง
 *   manager     = ผู้จัดการ → operation + การเงิน + อนุมัติ/ย้อน (ไม่แตะ users/settings)
 *   admin       = หน้าร้าน  → รับงาน + shop/บทความ/ประกัน + ขาย
 *   staff       = ช่าง       → งานซ่อม + เบิกอะไหล่
 *   viewer      = บัญชี      → อ่านอย่างเดียว + รายงานการเงิน
 *
 * Capability keys ที่ enforce จริง:
 *   jobs.write      เปิด/แก้/ปิดงานซ่อม
 *   content.write   บทความ / ประกัน / จัดการเนื้อหา shop
 *   parts.consume   เบิกอะไหล่ (ถูก log ในศูนย์ควบคุม)
 *   parts.manage    เพิ่ม/แก้/ลบ/เติมสต็อก + แยกเครื่องซาก
 *   pricing.write   ตั้ง/แก้ราคาซ่อม
 *   shop.finance    เอาขึ้นขาย / ปิดการขาย / mark sold / revert
 *   manager.center  ศูนย์ควบคุมผู้จัดการ (ดู + ย้อนรายการ)
 *   reports.view    ดูรายงานการเงิน
 *   users.manage    จัดการผู้ใช้/ยศ
 *   settings.manage ตั้งค่าระบบ + เชื่อมต่อแชท (integrations)
 */
function permission_matrix(): array
{
    return [
        'super_admin' => ['*'],
        'manager'     => [
            'jobs.write', 'content.write',
            'parts.consume', 'parts.manage',
            'pricing.write', 'shop.finance',
            'manager.center', 'reports.view',
        ],
        'admin'       => [
            'jobs.write', 'content.write',
            'parts.consume', 'shop.finance',
        ],
        'staff'       => [
            'jobs.write', 'parts.consume',
        ],
        'viewer'      => [
            'reports.view',
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
            $to       = $_SERVER['HTTP_REFERER'] ?? '';
            $cur_path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
            $to_path  = $to !== '' ? parse_url($to, PHP_URL_PATH) : '';
            // กัน redirect loop: อย่าเด้งกลับหน้าเดิม หรือหน้า redirector (view_as.php)
            if ($to === '' || strpos($to, 'view_as.php') !== false || $to_path === $cur_path) {
                $to = '/admin/dashboard/';
            }
            $q = (strpos($to, '?') !== false ? '&' : '?');
            header("Location: {$to}{$q}err=" . rawurlencode("ไม่มีสิทธิ์ ($p)"));
            exit;
        }
    }
}

/**
 * เวอร์ชันสำหรับ AJAX/process endpoint ที่ต้องคืน JSON
 * (require_perms เดิม redirect ซึ่งพัง contract ของ JSON)
 * ต้องมี admin_id ก่อน แล้วเช็คสิทธิ์ — ไม่ผ่านคืน 403 JSON แล้ว exit
 */
function require_perms_json(array $perms): void
{
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    if (!is_logged_in()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'กรุณาเข้าสู่ระบบ'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    foreach ($perms as $p) {
        if (!can($p)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => "ไม่มีสิทธิ์ทำรายการนี้ ($p)"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

/**
 * ========== View-As: super_admin ดูมุมมองยศอื่น ==========
 * ตอนสลับ: เก็บ role จริงไว้ใน $_SESSION['real_admin_role'] แล้วเปลี่ยน
 * $_SESSION['admin_role'] เป็น role เป้าหมาย → ทุก can()/current_role()/
 * sidebar ที่อ่าน admin_role เปลี่ยนตามอัตโนมัติ ไม่ต้องแก้รายหน้า.
 * ความปลอดภัย: ค่าจริงอยู่ที่ real_admin_role เสมอ, ตั้ง/ปลดได้เฉพาะ super_admin จริง.
 */
function real_role(): string
{
    return (string)($_SESSION['real_admin_role'] ?? $_SESSION['admin_role'] ?? $_SESSION['user_role'] ?? '');
}

/** เช็คยศจริง (ไม่ใช่มุมมองที่สวมอยู่) */
function is_super_admin(): bool
{
    return real_role() === 'super_admin';
}

/** กำลังสวมมุมมองยศอื่นอยู่ไหม */
function is_viewing_as(): bool
{
    return is_super_admin() && !empty($_SESSION['view_as']);
}

/** ยศที่กำลังสวมอยู่ ('' = ไม่ได้สวม) */
function viewing_as_role(): string
{
    return is_viewing_as() ? (string)$_SESSION['view_as'] : '';
}

/** รายการยศที่ super_admin สลับไปดูได้ (super_admin = มุมมองเดิม จึงไม่อยู่ในลิสต์) */
function view_as_roles(): array
{
    return ['manager', 'admin', 'staff', 'viewer'];
}

/** ป้ายชื่อยศแบบอ่านง่าย */
function role_label(string $role): string
{
    $map = [
        'super_admin' => 'เจ้าของ (Owner)',
        'manager'     => 'ผู้จัดการ (Manager)',
        'admin'       => 'หน้าร้าน (Front Desk)',
        'staff'       => 'ช่าง (Technician)',
        'viewer'      => 'บัญชี (Accountant)',
    ];
    return $map[$role] ?? $role;
}
