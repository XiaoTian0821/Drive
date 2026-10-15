<?php
/**
 * Drivew - Public download (token-gated).
 * For files: streams the physical file. For folders: builds a ZIP on the fly.
 */
require_once __DIR__ . '/includes/functions.php';

$token = trim((string)($_GET['t'] ?? ''));
$p = (string)($_GET['p'] ?? '');

$share = null;
$resource = null;
$dbOk = function_exists('drivew_db') && defined('DB_DSN');
if ($token !== '' && $dbOk) {
    try {
        $share = \Drivew\F::shareByToken($token);
        $resource = $share ? \Drivew\F::shareResource($share) : null;
    } catch (Throwable $e) {
        $share = null;
        $resource = null;
    }
}

function publicGated(?array $share, ?array $resource, string $p): bool
{
    if ($share === null || $resource === null) return false;
    if ((int)$share['is_active'] !== 1) return false;
    if ($share['expires_at'] && strtotime($share['expires_at']) < time()) return false;
    if (!empty($share['password_hash'])) {
        if ($p === '' || !password_verify($p, $share['password_hash'])) return false;
    }
    return true;
}

if (!publicGated($share, $resource, $p)) {
    header('Location: public_share.php?t=' . urlencode($token));
    exit;
}

if ($share['resource_type'] === 'file') {
    $f = $resource;
    $path = \Drivew\F::resolvePath($f);
    if ($path === null) { http_response_code(404); exit('File missing.'); }
    $mime = $f['mime_type'] ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w.\- ()]/', '_', $f['original_name']) . '"');
    header('Content-Length: ' . (int)$f['size']);
    @readfile($path);
    exit;
}

// Folder -> ZIP
if (!class_exists('ZipArchive')) {
    http_response_code(501);
    exit('ZIP support is not available on this server.');
}

$zipPath = \Drivew\F::createFolderZip($resource, (int)$share['user_id']);
if ($zipPath === null) { http_response_code(500); exit('Could not create archive.'); }

$zipSize = filesize($zipPath);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^\w.\- ()]/', '_', $resource['name']) . '.zip"');
header('Content-Length: ' . $zipSize);
@readfile($zipPath);
@unlink($zipPath);
exit;
