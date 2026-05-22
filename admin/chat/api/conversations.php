<?php
// admin/chat/api/conversations.php — returns conversation list as JSON

session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$platform = $_GET['platform'] ?? 'all';
$params   = [];
$where    = '';

if (in_array($platform, ['facebook', 'line'])) {
    $where    = 'AND cv.platform = ?';
    $params[] = $platform;
}

$rows = $pdo->prepare("
    SELECT
        cv.id,
        cv.platform,
        cv.last_message_at,
        cv.last_message_preview,
        cv.unread_count,
        ct.display_name,
        ct.picture_url,
        ct.platform_user_id
    FROM chat_conversations cv
    JOIN chat_contacts ct ON ct.id = cv.contact_id
    WHERE 1=1 {$where}
    ORDER BY cv.last_message_at DESC
    LIMIT 100
");
$rows->execute($params);
$conversations = $rows->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'data' => $conversations]);
