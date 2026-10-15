<?php
/**
 * Drivew - Secure download handler.
 * ?id=FILE_ID  -> download a user's own file (auth + ownership check)
 * ?v=VERSION_ID -> download a specific older version
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];

// Version download
if (isset($_GET['v'])) {
    $vid = (int)$_GET['v'];
    $stmt = drivew_db()->prepare('SELECT * FROM file_versions WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$vid, $uid]);
    $ver = $stmt->fetch();
    if (!$ver) { http_response_code(404); exit('Version not found.'); }
    $path = UPLOAD_ROOT . '/' . $ver['stored_path'];
    if (!is_file($path)) { http_response_code(404); exit('Physical file missing.'); }
    \Drivew\F::log($uid, 'download_version', 'file', (int)$ver['file_id'], 'Downloaded v' . $ver['version_num']);
    sendFile($path, $ver['size'], 'version_' . $ver['version_num'] . '_' . basename(str_replace('\\','/',$ver['stored_path'])));
    exit;
}

// Normal file download
$fid = (int)($_GET['id'] ?? 0);
$f = \Drivew\F::fileFor($fid, $uid);
if (!$f) { http_response_code(404); exit('File not found.'); }
if ((int)$f['is_deleted'] === 1) { http_response_code(404); exit('File is in the trash.'); }

$path = \Drivew\F::resolvePath($f);
if ($path === null) { http_response_code(404); exit('File is missing from disk.'); }

// Log + touch last_downloaded
$stmt = drivew_db()->prepare('UPDATE files SET last_downloaded_at = NOW() WHERE id = ? AND user_id = ?');
$stmt->execute([$fid, $uid]);
\Drivew\F::log($uid, 'download', 'file', $fid, 'Downloaded "' . $f['original_name'] . '"');

sendFile($path, (int)$f['size'], $f['original_name'], $f['mime_type']);
exit;

function sendFile(string $path, int $size, string $filename, ?string $mime = null): void
{
    $mime = $mime ?: (class_exists('finfo') ? (new finfo(FILEINFO_MIME_TYPE))->file($path) : 'application/octet-stream');
    if ($mime === false || $mime === '') $mime = 'application/octet-stream';
    $filename = preg_replace('/[^\w.\- ()]/', '_', $filename);

    header('Content-Type: ' . $mime);
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $size);
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate');

    @readfile($path);
    exit;
}
