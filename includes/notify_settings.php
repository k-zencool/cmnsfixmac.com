<?php
/**
 * includes/notify_settings.php
 * kv loader สำหรับ setting การแจ้งเตือน (ตาราง notification_settings)
 * standalone + function_exists guards (include ซ้ำได้ เหมือน warranty_lib.php)
 * ทุกฟังก์ชัน fail-safe: ถ้า table ยังไม่ migrate → คืน default เงียบๆ ไม่ throw
 */

if (!function_exists('notif_all')) {
    /** โหลดทั้ง table ครั้งเดียว cache ไว้ใน static */
    function notif_all(PDO $pdo): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = [];
        try {
            $rows = $pdo->query("SELECT setting_key, setting_value FROM notification_settings")
                        ->fetchAll(PDO::FETCH_KEY_PAIR);
            if (is_array($rows)) $cache = $rows;
        } catch (Throwable $e) {
            // table ยังไม่ migrate — คืน array ว่าง ให้ caller ใช้ default
        }
        return $cache;
    }
}

if (!function_exists('notif_all_fresh')) {
    /** โหลดสดจาก DB ไม่ผ่าน cache (ใช้ตอนแสดงผลหลังบันทึก) */
    function notif_all_fresh(PDO $pdo): array {
        try {
            $rows = $pdo->query("SELECT setting_key, setting_value FROM notification_settings")
                        ->fetchAll(PDO::FETCH_KEY_PAIR);
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('notif_get')) {
    function notif_get(PDO $pdo, string $key, ?string $default = null): ?string {
        $all = notif_all($pdo);
        return array_key_exists($key, $all) && $all[$key] !== '' ? (string)$all[$key] : $default;
    }
}

if (!function_exists('notif_bool')) {
    /** flag เปิด/ปิด — default true (ถ้ายังไม่ตั้งค่า/ยังไม่ migrate ให้ถือว่าเปิด เพื่อไม่เปลี่ยนพฤติกรรมเดิม) */
    function notif_bool(PDO $pdo, string $key, bool $default = true): bool {
        $all = notif_all($pdo);
        if (!array_key_exists($key, $all)) return $default;
        return $all[$key] === '1' || $all[$key] === 1 || $all[$key] === true;
    }
}

if (!function_exists('notif_set')) {
    function notif_set(PDO $pdo, string $key, string $val): void {
        try {
            $pdo->prepare("
                INSERT INTO notification_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ")->execute([$key, $val]);
        } catch (Throwable $e) {
            // table ยังไม่ migrate — ปล่อยเงียบ
        }
    }
}
