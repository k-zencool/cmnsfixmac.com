<?php
// admin/chat/api/messages.php — returns messages for a conversation

session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$conv_id   = (int)($_GET['conv_id']   ?? 0);
$after_id  = (int)($_GET['after_id']  ?? 0);
$before_id = (int)($_GET['before_id'] ?? 0);

if (!$conv_id) {
    echo json_encode(['ok' => false, 'msg' => 'conv_id required']);
    exit;
}

if (!$after_id && !$before_id) {
    $pdo->prepare("UPDATE chat_conversations SET unread_count=0 WHERE id=?")->execute([$conv_id]);
}

if ($before_id) {
    // Scroll-up history: 40 older messages before this id
    $stmt = $pdo->prepare("
        SELECT id, direction, message_type, content, media_url, sent_at
        FROM chat_messages
        WHERE conversation_id = ? AND id < ?
        ORDER BY id DESC
        LIMIT 40
    ");
    $stmt->execute([$conv_id, $before_id]);
    $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    $has_more = count($messages) === 40;

} elseif ($after_id === 0) {
    // Initial open: newest 60 messages
    $stmt = $pdo->prepare("
        SELECT id, direction, message_type, content, media_url, sent_at
        FROM chat_messages
        WHERE conversation_id = ?
        ORDER BY id DESC
        LIMIT 60
    ");
    $stmt->execute([$conv_id]);
    $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    $has_more = count($messages) === 60;

} else {
    // Polling: new messages after last known id
    $stmt = $pdo->prepare("
        SELECT id, direction, message_type, content, media_url, sent_at
        FROM chat_messages
        WHERE conversation_id = ? AND id > ?
        ORDER BY id ASC
        LIMIT 60
    ");
    $stmt->execute([$conv_id, $after_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_more = false;
}

echo json_encode(['ok' => true, 'data' => $messages, 'has_more' => $has_more]);
