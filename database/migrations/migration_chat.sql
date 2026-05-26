-- Chat system tables
-- Run: docker exec -i cmns-db mysql -ucmns_user -pcmns_password cmnsfixmac_db < db/migration_chat.sql

CREATE TABLE IF NOT EXISTS chat_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('facebook', 'line') NOT NULL,
    platform_user_id VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) DEFAULT NULL,
    picture_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_platform_user (platform, platform_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NOT NULL,
    platform ENUM('facebook', 'line') NOT NULL,
    last_message_at TIMESTAMP NULL DEFAULT NULL,
    last_message_preview VARCHAR(255) DEFAULT NULL,
    unread_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES chat_contacts(id) ON DELETE CASCADE,
    INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    platform_message_id VARCHAR(255) DEFAULT NULL,
    direction ENUM('incoming', 'outgoing') NOT NULL,
    message_type ENUM('text', 'image', 'sticker', 'file', 'audio', 'video') DEFAULT 'text',
    content TEXT DEFAULT NULL,
    media_url VARCHAR(500) DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    INDEX idx_conv_sent (conversation_id, sent_at),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_platform_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('facebook', 'line') NOT NULL,
    page_id VARCHAR(100) DEFAULT NULL,
    page_name VARCHAR(255) DEFAULT NULL,
    access_token TEXT DEFAULT NULL,
    secret_key VARCHAR(255) DEFAULT NULL,
    connected_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
