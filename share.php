<?php
/**
 * Drivew - Share link management (create / toggle / delete).
 * Only the owner (or an admin) can manage shares.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];

// Base URL of this app root (works on XAMPP, cPanel subfolders, or domain root)
if (!defined('APP_ROOT')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $root = ($scriptDir === '/' || $scriptDir === '') ? '' : $scriptDir;
    define('APP_ROOT', rtrim($root, '/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    $resType = $_POST['resource_type'] ?? '';
    $resId = (int)($_POST['resource_id'] ?? 0);

    // Ownership check
    $resource = null;
    if ($resType === 'file') $resource = \Drivew\F::fileFor($resId, $uid);
    elseif ($resType === 'folder') $resource = \Drivew\F::folderFor($resId, $uid);
    if ($resource === null) { flash('danger', 'Resource not found.', 'circle-exclamation'); header('Location: share.php'); exit; }

    switch ($action) {
        case 'create':
            $token = bin2hex(random_bytes(32)); // 64 hex chars
            $password = (string)($_POST['password'] ?? '');
            $passHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

            $expires = $_POST['expires'] ?? 'never';
            $expiresAt = null;
            if (is_numeric($expires) && (int)$expires > 0) {
                $expiresAt = date('Y-m-d H:i:s', time() + (int)$expires * 86400);
            } elseif ($expires === 'custom' && !empty($_POST['custom_date'])) {
                $expiresAt = date('Y-m-d 23:59:59', strtotime($_POST['custom_date']));
            }

            $ins = drivew_db()->prepare(
                'INSERT INTO shares (user_id, resource_type, resource_id, token, password_hash, is_active, expires_at, view_count, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, ?, 0, NOW())'
            );
            $ins->execute([$uid, $resType, $resId, $token, $passHash, $expiresAt]);
            \Drivew\F::log($uid, 'create_share', $resType, $resId, 'Created share link for "' . $resource[$resType === 'file' ? 'original_name' : 'name'] . '"');
            $_SESSION['new_share_token'] = $token;
            flash('success', 'Share link created.', 'link');
            break;

        case 'toggle':
            $sid = (int)($_POST['share_id'] ?? 0);
            $st = drivew_db()->prepare('SELECT * FROM shares WHERE id = ? AND user_id = ?');
            $st->execute([$sid, $uid]);
            $share = $st->fetch();
            if ($share) {
                drivew_db()->prepare('UPDATE shares SET is_active = 1 - is_active WHERE id = ? AND user_id = ?')->execute([$sid, $uid]);
                \Drivew\F::log($uid, $share['is_active'] ? 'disable_share' : 'enable_share', 'share', $sid);
                flash('success', $share['is_active'] ? 'Share disabled.' : 'Share enabled.', 'toggle-on');
            }
            break;

        case 'delete':
            $sid = (int)($_POST['share_id'] ?? 0);
            $st = drivew_db()->prepare('SELECT * FROM shares WHERE id = ? AND user_id = ?');
            $st->execute([$sid, $uid]);
            if ($st->fetch()) {
                drivew_db()->prepare('DELETE FROM shares WHERE id = ? AND user_id = ?')->execute([$sid, $uid]);
                \Drivew\F::log($uid, 'delete_share', 'share', $sid);
                flash('success', 'Share link deleted.', 'trash-can');
            }
            break;
    }
    header('Location: share.php');
    exit;
}

// List all of this user's shares
$shares = drivew_db()->prepare('SELECT s.*, u.email AS owner_email FROM shares s LEFT JOIN users u ON u.id = s.user_id WHERE s.user_id = ? ORDER BY s.created_at DESC');
$shares->execute([$uid]);
$shares = $shares->fetchAll();

$pageTitle = 'Shared';
$activeNav = 'shares';
require __DIR__ . '/includes/header.php';
?>
<div class="mb-4">
  <h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-link me-2 text-primary"></i>Shared links</h1>
  <p class="text-secondary mb-0">Public links you've created. Anyone with the link (and password, if set) can access the resource until it expires or you disable it.</p>
</div>

<?php if ($newToken = ($_SESSION['new_share_token'] ?? null)) : ?>
  <div class="alert alert-success shadow-sm rounded-3 d-flex align-items-center gap-2 flex-wrap">
    <i class="fa-solid fa-circle-check"></i>
    <div class="flex-grow-1">
      <div class="fw-semibold">New share link ready</div>
      <code class="small d-block"><?= \Drivew\F::esc(APP_ROOT . '/public_share.php?t=' . $newToken) ?></code>
    </div>
    <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="navigator.clipboard.writeText('<?= APP_ROOT ?>/public_share.php?t=<?= \Drivew\F::esc($newToken) ?>')"><i class="fa-solid fa-copy me-1"></i>Copy</button>
  </div>
  <?php unset($_SESSION['new_share_token']); ?>
<?php endif; ?>

<?php if (empty($shares)) : ?>
  <div class="empty-state text-center py-5 card shadow-sm rounded-4">
    <i class="fa-solid fa-link-slash display-4 text-secondary opacity-50 mb-3"></i>
    <h2 class="h5 fw-semibold">No share links yet</h2>
    <p class="text-secondary">Open a file or folder's preview page and use the Share button to create one.</p>
    <a href="files.php" class="btn btn-primary rounded-pill px-4 mt-2">Browse files</a>
  </div>
<?php else : ?>
  <div class="card shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr><th>Resource</th><th>Link</th><th class="d-none d-md-table-cell">Password</th><th class="d-none d-md-table-cell">Expires</th><th>Views</th><th>Status</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($shares as $sh) :
        $res = \Drivew\F::shareResource($sh);
        $resName = $res ? ($sh['resource_type'] === 'file' ? $res['original_name'] : $res['name']) : '(deleted)';
        $isActive = \Drivew\F::isShareActive($sh, (array)$res);
      ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <i class="fa-solid fa-<?= $sh['resource_type'] === 'file' ? 'file' : 'folder' ?> text-secondary"></i>
              <span class="text-truncate" style="max-width:220px" title="<?= \Drivew\F::esc($resName) ?>"><?= \Drivew\F::esc($resName) ?></span>
            </div>
            <div class="small text-secondary"><?= \Drivew\F::esc($sh['resource_type']) ?></div>
          </td>
          <td><code class="small"><?= \Drivew\F::esc(substr($sh['token'], 0, 12)) ?>…</code></td>
          <td class="d-none d-md-table-cell"><?= $sh['password_hash'] ? '<i class="fa-solid fa-lock text-warning" title="Password protected"></i>' : '<span class="text-secondary">—</span>' ?></td>
          <td class="d-none d-md-table-cell small"><?= $sh['expires_at'] ? date('M j, Y', strtotime($sh['expires_at'])) : 'Never' ?></td>
          <td class="small"><?= (int)$sh['view_count'] ?></td>
          <td><span class="badge text-bg-<?= $isActive ? 'success' : 'secondary' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span></td>
          <td class="text-end">
            <div class="d-inline-flex gap-1">
              <form method="post" action="share.php" class="d-inline">
                <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="resource_type" value="<?= $sh['resource_type'] ?>">
                <input type="hidden" name="resource_id" value="<?= $sh['resource_id'] ?>">
                <input type="hidden" name="share_id" value="<?= $sh['id'] ?>">
                <button class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Toggle"><i class="fa-solid fa-<?= $sh['is_active'] ? 'toggle-off' : 'toggle-on' ?>"></i></button>
              </form>
              <form method="post" action="share.php" class="d-inline" onsubmit="return confirm('Delete this share link?')">
                <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="share_id" value="<?= $sh['id'] ?>">
                <button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete"><i class="fa-solid fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
