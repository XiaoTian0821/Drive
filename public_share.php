<?php
/**
 * Drivew - Public share landing page.
 * Accessed via a random token (?t=...). No login required.
 * Validates token, active state, expiration, password, resource existence.
 *
 * For folders: list contents + download as ZIP (if ZipArchive available).
 */
require_once __DIR__ . '/includes/functions.php';

$token = trim((string)($_GET['t'] ?? ''));

// If the database is not reachable (pre-install), fall back to a
// "link not found" page rather than a fatal error.
$share = null;
$resource = null;
$dbAvailable = function_exists('drivew_db') && defined('DB_DSN');
if ($token !== '' && $dbAvailable) {
    try {
        $share = \Drivew\F::shareByToken($token);
        $resource = $share ? \Drivew\F::shareResource($share) : null;
    } catch (Throwable $e) {
        $share = null;
        $resource = null;
    }
}

$accessible = false;
$needsPassword = false;
$wrongPassword = false;
$expired = false;

if ($share && $resource) {
    if ((int)$share['is_active'] !== 1) {
        $accessible = false;
    } elseif ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
        $expired = true;
    } else {
        if (!empty($share['password_hash'])) {
            $pw = (string)($_GET['p'] ?? '');
            if ($pw === '') {
                $needsPassword = true;
            } elseif (password_verify($pw, $share['password_hash'])) {
                $accessible = true;
            } else {
                $wrongPassword = true;
            }
        } else {
            $accessible = true;
        }
    }
}

// Record view count when accessible
if ($accessible) {
    drivew_db()->prepare('UPDATE shares SET view_count = view_count + 1 WHERE id = ?')->execute([$share['id']]);
}

// Owner display name
$ownerName = 'Shared by user';
if ($share !== null) {
    $ost = drivew_db()->prepare('SELECT display_name, email FROM users WHERE id = ?');
    $ost->execute([$share['user_id']]);
    $orow = $ost->fetch();
    if ($orow) $ownerName = $orow['display_name'] ?: $orow['email'];
}

$pageTitle = 'Shared item';
// Minimal header (no auth required, no sidebar)
$currentUser = null; $isAdmin = false; $activeNav = '';
$initials = '?';
$displayUser = ['display_name' => '', 'email' => '', 'id' => 0];
$usedBytes = 0; $quota = 0; $usedPct = 0; $usedHuman = '0 B'; $quotaHuman = '0';
$flashes = [];

// Public-share pages render their own minimal shell (below) instead of the full app layout.
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $resource ? \Drivew\F::esc($share['resource_type'] === 'file' ? $resource['original_name'] : $resource['name']) : 'Shared item' ?> · Drivew</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--brand:#4f46e5}
body{font-family:'Inter',system-ui,sans-serif;background:#f8fafc;min-height:100vh;display:grid;place-items:center;padding:1.5rem;color:#0f172a}
.share-card{max-width:640px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:2.5rem;box-shadow:0 20px 50px rgba(15,23,42,.08);text-align:center}
.share-icon{width:80px;height:80px;margin:0 auto 1rem;background:var(--brand);color:#fff;border-radius:20px;display:grid;place-items:center;font-size:2rem;box-shadow:0 12px 30px rgba(79,70,229,.3)}
.brand{display:flex;align-items:center;justify-content:center;gap:.5rem;margin-bottom:1.5rem;color:#64748b}
.brand-mark{width:34px;height:34px;background:var(--brand);color:#fff;border-radius:9px;display:grid;place-items:center}
.meta{display:flex;flex-wrap:wrap;gap:1rem;justify-content:center;margin:1.25rem 0}
.meta .item{font-size:.85rem;color:#334155}
.meta .item .v{font-weight:700;color:#0f172a;display:block}
.meta .item .l{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8}
.btn-primary{background:var(--brand);border-color:var(--brand)}
.btn-primary:hover{background:#4338ca}
.password-form{margin-top:1.25rem}
.password-form input{width:100%;padding:.65rem .85rem;border:1px solid #cbd5e1;border-radius:10px;font-size:.95rem}
.alert{border-radius:10px}
</style></head>
<body>
<a class="brand" href="login.php"><div class="brand-mark"><i class="fa-solid fa-box-archive"></i></div> Drivew</a>

<div class="share-card">

<?php if ($share === null) : ?>
  <div class="share-icon"><i class="fa-solid fa-link-slash"></i></div>
  <h1 class="h4 fw-bold">Link not found</h1>
  <p class="text-secondary">This sharing link is invalid or has been removed.</p>

<?php elseif ($expired) : ?>
  <div class="share-icon" style="background:#dc2626"><i class="fa-solid fa-clock-xmark"></i></div>
  <h1 class="h4 fw-bold">Link expired</h1>
  <p class="text-secondary">This sharing link has expired and no longer provides access.</p>

<?php elseif ((int)$share['is_active'] !== 1 || $resource === null) : ?>
  <div class="share-icon" style="background:#dc2626"><i class="fa-solid fa-ban"></i></div>
  <h1 class="h4 fw-bold">Access disabled</h1>
  <p class="text-secondary">The owner has disabled this link, or the item no longer exists.</p>

<?php elseif ($needsPassword || $wrongPassword) : ?>
  <div class="share-icon"><i class="fa-solid fa-lock"></i></div>
  <h1 class="h4 fw-bold">Protected share</h1>
  <p class="text-secondary mb-3">This resource requires a password to view.</p>
  <?php if ($wrongPassword) : ?>
    <div class="alert alert-danger small py-2 mb-3"><i class="fa-solid fa-circle-xmark me-1"></i>Incorrect password. Please try again.</div>
  <?php endif; ?>
  <form method="get" action="public_share.php" class="password-form">
    <input type="hidden" name="t" value="<?= \Drivew\F::esc($token) ?>">
    <div class="d-flex gap-2">
      <input type="password" name="p" placeholder="Access password" required autocomplete="off" autofocus>
      <button class="btn btn-primary px-4 text-nowrap" type="submit"><i class="fa-solid fa-unlock me-1"></i>Unlock</button>
    </div>
  </form>

<?php else : ?>
  <?php
    $isFile = $share['resource_type'] === 'file';
    $res = $resource;
    $resName = $isFile ? $res['original_name'] : $res['name'];
    $resType = $isFile ? strtoupper($res['ext']) : 'Folder';
    $resSize = $isFile ? \Drivew\F::humanSize($res['size']) : null;
  ?>
  <div class="share-icon"><i class="fa-solid fa-<?= $isFile ? 'file-lines' : 'folder-open' ?>"></i></div>
  <div class="eyebrow small text-primary text-uppercase fw-semibold mb-1"><?= $ownerName ?></div>
  <h1 class="h3 fw-bold text-truncate" title="<?= \Drivew\F::esc($resName) ?>"><?= \Drivew\F::esc($resName) ?></h1>

  <div class="meta">
    <div class="item"><span class="v"><?= \Drivew\F::esc($resType) ?></span><span class="l">Type</span></div>
    <?php if ($resSize) : ?><div class="item"><span class="v"><?= $resSize ?></span><span class="l">Size</span></div><?php endif; ?>
    <div class="item"><span class="v"><?= date('M j, Y', strtotime($share['created_at'])) ?></span><span class="l">Shared</span></div>
    <div class="item"><span class="v"><?= $share['expires_at'] ? date('M j, Y', strtotime($share['expires_at'])) : 'No expiry' ?></span><span class="l">Expires</span></div>
    <div class="item"><span class="v"><?= (int)$share['view_count'] ?></span><span class="l">Views</span></div>
  </div>

  <div class="d-flex gap-2 justify-content-center flex-wrap">
    <?php if ($isFile) : ?>
      <?php
        $previewable = \Drivew\F::isPreviewable($res['ext']);
      ?>
      <a class="btn btn-primary px-4 py-2 rounded-pill" href="public_download.php?t=<?= \Drivew\F::esc($token) ?>&p=<?= \Drivew\F::esc((string)($_GET['p'] ?? '')) ?>"><i class="fa-solid fa-download me-2"></i>Download</a>
      <?php if ($previewable) : ?>
        <a class="btn btn-outline-primary px-4 py-2 rounded-pill" href="public_preview.php?t=<?= \Drivew\F::esc($token) ?>&p=<?= \Drivew\F::esc((string)($_GET['p'] ?? '')) ?>"><i class="fa-solid fa-eye me-2"></i>Preview</a>
      <?php endif; ?>
    <?php else : ?>
      <?php
        $zipOk = class_exists('ZipArchive');
        // Count contents
        $contents = $res ? \Drivew\F::collectFolderFiles($res['id'], $res['user_id']) : [];
      ?>
      <div class="w-100">
        <div class="alert alert-light border small text-start mb-3">
          <div class="fw-semibold mb-2">Folder contents (<?= count($contents) ?> file(s))</div>
          <?php if (empty($contents)) : ?>
            <div class="text-secondary">This folder has no files.</div>
          <?php else : foreach (array_slice($contents, 0, 20) as $cf) : ?>
            <div class="d-flex justify-content-between gap-2 border-bottom py-1">
              <span class="text-truncate"><i class="fa-solid fa-file me-1 text-secondary"></i><?= \Drivew\F::esc($cf['original_name']) ?></span>
              <span class="text-secondary text-nowrap"><?= \Drivew\F::humanSize($cf['size']) ?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php if ($zipOk && count($contents) > 0) : ?>
        <a class="btn btn-primary px-4 py-2 rounded-pill" href="public_download.php?t=<?= \Drivew\F::esc($token) ?>&p=<?= \Drivew\F::esc((string)($_GET['p'] ?? '')) ?>"><i class="fa-solid fa-file-zipper me-2"></i>Download folder (ZIP)</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($share['expires_at']) : ?>
    <p class="small text-secondary mt-3"><i class="fa-solid fa-clock me-1"></i>This link expires on <?= date('M j, Y', strtotime($share['expires_at'])) ?>.</p>
  <?php endif; ?>
<?php endif; ?>

</div>
</body></html>
