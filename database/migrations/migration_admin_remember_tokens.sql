-- ============================================================
-- Admin "จดจำฉัน" (remember-me) tokens — includes/remember.php
--
-- One row per remembered device. The cookie holds "<selector>:<validator>";
-- only sha256(validator) is stored, so a leaked table can't be replayed.
-- session_row_id ties the token to that device's admin_sessions row, which
-- is how "บังคับออก" (kill_session.php) also kills the token.
--
-- Safe to re-run (IF NOT EXISTS). Must be run by hand on production —
-- the deploy pipeline never touches the database.
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_remember_tokens (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id            INT NOT NULL,
    selector            CHAR(18) NOT NULL,
    validator_hash      CHAR(64) NOT NULL,
    prev_validator_hash CHAR(64) DEFAULT NULL,
    rotated_at          DATETIME DEFAULT NULL,
    session_row_id      BIGINT UNSIGNED DEFAULT NULL,
    user_agent          VARCHAR(255) DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at          DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_selector (selector),
    KEY idx_admin (admin_id),
    KEY idx_session_row (session_row_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
