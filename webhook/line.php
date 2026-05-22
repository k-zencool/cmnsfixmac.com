<?php
// webhook/line.php — receives LINE Messaging API events

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/chat_config.php';
require_once __DIR__ . '/../includes/chat_helpers.php';

$raw    = file_get_contents('php://input');
$secret = line_secret($pdo);
$token  = line_token($pdo);

// Validate X-Line-Signature
if ($secret !== '') {
    $sig      = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
    $expected = base64_encode(hash_hmac('sha256', $raw, $secret, true));
    if (!hash_equals($expected, $sig)) {
        http_response_code(401);
        exit('Unauthorized');
    }
}

$body = json_decode($raw, true);

foreach ($body['events'] ?? [] as $event) {
    if ($event['type'] !== 'message') continue;

    $user_id = $event['source']['userId'] ?? null;
    if (!$user_id) continue;

    $profile = fetch_line_profile($user_id, $token);
    $contact = chat_upsert_contact($pdo, 'line', $user_id, $profile['displayName'] ?? 'LINE User', $profile['pictureUrl'] ?? null);
    $conv_id = chat_get_or_create_conversation($pdo, (int)$contact['id'], 'line');

    $msg     = $event['message'];
    $type    = $msg['type'];
    $content = null;
    $media   = null;

    if ($type === 'text') {
        $content = $msg['text'];
    } elseif ($type === 'sticker') {
        $content = '[Sticker]';
        $type    = 'sticker';
    } elseif (in_array($type, ['image', 'video', 'audio', 'file'])) {
        $media = "line_media:{$msg['id']}";
    } else {
        continue;
    }

    chat_store_message($pdo, $conv_id, $msg['id'], 'incoming', $type, $content, $media);
}

http_response_code(200);
echo 'OK';

function fetch_line_profile(string $user_id, string $token): array {
    $res = chat_http_get(
        "https://api.line.me/v2/bot/profile/{$user_id}",
        ["Authorization: Bearer {$token}"]
    );
    return $res['body'];
}
