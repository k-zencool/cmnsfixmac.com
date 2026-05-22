<?php
// admin/chat/api/select_fb_page.php — save chosen FB page from session

session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();

header('Content-Type: application/json');

$body    = json_decode(file_get_contents('php://input'), true);
$page_id = $body['page_id'] ?? null;
$pages   = $_SESSION['fb_pending_pages'] ?? [];

$page = null;
foreach ($pages as $p) {
    if ($p['id'] === $page_id) { $page = $p; break; }
}

if (!$page) {
    echo json_encode(['ok' => false, 'msg' => 'ไม่พบ page']);
    exit;
}

$pdo->prepare("
    INSERT INTO chat_platform_config (platform, page_id, page_name, access_token, connected_at)
    VALUES ('facebook', ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        page_id=VALUES(page_id), page_name=VALUES(page_name),
        access_token=VALUES(access_token), connected_at=NOW()
")->execute([$page['id'], $page['name'], $page['access_token']]);

// Subscribe page to webhook
$ch = curl_init("https://graph.facebook.com/v18.0/{$page['id']}/subscribed_apps");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'subscribed_fields' => 'messages,messaging_postbacks',
        'access_token'      => $page['access_token'],
    ]),
    CURLOPT_TIMEOUT => 10,
]);
curl_exec($ch);
curl_close($ch);

unset($_SESSION['fb_pending_pages']);
echo json_encode(['ok' => true, 'name' => $page['name']]);
