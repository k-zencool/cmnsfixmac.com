<?php
// admin/chat/api/save_line_config.php

session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/chat_helpers.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false]); exit; }

$body   = json_decode(file_get_contents('php://input'), true);
$token  = trim($body['access_token'] ?? '');
$secret = trim($body['channel_secret'] ?? '');

if (!$token || !$secret) {
    echo json_encode(['ok' => false, 'msg' => 'กรุณากรอกทั้ง Access Token และ Channel Secret']);
    exit;
}

// Test token against LINE API
$res = chat_http_get('https://api.line.me/v2/bot/info', ["Authorization: Bearer {$token}"]);
if ($res['code'] !== 200) {
    echo json_encode(['ok' => false, 'msg' => 'Token ไม่ถูกต้อง: ' . ($res['body']['message'] ?? 'LINE API error')]);
    exit;
}

$bot_name = $res['body']['displayName'] ?? 'LINE Bot';

$pdo->prepare("
    INSERT INTO chat_platform_config (platform, page_name, access_token, secret_key, connected_at)
    VALUES ('line', ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        page_name=VALUES(page_name), access_token=VALUES(access_token),
        secret_key=VALUES(secret_key), connected_at=NOW()
")->execute([$bot_name, $token, $secret]);

echo json_encode(['ok' => true, 'name' => $bot_name]);
