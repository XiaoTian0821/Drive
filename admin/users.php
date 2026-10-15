<?php
/**
 * Drivew - Admin user management (list + search).
 */
require_once __DIR__ . '/includes_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['id'] ?? 0);

    // Resolve the target user
    $stmt = drivew_db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();

    if ($target === null) {
        flash('danger', 'User not found.', 'circle-exclamation');
        header('Location: users.php');
        exit;
    }

    // Protect the current admin from self-destructive actions
    $self = (int)$target['id'] === $uid;
    $isLastAdmin = false;
    if ((int)$target['role'] === 1 || $target['role'] === 'admin') {
        $admCount = (int)drivew_db()->query("SELECT COUNT(*) c FROM users WHERE role = 'admin' AND is_active = 1")->fetch()['c'];
        $isLastAdmin = $admCount <= 1 && $target['role'] === 'admin';
    }

    switch ($action) {
        case 'activate':
        case 'deactivate':
            if ($self) {
                flash('danger', 'You cannot deactivate your own account.', 'circle-exclamation');
                break;
            }
            if ($target['role'] === 'admin' && $action === 'deactivate' && $isLastAdmin) {
                flash('danger', 'Cannot deactivate the last active administrator.', 'circle-exclamation');
                break;
            }
            $newState = $action === 'activate' ? 1 : 0;
            drivew_db()->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?')->execute([$newState, $targetId]);
            \Drivew\F::log($uid, 'activate_user', 'user', $targetId, ($newState ? 'Activated' : 'Deactivated') . ' user ' . $target['email']);
            flash('success', 'User ' . ($newState ? 'activated' : 'deactivated') . '.', 'user-check');
            break;

        case 'delete':
            if ($self) {
                flash('danger', 'You cannot delete your own account.', 'circle-exclamation');
                break;
            }
            if ($target['role'] === 'admin' && $isLastAdmin) {
                flash('danger', 'Cannot delete the last administrator. Promote another admin first.', 'circle-exclamation');
                break;
            }
            // Cascade: folders/files via FK. Also clean physical files.
            cleanUserFiles($targetId);
            drivew_db()->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
            \Drivew\F::log($uid, 'delete_user', 'user', $targetId, 'Deleted user ' . $target['email']);
            flash('success', 'User deleted.', 'trash-can');
            break;

        case 'reset_password':
            $newPass = (string)($_POST['new_password'] ?? '');
            if (strlen($newPass) < 8) {
                flash('danger', 'New password must be at least 8 characters.', 'circle-exclamation');
                break;
            }
            drivew_db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
                ->execute([password_hash($newPass, PASSWORD_DEFAULT), $targetId]);
            \Drivew\F::log($uid, 'reset_password', 'user', $targetId, 'Reset password for ' . $target['email']);
            flash('success', 'Password reset. New: ' . $newPass, 'key');
            break;
    }
    header('Location: users.php');
    exit;
}

function cleanUserFiles(int $uid): void
{
    $files = drivew_db()->prepare('SELECT stored_path FROM files WHERE user_id = ?');
    $files->execute([$uid]);
    foreach ($files->fetchAll() as $f) {
        $p = UPLOAD_ROOT . '/' . $f['stored_path'];
        if (is_file($p)) @unlink($p);
    }
    $vers = drivew_db()->prepare('SELECT stored_path FROM file_versions WHERE user_id = ?');
    $vers->execute([$uid]);
    foreach ($vers->fetchAll() as $v) {
        $p = UPLOAD_ROOT . '/' . $v['stored_path'];
        if (is_file($p)) @unlink($p);
    }
    $dir = UPLOAD_ROOT . '/u' . $uid;
    if (is_dir($dir)) {
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        @rmdir($dir);
    }
}

// List users with usage
$q = trim((string)($_GET['q'] ?? ''));
$stmt = drivew_db()->prepare(
    "SELECT u.*, (SELECT COALESCE(SUM(size),0) FROM files f WHERE f.user_id = u.id AND f.is_deleted = 0) AS used_bytes
     FROM users u" . ($q !== '' ? " WHERE u.email LIKE ? OR u.display_name LIKE ?" : "") . " ORDER BY u.created_at DESC"
);
$like = '%' . addcslashes($q, '_%\\') . '%';
$stmt->execute($q !== '' ? [$like, $like] : []);
$users = $stmt->fetchAll();

$pageTitle = 'Users';
$activeNav = 'users';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-users me-2"></i>Users</h1>
  <p class="text-secondary mb-0">Manage accounts, quotas, and access.</p></div>
  <a class="btn btn-primary rounded-pill px-4" href="user_create.php"><i class="fa-solid fa-user-plus me-2"></i>Add user</a>
</div>

<form class="row g-2 mb-4" method="get">
  <div class="col-12 col-md-5">
    <div class="search-box d-flex">
      <i class="fa-solid fa-magnifying-glass search-icon" style="position:static;margin:0 .5rem 0 0"></i>
      <input type="search" name="q" class="form-control" placeholder="Search by name or email" value="<?= \Drivew\F::esc($q) ?>">
    </div>
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary rounded-pill px-3">Search</button></div>
</form>

<div class="card shadow-sm rounded-4 overflow-hidden">
  <div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead class="table-light">
      <tr><th>Name</th><th class="d-none d-lg-table-cell">Email</th><th>Role</th><th>Status</th>
      <th class="d-none d-md-table-cell">Storage</th><th class="d-none d-md-table-cell">Created</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $usr) :
      $used = (int)$usr['used_bytes'];
      $quota = (int)$usr['storage_quota'];
      $pct = $quota > 0 ? min(100, round($used / $quota * 100)) : 0;
    ?>
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="user-avatar" style="width:34px;height:34px;font-size:.75rem"><?= \Drivew\F::esc(strtoupper(substr(trim($usr['display_name']),0,2))) ?></div>
            <span class="fw-semibold"><?= \Drivew\F::esc($usr['display_name']) ?></span>
          </div>
        </td>
        <td class="d-none d-lg-table-cell small text-secondary"><?= \Drivew\F::esc($usr['email']) ?></td>
        <td><span class="badge text-bg-<?= $usr['role'] === 'admin' ? 'danger' : 'primary' ?> rounded-pill"><?= \Drivew\F::esc($usr['role']) ?></span></td>
        <td>
          <span class="badge text-bg-<?= $usr['is_active'] ? 'success' : 'secondary' ?> rounded-pill"><?= $usr['is_active'] ? 'Active' : 'Inactive' ?></span>
          <?php if ((int)$usr['id'] === $uid) : ?><span class="badge text-bg-light border rounded-pill ms-1">You</span><?php endif; ?>
        </td>
        <td class="d-none d-md-table-cell">
          <div class="small text-secondary mb-1"><?= \Drivew\F::humanSize($used) ?><?= $quota > 0 ? ' / ' . \Drivew\F::humanSize($quota) : '' ?></div>
          <?php if ($quota > 0) : ?><div class="progress" style="height:5px"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct >= 90 ? '#dc2626' : '#4f46e5' ?>"></div></div><?php endif; ?>
        </td>
        <td class="d-none d-md-table-cell small text-secondary"><?= date('M j, Y', strtotime($usr['created_at'])) ?></td>
        <td class="text-end">
          <div class="d-inline-flex gap-1">
            <a class="btn btn-sm btn-outline-secondary rounded-pill px-2" href="user_edit.php?id=<?= $usr['id'] ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
            <?php if ($usr['is_active']) : ?>
              <form class="d-inline" method="post" action="users.php" onsubmit="return confirm('Deactivate this user?')">
                <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
                <input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= $usr['id'] ?>">
                <button class="btn btn-sm btn-outline-warning rounded-pill px-2" title="Deactivate"><i class="fa-solid fa-ban"></i></button>
              </form>
            <?php else : ?>
              <form class="d-inline" method="post" action="users.php">
                <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
                <input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $usr['id'] ?>">
                <button class="btn btn-sm btn-outline-success rounded-pill px-2" title="Activate"><i class="fa-solid fa-check"></i></button>
              </form>
            <?php endif; ?>
            <form class="d-inline" method="post" action="users.php">
              <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
              <input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= $usr['id'] ?>">
              <input type="password" name="new_password" minlength="8" required placeholder="New password" class="form-control form-control-sm d-inline w-75" style="width:8.5rem;display:inline-block">
              <button class="btn btn-sm btn-outline-info rounded-pill px-2" title="Reset password" type="submit"><i class="fa-solid fa-key"></i></button>
            </form>
            <form class="d-inline" method="post" action="users.php" onsubmit="return confirm('Delete this user and all their files?')">
              <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $usr['id'] ?>">
              <button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete" <?= ((int)$usr['id'] === $uid || ($usr['role'] === 'admin' && (int)drivew_db()->query("SELECT COUNT(*) c FROM users WHERE role='admin' AND is_active=1")->fetch()['c'] <= 1)) ? 'disabled' : '' ?>><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
