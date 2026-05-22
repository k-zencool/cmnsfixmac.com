<?php
session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$body    = json_decode(file_get_contents('php://input'), true);
$conv_id = (int)($body['conv_id'] ?? 0);
$name    = trim($body['name'] ?? '');

if (!$conv_id || $name === '') {
    echo json_encode(['ok' => false, 'msg' => 'conv_id and name required']);
    exit;
}

$pdo->prepare("
    UPDATE chat_contacts ct
    JOIN chat_conversations cv ON cv.contact_id = ct.id
    SET ct.display_name = ?
    WHERE cv.id = ?
")->execute([$name, $conv_id]);

echo json_encode(['ok' => true]);
