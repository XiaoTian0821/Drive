<?php
/**
 * Drivew - CSRF token helpers.
 *
 * One token per session, generated on first use, validated on every
 * state-changing POST.
 */

declare(strict_types=1);

namespace Drivew;

require_once __DIR__ . '/bootstrap.php';

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /** Hidden input to embed in any form. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' .
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Verify a token supplied via POST or X-CSRF-Token header. */
    public static function verify(?string $token = null): bool
    {
        if ($token === null) {
            $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        }
        if (is_string($token) && $token !== '' && !empty($_SESSION['csrf'])) {
            return hash_equals($_SESSION['csrf'], $token);
        }
        return false;
    }

    /** Abort with 419 if the CSRF check fails. */
    public static function requireValid(?string $token = null): void
    {
        if (!self::verify($token)) {
            http_response_code(419);
            exit('Invalid or expired security token. Please refresh the page and try again.');
        }
    }
}
