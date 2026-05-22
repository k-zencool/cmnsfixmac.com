<?php
// Refresh Facebook contacts that still have placeholder name or no picture

session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/chat_config.php';
require_once __DIR__ . '/../../../includes/chat_helpers.php';
require_login();

header('Content-Type: application/json');

$token = fb_page_token($pdo);
if (!$token) {
    echo json_encode(['ok' => false, 'msg' => 'Facebook ยังไม่ได้เชื่อมต่อ']);
    exit;
}

// Fetch all FB contacts with missing name or picture
$stmt = $pdo->query("
    SELECT id, platform_user_id, display_name, picture_url
    FROM chat_contacts
    WHERE platform = 'facebook'
      AND (display_name IN ('Facebook User', '') OR picture_url IS NULL)
");
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$contacts) {
    echo json_encode(['ok' => true, 'updated' => 0, 'msg' => 'ไม่มีที่ต้องอัปเดต']);
    exit;
}

$updated = 0;
$failed  = 0;

foreach ($contacts as $c) {
    $res = chat_http_get(
        "https://graph.facebook.com/v20.0/{$c['platform_user_id']}?fields=name,profile_pic&access_token={$token}"
    );

    $name    = $res['body']['name']        ?? null;
    $picture = $res['body']['profile_pic'] ?? null;

    if (!$name && empty($picture)) {
        // Fallback: conversations API
        $res2 = chat_http_get(
            "https://graph.facebook.com/v20.0/me/conversations?user_id={$c['platform_user_id']}&fields=participants&access_token={$token}"
        );
        foreach ($res2['body']['data'][0]['participants']['data'] ?? [] as $p) {
            if (($p['id'] ?? '') === $c['platform_user_id']) {
                $name = $p['name'];
                break;
            }
        }
    }

    if (!$name && !$picture) {
        $failed++;
        continue;
    }

    // Only overwrite fields that we actually got
    $sets  = [];
    $binds = [];
    if ($name && $name !== 'Facebook User') {
        $sets[]  = 'display_name = ?';
        $binds[] = $name;
    }
    if ($picture) {
        $sets[]  = 'picture_url = ?';
        $binds[] = $picture;
    }

    if ($sets) {
        $binds[] = $c['id'];
        $pdo->prepare("UPDATE chat_contacts SET " . implode(', ', $sets) . " WHERE id = ?")
            ->execute($binds);
        $updated++;
    }
}

echo json_encode([
    'ok'      => true,
    'updated' => $updated,
    'failed'  => $failed,
    'msg'     => "อัปเดต {$updated} คน, ดึงไม่ได้ {$failed} คน",
]);
