<?php
/**
 * Drivew - JSON response helpers for AJAX endpoints.
 */

declare(strict_types=1);

namespace Drivew;

require_once __DIR__ . '/bootstrap.php';

final class Ajax
{
    public static function ok(string $message = 'OK', array $extra = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode(array_merge(['success' => true, 'message' => $message], $extra));
        exit;
    }

    public static function fail(string $message = 'Request failed.', int $code = 400, array $extra = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(array_merge(['success' => false, 'message' => $message], $extra));
        exit;
    }

    /** Require authentication; returns the user row. */
    public static function requireUser(): array
    {
        $u = Auth::user();
        if ($u === null) {
            self::fail('Authentication required.', 401);
        }
        return $u;
    }

    /** Require admin. */
    public static function requireAdmin(): array
    {
        $u = self::requireUser();
        if ($u['role'] !== 'admin') {
            self::fail('Administrator access required.', 403);
        }
        return $u;
    }

    /** Require a valid CSRF token. */
    public static function requireCsrf(): void
    {
        if (!Csrf::verify()) {
            self::fail('Invalid CSRF token.', 419);
        }
    }

    /** Read and parse a JSON request body. */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '[]', true);
        return is_array($data) ? $data : [];
    }
}
