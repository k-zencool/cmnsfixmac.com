<?php
/**
 * Admin remember-me ("จดจำฉัน") — stay logged in for 90 days, sliding.
 *
 * Why a token and not just a longer session: PHP's own session dies after
 * ~24 min idle, and on cPanel the file cleanup follows the HOST php.ini, so
 * ini_set() can't stretch it. The session cookie also dies whenever iOS
 * kills the PWA. A long-lived token restores the session instead.
 *
 * Split token: cookie `adm_rem` = "<selector>:<validator>". The DB keeps the
 * selector in clear (the lookup key) and only sha256(validator), so a leaked
 * table can't be replayed. Every restore rotates the validator and pushes
 * the expiry out another 90 days. The previous validator stays valid for
 * ADM_REMEMBER_GRACE seconds, because a cold-started PWA can fire two
 * requests at once and the slower one still carries the old value. A wrong
 * validator outside that window means the cookie was copied — burn the token.
 *
 * Each token is tied to its device's admin_sessions row (session_row_id), so
 * "บังคับออก" kills the token too; otherwise a kicked device would simply log
 * itself back in. Logout, password change and deactivation delete tokens.
 *
 * Fail-open like touch_admin_session(): no table / DB error = no remember-me,
 * never a broken login.
 */
if (!defined('ADM_REMEMBER_COOKIE')) {
    define('ADM_REMEMBER_COOKIE', 'adm_rem');
    define('ADM_REMEMBER_DAYS',   90);
    define('ADM_REMEMBER_GRACE',  60);
}

if (!function_exists('adm_is_https')) {
    function adm_is_https(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}

if (!function_exists('adm_remember_set_cookie')) {
    /** Pass an expiry in the past to delete it. */
    function adm_remember_set_cookie(string $value, int $expires): void
    {
        if (!headers_sent()) {
            setcookie(ADM_REMEMBER_COOKIE, $value, [
                'expires'  => $expires,
                'path'     => '/admin',
                'secure'   => adm_is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        if ($expires > time()) $_COOKIE[ADM_REMEMBER_COOKIE] = $value;
        else unset($_COOKIE[ADM_REMEMBER_COOKIE]);
    }
}

if (!function_exists('adm_remember_parse')) {
    /** @return array{0:string,1:string}|null  [selector, validator] */
    function adm_remember_parse(): ?array
    {
        $raw = (string)($_COOKIE[ADM_REMEMBER_COOKIE] ?? '');
        if (!preg_match('/^([a-f0-9]{18}):([a-f0-9]{64})$/', $raw, $m)) return null;
        return [$m[1], $m[2]];
    }
}

if (!function_exists('adm_start_session')) {
    /**
     * Put an admin into the current PHP session and record the device in
     * admin_sessions. Shared by the login form and the remember-me restore,
     * so both produce exactly the same session.
     * Returns the admin_sessions id (0 if that table isn't there).
     */
    function adm_start_session(PDO $pdo, array $admin): int
    {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username']  = $admin['username'];
        $_SESSION['admin_id']        = $admin['id'];
        $_SESSION['admin_role']      = $admin['role'];
        $_SESSION['LAST_ACTIVE']     = time();

        // หน้า admin/user/ (ออนไลน์ตอนนี้ / อุปกรณ์ / บังคับออก) — must never block a login
        try {
            require_once __DIR__ . '/ua_parser.php';
            $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
            $pdo->prepare("INSERT INTO admin_sessions (admin_id, session_hash, ip, user_agent, device_label) VALUES (?, ?, ?, ?, ?)")
                ->execute([
                    $admin['id'],
                    hash('sha256', session_id()),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $ua,
                    parse_device_label($ua),
                ]);
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('admin_sessions insert failed: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('adm_remember_issue')) {
    /** Called right after a successful login with "จดจำฉัน" ticked. */
    function adm_remember_issue(PDO $pdo, int $adminId, int $sessionRowId): void
    {
        try {
            $selector  = bin2hex(random_bytes(9));
            $validator = bin2hex(random_bytes(32));
            $expires   = time() + ADM_REMEMBER_DAYS * 86400;

            // housekeeping: this user's dead tokens
            $pdo->prepare("DELETE FROM admin_remember_tokens WHERE admin_id = ? AND expires_at < NOW()")
                ->execute([$adminId]);
            $pdo->prepare("INSERT INTO admin_remember_tokens
                               (admin_id, selector, validator_hash, session_row_id, user_agent, expires_at)
                           VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?))")
                ->execute([
                    $adminId, $selector, hash('sha256', $validator), $sessionRowId ?: null,
                    mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250), $expires,
                ]);
            adm_remember_set_cookie("$selector:$validator", $expires);
        } catch (Throwable $e) {
            error_log('adm_remember_issue: ' . $e->getMessage());
        }
    }
}

if (!function_exists('adm_remember_restore')) {
    /** No live session but a remember cookie? Log the admin back in. */
    function adm_remember_restore(): bool
    {
        global $pdo;
        $tok = adm_remember_parse();
        if (!$tok || !isset($pdo)) return false;
        [$selector, $validator] = $tok;

        try {
            // grace is judged by MySQL's clock, not PHP's — the two time zones can differ
            $st = $pdo->prepare("SELECT r.id, r.admin_id, r.validator_hash, r.prev_validator_hash,
                                        (r.rotated_at IS NOT NULL AND r.rotated_at > NOW() - INTERVAL ? SECOND) AS in_grace,
                                        u.username, u.role, u.is_active
                                 FROM admin_remember_tokens r
                                 JOIN admin_users u ON u.id = r.admin_id
                                 WHERE r.selector = ? AND r.expires_at > NOW()
                                 LIMIT 1");
            $st->execute([ADM_REMEMBER_GRACE, $selector]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                adm_remember_set_cookie('', time() - 3600);
                return false;
            }

            $h       = hash('sha256', $validator);
            $current = hash_equals($row['validator_hash'], $h);
            $grace   = !$current && (int)$row['in_grace'] === 1
                       && $row['prev_validator_hash'] !== null
                       && hash_equals($row['prev_validator_hash'], $h);

            if (!$current && !$grace) {
                // Right selector, wrong secret, outside the grace window: copied cookie.
                $pdo->prepare("DELETE FROM admin_remember_tokens WHERE id = ?")->execute([$row['id']]);
                adm_remember_set_cookie('', time() - 3600);
                return false;
            }
            if ((int)$row['is_active'] !== 1) {
                $pdo->prepare("DELETE FROM admin_remember_tokens WHERE admin_id = ?")->execute([$row['admin_id']]);
                adm_remember_set_cookie('', time() - 3600);
                return false;
            }

            // role comes fresh from admin_users, so a role change applies on the next restore
            $sessRow = adm_start_session($pdo, [
                'id' => (int)$row['admin_id'], 'username' => $row['username'], 'role' => $row['role'],
            ]);

            // The grace request must NOT rotate again: its twin already set the
            // new cookie, and a second rotation would strand whichever value the
            // browser ends up keeping.
            if ($current) {
                $newValidator = bin2hex(random_bytes(32));
                $expires      = time() + ADM_REMEMBER_DAYS * 86400;
                $pdo->prepare("UPDATE admin_remember_tokens
                               SET prev_validator_hash = validator_hash,
                                   validator_hash      = ?,
                                   rotated_at          = NOW(),
                                   session_row_id      = ?,
                                   last_used_at        = NOW(),
                                   expires_at          = FROM_UNIXTIME(?)
                               WHERE id = ?")
                    ->execute([hash('sha256', $newValidator), $sessRow ?: null, $expires, $row['id']]);
                adm_remember_set_cookie("$selector:$newValidator", $expires);
            }
            return true;
        } catch (Throwable $e) {
            error_log('adm_remember_restore: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('adm_remember_forget_current')) {
    /** This device: logout, or being kicked. Always clears the cookie. */
    function adm_remember_forget_current(?PDO $pdo): void
    {
        $tok = adm_remember_parse();
        if ($tok && $pdo) {
            try {
                $pdo->prepare("DELETE FROM admin_remember_tokens WHERE selector = ?")->execute([$tok[0]]);
            } catch (Throwable $e) {
                error_log('adm_remember_forget_current: ' . $e->getMessage());
            }
        }
        adm_remember_set_cookie('', time() - 3600);
    }
}

if (!function_exists('adm_remember_forget_session')) {
    /** "บังคับออก" on one device (admin_sessions.id). */
    function adm_remember_forget_session(PDO $pdo, int $sessionRowId): void
    {
        try {
            $pdo->prepare("DELETE FROM admin_remember_tokens WHERE session_row_id = ?")->execute([$sessionRowId]);
        } catch (Throwable $e) {
            error_log('adm_remember_forget_session: ' . $e->getMessage());
        }
    }
}

if (!function_exists('adm_remember_forget_user')) {
    /** Every device of one admin — password change, deactivation, delete. */
    function adm_remember_forget_user(PDO $pdo, int $adminId, bool $keepThisDevice = false): void
    {
        try {
            $tok = $keepThisDevice ? adm_remember_parse() : null;
            if ($tok) {
                $pdo->prepare("DELETE FROM admin_remember_tokens WHERE admin_id = ? AND selector <> ?")
                    ->execute([$adminId, $tok[0]]);
            } else {
                $pdo->prepare("DELETE FROM admin_remember_tokens WHERE admin_id = ?")->execute([$adminId]);
            }
        } catch (Throwable $e) {
            error_log('adm_remember_forget_user: ' . $e->getMessage());
        }
    }
}
