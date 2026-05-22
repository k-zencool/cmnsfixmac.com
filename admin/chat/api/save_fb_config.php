<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/chat_helpers.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }

$body  = json_decode(file_get_contents('php://input'), true);
$token = trim($body['access_token'] ?? '');

if (!$token) {
    echo json_encode(['ok' => false, 'msg' => 'กรุณาใส่ Page Access Token']);
    exit;
}

// Verify token + get page info
$res = chat_http_get("https://graph.facebook.com/v18.0/me?fields=id,name&access_token={$token}");
if (empty($res['body']['id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Token ไม่ถูกต้อง: ' . ($res['body']['error']['message'] ?? 'Facebook API error')]);
    exit;
}

$page_id   = $res['body']['id'];
$page_name = $res['body']['name'];

$pdo->prepare("
    INSERT INTO chat_platform_config (platform, page_id, page_name, access_token, connected_at)
    VALUES ('facebook', ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        page_id=VALUES(page_id), page_name=VALUES(page_name),
        access_token=VALUES(access_token), connected_at=NOW()
")->execute([$page_id, $page_name, $token]);

// Auto-subscribe page
$ch = curl_init("https://graph.facebook.com/v18.0/{$page_id}/subscribed_apps");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'subscribed_fields' => 'messages,messaging_postbacks',
        'access_token'      => $token,
    ]),
    CURLOPT_TIMEOUT => 10,
]);
curl_exec($ch);
curl_close($ch);

echo json_encode(['ok' => true, 'name' => $page_name]);
