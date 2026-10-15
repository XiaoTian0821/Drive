<?php
/**
 * Drivew - Public preview (token-gated).
 * Only previewable files; otherwise falls back to the share page.
 */
require_once __DIR__ . '/includes/functions.php';

$token = trim((string)($_GET['t'] ?? ''));
$p = (string)($_GET['p'] ?? '');
$dbOk = function_exists('drivew_db') && defined('DB_DSN');

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

function publicGated2(?array $share, ?array $resource, string $p): bool
{
    if ($share === null || $resource === null) return false;
    if ((int)$share['is_active'] !== 1) return false;
    if ($share['expires_at'] && strtotime($share['expires_at']) < time()) return false;
    if (!empty($share['password_hash'])) {
        if ($p === '' || !password_verify($p, $share['password_hash'])) return false;
    }
    return true;
}

if (!publicGated2($share, $resource, $p) || $share['resource_type'] !== 'file') {
    header('Location: public_share.php?t=' . urlencode($token));
    exit;
}

$f = $resource;
$path = \Drivew\F::resolvePath($f);
if ($path === null || !\Drivew\F::isPreviewable($f['ext'])) {
    header('Location: public_share.php?t=' . urlencode($token));
    exit;
}

$preview = \Drivew\F::previewType($f['ext']);
$mime = $f['mime_type'];
$safe = \Drivew\F::esc($f['original_name']);
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Preview · <?= $safe ?> · Drivew</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>body{font-family:system-ui,sans-serif;margin:0;background:#f8fafc}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:.75rem 1.25rem;background:#fff;border-bottom:1px solid #e2e8f0}
.topbar a{color:#4f46e5;font-weight:600;text-decoration:none}
.preview{max-width:960px;margin:1rem auto;padding:0 1rem}
iframe,img,video{width:100%;max-height:70vh;border:0;border-radius:12px;margin:auto;display:block}
pre{white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#e2e8f0;padding:1.25rem;border-radius:12px;font-size:.85rem;max-height:70vh;overflow:auto}</style>
</head><body>
<div class="topbar">
  <span><i class="fa-solid fa-box-archive me-1" style="color:#4f46e5"></i> Drivew preview</span>
  <a href="public_share.php?t=<?= \Drivew\F::esc($token) ?>&p=<?= \Drivew\F::esc($p) ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
</div>
<div class="preview">
<?php if ($preview === 'pdf') : ?>
  <iframe src="<?= \Drivew\F::esc(UPLOAD_ROOT . '/' . $f['stored_path']) ?>" title="<?= $safe ?>"></iframe>
<?php elseif ($preview === 'image') : ?>
  <img src="<?= \Drivew\F::esc(UPLOAD_ROOT . '/' . $f['stored_path']) ?>" alt="<?= $safe ?>">
<?php elseif ($preview === 'media') : ?>
  <?php if (in_array($f['ext'], ['mp4','webm','ogg'], true)) : ?>
    <video controls src="<?= \Drivew\F::esc(UPLOAD_ROOT . '/' . $f['stored_path']) ?>"></video>
  <?php else : ?>
    <audio controls src="<?= \Drivew\F::esc(UPLOAD_ROOT . '/' . $f['stored_path']) ?>"></audio>
  <?php endif; ?>
<?php elseif ($preview === 'text') : ?>
  <pre><?= htmlspecialchars(substr(file_get_contents($path), 0, 100000), ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif; ?>
</div>
</body></html>
