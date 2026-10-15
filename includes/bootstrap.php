<?php
/**
 * Drivew - Core bootstrap.
 * Loaded by every page. Sets up autoloading for shared helpers.
 */

declare(strict_types=1);

// Composer-free autoloader: map Drivew\* to includes/
spl_autoload_register(function (string $class): void {
    $prefix = 'Drivew\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Ensure config is loaded exactly once.
if (!defined('APP_NAME')) {
    require dirname(__DIR__) . '/config/config.php';
}

// Public-share pages and the installer may run before installation or
// before a database is reachable. Everything else requires the lock file.
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$skipDbFor = ($script === 'install.php' || strpos($script, 'public_') === 0);

if (!$skipDbFor && !defined('DB_DSN')) {
    require dirname(__DIR__) . '/config/database.php';
}

if (!is_file(INSTALL_LOCK) && !$skipDbFor) {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if (basename($base) === 'admin') {
        $base = dirname($base);
    }
    header('Location: ' . $base . '/install/install.php');
    exit;
}
