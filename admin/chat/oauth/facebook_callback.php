<?php
// admin/chat/oauth/facebook_callback.php

session_start();
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/chat_config.php';
require_once __DIR__ . '/../../../includes/chat_helpers.php';
require_login();

$code  = $_GET['code']  ?? null;
$error = $_GET['error_description'] ?? ($_GET['error'] ?? null);

if ($error || !$code) {
    $_SESSION['error'] = 'Facebook ปฏิเสธการเชื่อมต่อ: ' . ($error ?? 'ไม่มี code');
    header('Location: /admin/chat/settings.php');
    exit;
}

$redirect_uri = chat_oauth_redirect_uri();

// Exchange code → user access token
$res = chat_http_get(
    "https://graph.facebook.com/v18.0/oauth/access_token" .
    "?client_id=" . FB_APP_ID .
    "&client_secret=" . FB_APP_SECRET .
    "&redirect_uri=" . urlencode($redirect_uri) .
    "&code=" . urlencode($code)
);

$user_token = $res['body']['access_token'] ?? null;
if (!$user_token) {
    $_SESSION['error'] = 'ไม่สามารถแลก token ได้: ' . ($res['body']['error']['message'] ?? 'unknown');
    header('Location: /admin/chat/settings.php');
    exit;
}

// Get pages this user manages
$pages_res = chat_http_get("https://graph.facebook.com/v18.0/me/accounts?fields=id,name,access_token&access_token={$user_token}");
$pages = $pages_res['body']['data'] ?? [];

if (empty($pages)) {
    $_SESSION['error'] = 'ไม่พบ Facebook Page ที่จัดการอยู่';
    header('Location: /admin/chat/settings.php');
    exit;
}

if (count($pages) === 1) {
    save_fb_page($pdo, $pages[0]);
    $_SESSION['success'] = 'เชื่อมต่อ Facebook Page สำเร็จ: ' . $pages[0]['name'];
    header('Location: /admin/chat/settings.php');
    exit;
}

// Multiple pages — let user choose
$_SESSION['fb_pending_pages'] = $pages;
header('Location: /admin/chat/settings.php?select_page=1');
exit;

// ─────────────────────────────────────────────────────────────────────────────
function save_fb_page(PDO $pdo, array $page): void {
    $pdo->prepare("
        INSERT INTO chat_platform_config (platform, page_id, page_name, access_token, connected_at)
        VALUES ('facebook', ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            page_id=VALUES(page_id), page_name=VALUES(page_name),
            access_token=VALUES(access_token), connected_at=NOW()
    ")->execute([$page['id'], $page['name'], $page['access_token']]);

    // Auto-subscribe page to webhook
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
}
