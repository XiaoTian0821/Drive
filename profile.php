<?php
/**
 * Drivew - Profile (edit display name, change password).
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim((string)($_POST['display_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            flash('danger', 'Display name must be 2–80 characters.', 'circle-exclamation');
        } else {
            // If email changed, ensure uniqueness
            $stmt = drivew_db()->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $stmt->execute([$email, $uid]);
            if ($stmt->fetch()) {
                flash('danger', 'That email is already in use.', 'circle-exclamation');
            } else {
                $upd = drivew_db()->prepare('UPDATE users SET display_name = ?, email = ?, updated_at = NOW() WHERE id = ?');
                $upd->execute([$name, $email, $uid]);
                \Drivew\F::log($uid, 'update_profile', 'user', $uid, 'Updated profile');
                flash('success', 'Profile updated.', 'circle-check');
            }
        }
        header('Location: profile.php');
        exit;
    }

    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $new2 = (string)($_POST['confirm_password'] ?? '');

        $stmt = drivew_db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!password_verify($current, $row['password_hash'])) {
            flash('danger', 'Current password is incorrect.', 'circle-exclamation');
        } elseif (strlen($new) < 8) {
            flash('danger', 'New password must be at least 8 characters.', 'circle-exclamation');
        } elseif ($new !== $new2) {
            flash('danger', 'New passwords do not match.', 'circle-exclamation');
        } else {
            $upd = drivew_db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            \Drivew\F::log($uid, 'change_password', 'user', $uid, 'Changed password');
            flash('success', 'Password changed. Use it next time you sign in.', 'key');
        }
        header('Location: profile.php');
        exit;
    }
}

$flashes = getFlashes();
$pageTitle = 'Profile';
$activeNav = 'profile';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm rounded-4 h-100">
      <div class="card-body p-4 text-center">
        <div class="user-avatar-lg mx-auto mb-3"><?= \Drivew\F::esc(strtoupper(substr(trim($u['display_name'] ?: $u['email']), 0, 2))) ?></div>
        <h2 class="h5 fw-bold mb-1"><?= \Drivew\F::esc($u['display_name']) ?></h2>
        <div class="text-secondary small"><?= \Drivew\F::esc($u['email']) ?></div>
        <div class="mt-3 d-flex justify-content-center gap-2">
          <span class="badge text-bg-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?> rounded-pill"><?= $u['role'] === 'admin' ? 'Administrator' : 'Standard user' ?></span>
        </div>
        <hr>
        <div class="text-start small">
          <div class="d-flex justify-content-between py-1"><span class="text-secondary">Storage used</span><span class="fw-semibold"><?= \Drivew\F::humanSize(\Drivew\F::storageUsed($uid)) ?></span></div>
          <div class="d-flex justify-content-between py-1"><span class="text-secondary">Storage limit</span><span class="fw-semibold"><?= \Drivew\F::storageQuota($uid) > 0 ? \Drivew\F::humanSize(\Drivew\F::storageQuota($uid)) : 'Unlimited' ?></span></div>
          <div class="d-flex justify-content-between py-1"><span class="text-secondary">Member since</span><span class="fw-semibold"><?= date('M Y', strtotime($u['created_at'])) ?></span></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card shadow-sm rounded-4 mb-3">
      <div class="card-body p-4">
        <h1 class="h5 fw-bold mb-3">Account details</h1>
        <form method="post" action="profile.php">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <input type="hidden" name="action" value="update_profile">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold small" for="displayName">Display name</label>
              <input type="text" id="displayName" name="display_name" class="form-control" value="<?= \Drivew\F::esc($u['display_name']) ?>" required minlength="2" maxlength="80">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold small" for="email">Email</label>
              <input type="email" id="email" name="email" class="form-control" value="<?= \Drivew\F::esc($u['email']) ?>" required>
            </div>
          </div>
          <button class="btn btn-primary rounded-pill px-4 mt-3">Save changes</button>
        </form>
      </div>
    </div>

    <div class="card shadow-sm rounded-4" id="change-password">
      <div class="card-body p-4">
        <h1 class="h5 fw-bold mb-1">Change password</h1>
        <p class="text-secondary small mb-3">Choose a strong password of at least 8 characters.</p>
        <form method="post" action="profile.php">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <input type="hidden" name="action" value="change_password">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold small" for="curPw">Current password</label>
              <input type="password" id="curPw" name="current_password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold small" for="newPw">New password</label>
              <input type="password" id="newPw" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold small" for="newPw2">Confirm new password</label>
              <input type="password" id="newPw2" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
            </div>
          </div>
          <button class="btn btn-primary rounded-pill px-4 mt-3"><i class="fa-solid fa-key me-2"></i>Update password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
