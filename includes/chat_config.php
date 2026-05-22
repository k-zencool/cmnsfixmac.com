<?php
// includes/chat_config.php

define('FB_APP_ID',     $_ENV['FB_APP_ID']     ?? '');
define('FB_APP_SECRET', $_ENV['FB_APP_SECRET'] ?? '');
define('FB_VERIFY_TOKEN', $_ENV['FB_VERIFY_TOKEN'] ?? 'cmnsfixmac_verify_2025');

function chat_get_platform_config(PDO $pdo, string $platform): array {
    static $cache = [];
    if (!isset($cache[$platform])) {
        $stmt = $pdo->prepare("SELECT * FROM chat_platform_config WHERE platform=?");
        $stmt->execute([$platform]);
        $cache[$platform] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return $cache[$platform];
}

function fb_page_token(PDO $pdo): string {
    return chat_get_platform_config($pdo, 'facebook')['access_token'] ?? '';
}

function line_token(PDO $pdo): string {
    return chat_get_platform_config($pdo, 'line')['access_token']
        ?? $_ENV['LINE_CHANNEL_ACCESS_TOKEN'] ?? '';
}

function line_secret(PDO $pdo): string {
    return chat_get_platform_config($pdo, 'line')['secret_key']
        ?? $_ENV['LINE_CHANNEL_SECRET'] ?? '';
}

function chat_oauth_redirect_uri(): string {
    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
    if (!$base) {
        $proto = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $base  = $proto . '://' . $_SERVER['HTTP_HOST'];
    }
    return $base . '/admin/chat/oauth/facebook_callback.php';
}
