<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$body    = json_decode(file_get_contents('php://input'), true);
$conv_id = (int)($body['conv_id'] ?? 0);

if (!$conv_id) {
    echo json_encode(['ok' => false, 'msg' => 'conv_id required']);
    exit;
}

$pdo->prepare("UPDATE chat_conversations SET archived=1 WHERE id=?")->execute([$conv_id]);
echo json_encode(['ok' => true]);
