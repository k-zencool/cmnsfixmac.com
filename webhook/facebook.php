<?php
// webhook/facebook.php — receives Facebook Messenger events

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/chat_config.php';
require_once __DIR__ . '/../includes/chat_helpers.php';

// ── Webhook verification (GET) ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (
        ($_GET['hub_mode']         ?? '') === 'subscribe' &&
        ($_GET['hub_verify_token'] ?? '') === FB_VERIFY_TOKEN
    ) {
        http_response_code(200);
        echo $_GET['hub_challenge'];
    } else {
        http_response_code(403);
        echo 'Forbidden';
    }
    exit;
}

// ── Receive events (POST) ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input');

// Validate X-Hub-Signature-256
if (FB_APP_SECRET !== '') {
    $sig      = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expected = 'sha256=' . hash_hmac('sha256', $raw, FB_APP_SECRET);
    if (!hash_equals($expected, $sig)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

// Load page token from DB
$fb_token = fb_page_token($pdo);

$body = json_decode($raw, true);
if (($body['object'] ?? '') !== 'page') {
    http_response_code(200);
    exit('OK');
}

foreach ($body['entry'] ?? [] as $entry) {
    foreach ($entry['messaging'] ?? [] as $event) {
        // Skip echoes and delivery/read receipts
        if (!isset($event['message']) || isset($event['message']['is_echo'])) continue;

        $sender_id = $event['sender']['id'];
        $msg       = $event['message'];

        // Fetch + upsert contact
        $profile = fetch_fb_profile($sender_id);
        $contact = chat_upsert_contact($pdo, 'facebook', $sender_id, $profile['name'], $profile['picture']);
        $conv_id = chat_get_or_create_conversation($pdo, (int)$contact['id'], 'facebook');

        // Determine type + content
        if (!empty($msg['attachments'])) {
            $att   = $msg['attachments'][0];
            $type  = in_array($att['type'], ['image','video','audio','file']) ? $att['type'] : 'file';
            $media = $att['payload']['url'] ?? null;
            chat_store_message($pdo, $conv_id, $msg['mid'] ?? null, 'incoming', $type, null, $media);
        } elseif (isset($msg['text'])) {
            chat_store_message($pdo, $conv_id, $msg['mid'] ?? null, 'incoming', 'text', $msg['text'], null);
        }
    }
}

http_response_code(200);
echo 'OK';

// ─────────────────────────────────────────────────────────────────────────────
function fetch_fb_profile(string $sender_id): array {
    global $fb_token;
    $res = chat_http_get(
        "https://graph.facebook.com/v18.0/me/conversations?user_id={$sender_id}&fields=participants&access_token={$fb_token}"
    );

    $name = 'Facebook User';
    if (!empty($res['body']['data'][0]['participants']['data'])) {
        foreach ($res['body']['data'][0]['participants']['data'] as $p) {
            if (($p['id'] ?? '') === $sender_id) {
                $name = $p['name'];
                break;
            }
        }
    }

    return ['name' => $name, 'picture' => null];
}
