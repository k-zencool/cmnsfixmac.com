<?php
/**
 * admin/push_subscribe.php — บันทึก/ถอน Web Push subscription ของเครื่องนี้ (admin ทุก role)
 * POST JSON: {action: "subscribe"|"unsubscribe", subscription: {endpoint, keys:{p256dh, auth}}}
 */
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/push_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'err' => 'not logged in']);
    exit;
}

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? '';
$sub    = $in['subscription'] ?? [];
$ep     = (string)($sub['endpoint'] ?? '');

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($in['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'err' => 'CSRF token ไม่ถูกต้อง']);
    exit;
}

try {
    if ($action === 'subscribe') {
        $p256dh = (string)($sub['keys']['p256dh'] ?? '');
        $auth   = (string)($sub['keys']['auth'] ?? '');
        // กัน payload มั่ว: endpoint ต้องเป็น https + คีย์ decode แล้วได้ขนาดมาตรฐาน
        if (strpos($ep, 'https://') !== 0 || strlen(push_b64url_decode($p256dh)) !== 65 || strlen(push_b64url_decode($auth)) !== 16) {
            throw new Exception('subscription ไม่ถูกต้อง');
        }
        $pdo->prepare("
            INSERT INTO push_subscriptions (admin_id, endpoint, endpoint_hash, p256dh, auth, ua)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE admin_id = VALUES(admin_id), p256dh = VALUES(p256dh), auth = VALUES(auth), ua = VALUES(ua)
        ")->execute([
            (int)$_SESSION['admin_id'], $ep, hash('sha256', $ep), $p256dh, $auth,
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
        ]);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'unsubscribe') {
        if ($ep === '') throw new Exception('ไม่มี endpoint');
        $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND admin_id = ?")
            ->execute([hash('sha256', $ep), (int)$_SESSION['admin_id']]);
        echo json_encode(['ok' => true]);
    } else {
        throw new Exception('action ไม่ถูกต้อง');
    }
} catch (Throwable $e) {
    http_response_code(422);
    $msg = (strpos($e->getMessage(), 'push_subscriptions') !== false)
         ? 'ยังไม่ได้รัน migration_push_subscriptions.sql บนฐานข้อมูลนี้'
         : $e->getMessage();
    echo json_encode(['ok' => false, 'err' => $msg]);
}
