<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$body     = json_decode(file_get_contents('php://input'), true);
$platform = $body['platform'] ?? '';

if (!in_array($platform, ['facebook', 'line'])) {
    echo json_encode(['ok' => false, 'msg' => 'invalid platform']);
    exit;
}

$pdo->prepare("DELETE FROM chat_platform_config WHERE platform=?")->execute([$platform]);
echo json_encode(['ok' => true]);
