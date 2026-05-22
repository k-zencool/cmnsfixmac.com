<?php
// admin/chat/api/messages.php — returns messages for a conversation

session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$conv_id  = (int)($_GET['conv_id']  ?? 0);
$after_id = (int)($_GET['after_id'] ?? 0);

if (!$conv_id) {
    echo json_encode(['ok' => false, 'msg' => 'conv_id required']);
    exit;
}

// Mark conversation as read
if (!$after_id) {
    $pdo->prepare("UPDATE chat_conversations SET unread_count=0 WHERE id=?")->execute([$conv_id]);
}

$stmt = $pdo->prepare("
    SELECT id, direction, message_type, content, media_url, sent_at
    FROM chat_messages
    WHERE conversation_id = ? AND id > ?
    ORDER BY sent_at ASC, id ASC
    LIMIT 200
");
$stmt->execute([$conv_id, $after_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'data' => $messages]);
