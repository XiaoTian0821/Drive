<?php
/**
 * Drivew - Auth helpers.
 * Requires bootstrap to already be loaded.
 */

declare(strict_types=1);

namespace Drivew;

require_once __DIR__ . '/bootstrap.php';

final class Auth
{
    /** Current logged-in user row (or null). */
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (empty($_SESSION['uid'])) {
            self::$user = null;
            return null;
        }
        // If the database is not yet available (pre-install, or transient
        // connection failure), treat the session as logged-out instead of
        // raising a fatal error.
        if (!function_exists('drivew_db') || !defined('DB_DSN')) {
            self::$user = null;
            return null;
        }
        try {
            $stmt = drivew_db()->prepare(
                'SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([(int)$_SESSION['uid']]);
            $row = $stmt->fetch();
            self::$user = $row ?: null;
        } catch (PDOException $e) {
            error_log('[Auth] ' . $e->getMessage());
            self::$user = null;
        }
        return self::$user;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && $u['role'] === 'admin';
    }

    public static function requireAuth(): array
    {
        $u = self::user();
        if ($u === null) {
            header('Location: login.php');
            exit;
        }
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::requireAuth();
        if ($u['role'] !== 'admin') {
            http_response_code(403);
            header('Location: dashboard.php');
            exit;
        }
        return $u;
    }

    public static function login(int $userId): void
    {
        // Regenerate session id to prevent session fixation.
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        $_SESSION['last_login_at'] = time();
        $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    public static function logout(): void
    {
        unset($_SESSION['uid'], $_SESSION['last_login_at'], $_SESSION['login_ip']);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        // Start a fresh session so subsequent CSRF pages still work.
        drivew_start_session();
    }
}
