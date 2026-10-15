<?php
/**
 * Drivew - Admin: edit a user (name, email, role, quota, status).
 */
require_once __DIR__ . '/includes_admin.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = drivew_db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$usr = $stmt->fetch();
if (!$usr) { flash('danger', 'User not found.', 'circle-exclamation'); header('Location: users.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $name = trim((string)($_POST['display_name'] ?? ''));
    $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $quotaMB = (int)($_POST['quota_mb'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'A valid email is required.', 'circle-exclamation');
        header('Location: user_edit.php?id=' . $id); exit;
    }
    $dup = drivew_db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $dup->execute([$email, $id]);
    if ($dup->fetch()) {
        flash('danger', 'That email is already used by another user.', 'circle-exclamation');
        header('Location: user_edit.php?id=' . $id); exit;
    }

    // Protect: can't demote/deactivate/delete the last admin (or self)
    $isSelf = $id === $uid;
    $adminCount = (int)drivew_db()->query("SELECT COUNT(*) c FROM users WHERE role = 'admin' AND is_active = 1")->fetch()['c'];
    if ($usr['role'] === 'admin' && $adminCount <= 1 && ($role !== 'admin' || $active !== 1)) {
        flash('danger', 'This is the last active admin — cannot demote or deactivate them.', 'circle-exclamation');
        header('Location: user_edit.php?id=' . $id); exit;
    }
    if ($isSelf && $active !== 1) {
        flash('danger', 'You cannot deactivate your own account from here.', 'circle-exclamation');
        header('Location: user_edit.php?id=' . $id); exit;
    }

    $quotaBytes = max(0, $quotaMB) * 1048576;
    $upd = drivew_db()->prepare(
        'UPDATE users SET email = ?, display_name = ?, role = ?, is_active = ?, storage_quota = ?, updated_at = NOW() WHERE id = ?'
    );
    $upd->execute([$email, $name, $role, $active, $quotaBytes, $id]);
    \Drivew\F::log($uid, 'edit_user', 'user', $id, 'Edited user ' . $email);
    flash('success', 'User updated.', 'user-check');
    header('Location: users.php');
    exit;
}

$pageTitle = 'Edit user · ' . $usr['display_name'];
$activeNav = 'users';
$flashes = getFlashes();
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-12 col-lg-7 col-xl-6">
<div class="card shadow-sm rounded-4">
  <div class="card-body p-4">
    <div class="mb-4">
      <div class="user-avatar-lg mx-auto mb-3" style="width:56px;height:56px;font-size:1.2rem"><?= \Drivew\F::esc(strtoupper(substr(trim($usr['display_name']),0,2))) ?></div>
      <h1 class="h4 fw-bold text-center mb-0">Edit user</h1>
      <p class="text-secondary small text-center"><?= \Drivew\F::esc($usr['email']) ?></p>
    </div>
    <form method="post" action="user_edit.php?id=<?= $id ?>">
      <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
      <div class="mb-3">
        <label class="form-label fw-semibold small">Display name</label>
        <input type="text" name="display_name" class="form-control" value="<?= \Drivew\F::esc($usr['display_name']) ?>" required minlength="2" maxlength="80">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold small">Email</label>
        <input type="email" name="email" class="form-control" value="<?= \Drivew\F::esc($usr['email']) ?>" required>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label fw-semibold small">Role</label>
          <select name="role" class="form-select">
            <option value="user" <?= $usr['role'] === 'user' ? 'selected' : '' ?>>Standard user</option>
            <option value="admin" <?= $usr['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label fw-semibold small">Storage quota (MB)</label>
          <input type="number" name="quota_mb" class="form-control" value="<?= (int)($usr['storage_quota'] / 1048576) ?>" min="0">
          <div class="form-text">0 = unlimited</div>
        </div>
      </div>
      <div class="mb-4 form-check">
        <input type="checkbox" class="form-check-input" name="is_active" id="activeChk" <?= $usr['is_active'] ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold small" for="activeChk">Account active</label>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-check me-2"></i>Save changes</button>
        <a class="btn btn-outline-secondary rounded-pill px-4" href="users.php">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div></div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
