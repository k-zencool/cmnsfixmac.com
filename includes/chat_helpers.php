<?php
// includes/chat_helpers.php — shared DB helpers for both webhook endpoints

function chat_upsert_contact(PDO $pdo, string $platform, string $platform_user_id, string $display_name, ?string $picture_url): array {
    $fallbacks = ['Facebook User', 'LINE User', ''];
    $is_real   = !in_array($display_name, $fallbacks, true);

    // Only overwrite name/picture if we have real data
    $pdo->prepare("
        INSERT INTO chat_contacts (platform, platform_user_id, display_name, picture_url)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            display_name = IF(? = 1, VALUES(display_name), display_name),
            picture_url  = IF(? = 1 AND VALUES(picture_url) IS NOT NULL, VALUES(picture_url), picture_url)
    ")->execute([$platform, $platform_user_id, $display_name, $picture_url, (int)$is_real, (int)$is_real]);

    $stmt = $pdo->prepare("SELECT * FROM chat_contacts WHERE platform=? AND platform_user_id=?");
    $stmt->execute([$platform, $platform_user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function chat_get_or_create_conversation(PDO $pdo, int $contact_id, string $platform): int {
    $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE contact_id=? AND platform=?");
    $stmt->execute([$contact_id, $platform]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return (int)$row['id'];

    $pdo->prepare("INSERT INTO chat_conversations (contact_id, platform, last_message_at) VALUES (?, ?, NOW())")
        ->execute([$contact_id, $platform]);
    return (int)$pdo->lastInsertId();
}

function chat_store_message(PDO $pdo, int $conv_id, ?string $msg_id, string $direction, string $type, ?string $content, ?string $media_url): void {
    $pdo->prepare("
        INSERT INTO chat_messages (conversation_id, platform_message_id, direction, message_type, content, media_url)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$conv_id, $msg_id, $direction, $type, $content, $media_url]);

    $preview  = mb_substr($content ?? "[{$type}]", 0, 100);
    $unread   = $direction === 'incoming' ? 'unread_count+1' : 'unread_count';
    $pdo->prepare("
        UPDATE chat_conversations
        SET last_message_at=NOW(), last_message_preview=?, unread_count={$unread}
        WHERE id=?
    ")->execute([$preview, $conv_id]);
}

function chat_http_get(string $url, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res ?: '{}', true) ?? []];
}

function chat_http_post(string $url, array $payload, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json'], $headers));
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($res ?: '{}', true) ?? []];
}
