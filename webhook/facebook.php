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
        if (!isset($event['message'])) continue;

        $msg = $event['message'];
        $mid = $msg['mid'] ?? null;

        // ── Echo: admin replied from Facebook app ────────────────────────────
        if (!empty($msg['is_echo'])) {
            $recipient_id = $event['recipient']['id'] ?? null;
            if (!$recipient_id) continue;

            // Skip if already stored (sent via our send.php)
            if ($mid) {
                $exists = $pdo->prepare("SELECT id FROM chat_messages WHERE platform_message_id=? LIMIT 1");
                $exists->execute([$mid]);
                if ($exists->fetch()) continue;
            }

            // Find conversation by recipient PSID
            $stmt = $pdo->prepare("
                SELECT cv.id FROM chat_conversations cv
                JOIN chat_contacts ct ON ct.id = cv.contact_id
                WHERE ct.platform='facebook' AND ct.platform_user_id=?
                LIMIT 1
            ");
            $stmt->execute([$recipient_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue; // no conversation yet for this user, skip

            $content = $msg['text'] ?? null;
            if (!$content && empty($msg['attachments'])) continue;

            if (!empty($msg['attachments'])) {
                $att  = $msg['attachments'][0];
                $type = in_array($att['type'], ['image','video','audio','file']) ? $att['type'] : 'file';
                chat_store_message($pdo, (int)$row['id'], $mid, 'outgoing', $type, null, $att['payload']['url'] ?? null);
            } else {
                chat_store_message($pdo, (int)$row['id'], $mid, 'outgoing', 'text', $content, null);
            }
            continue;
        }

        // ── Incoming message from user ────────────────────────────────────────
        $sender_id = $event['sender']['id'];

        $profile = fetch_fb_profile($sender_id);
        $contact = chat_upsert_contact($pdo, 'facebook', $sender_id, $profile['name'], $profile['picture']);
        $conv_id = chat_get_or_create_conversation($pdo, (int)$contact['id'], 'facebook');

        if (!empty($msg['attachments'])) {
            $att   = $msg['attachments'][0];
            $type  = in_array($att['type'], ['image','video','audio','file']) ? $att['type'] : 'file';
            chat_store_message($pdo, $conv_id, $mid, 'incoming', $type, null, $att['payload']['url'] ?? null);
        } elseif (isset($msg['text'])) {
            chat_store_message($pdo, $conv_id, $mid, 'incoming', 'text', $msg['text'], null);
        }
    }
}

http_response_code(200);
echo 'OK';

// ─────────────────────────────────────────────────────────────────────────────
function fetch_fb_profile(string $sender_id): array {
    global $fb_token;

    // 1st try: direct user profile — gets name + profile picture
    $res = chat_http_get(
        "https://graph.facebook.com/v20.0/{$sender_id}?fields=name,profile_pic&access_token={$fb_token}"
    );
    if (!empty($res['body']['name'])) {
        return [
            'name'    => $res['body']['name'],
            'picture' => $res['body']['profile_pic'] ?? null,
        ];
    }

    // 2nd try: conversations API (fallback, no picture)
    $res2 = chat_http_get(
        "https://graph.facebook.com/v20.0/me/conversations?user_id={$sender_id}&fields=participants&access_token={$fb_token}"
    );
    $name = 'Facebook User';
    foreach ($res2['body']['data'][0]['participants']['data'] ?? [] as $p) {
        if (($p['id'] ?? '') === $sender_id) {
            $name = $p['name'];
            break;
        }
    }

    return ['name' => $name, 'picture' => null];
}
