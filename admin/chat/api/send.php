<?php
// admin/chat/api/send.php — send a reply message

session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/chat_config.php';
require_once __DIR__ . '/../../../includes/chat_helpers.php';

$fb_token   = fb_page_token($pdo);
$line_token = line_token($pdo);
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'POST only']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true);
$conv_id = (int)($body['conv_id'] ?? 0);
$text    = trim($body['text'] ?? '');

if (!$conv_id || $text === '') {
    echo json_encode(['ok' => false, 'msg' => 'conv_id and text required']);
    exit;
}

// Get conversation + contact
$stmt = $pdo->prepare("
    SELECT cv.platform, ct.platform_user_id
    FROM chat_conversations cv
    JOIN chat_contacts ct ON ct.id = cv.contact_id
    WHERE cv.id = ?
");
$stmt->execute([$conv_id]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    echo json_encode(['ok' => false, 'msg' => 'conversation not found']);
    exit;
}

$sent = false;

if ($conv['platform'] === 'facebook') {
    if (!$fb_token) { echo json_encode(['ok' => false, 'msg' => 'Facebook ยังไม่ได้เชื่อมต่อ']); exit; }
    $res = chat_http_post(
        'https://graph.facebook.com/v18.0/me/messages?access_token=' . $fb_token,
        [
            'recipient'      => ['id' => $conv['platform_user_id']],
            'message'        => ['text' => $text],
            'messaging_type' => 'RESPONSE',
        ]
    );
    $sent = $res['code'] === 200;

} elseif ($conv['platform'] === 'line') {
    if (!$line_token) { echo json_encode(['ok' => false, 'msg' => 'LINE ยังไม่ได้เชื่อมต่อ']); exit; }
    $res = chat_http_post(
        'https://api.line.me/v2/bot/message/push',
        [
            'to'       => $conv['platform_user_id'],
            'messages' => [['type' => 'text', 'text' => $text]],
        ],
        ['Authorization: Bearer ' . $line_token]
    );
    $sent = $res['code'] === 200;
}

if ($sent) {
    // Store with message_id so echo dedup works
    $mid = $res['body']['message_id'] ?? null;
    chat_store_message($pdo, $conv_id, $mid, 'outgoing', 'text', $text, null);
    echo json_encode(['ok' => true]);
} else {
    $err = $res['body']['message'] ?? $res['body']['error']['message'] ?? 'API error';
    echo json_encode(['ok' => false, 'msg' => $err, 'code' => $res['code']]);
}
