<?php
/**
 * Drivew - Admin: create a new user.
 */
require_once __DIR__ . '/includes_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $name = trim((string)($_POST['display_name'] ?? ''));
    $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $pass = (string)($_POST['password'] ?? '');
    $quotaMB = (int)($_POST['quota_mb'] ?? 1024);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'A valid email is required.', 'circle-exclamation');
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
        flash('danger', 'Display name must be 2–80 characters.', 'circle-exclamation');
    } elseif (strlen($pass) < 8) {
        flash('danger', 'Password must be at least 8 characters.', 'circle-exclamation');
    } else {
        $exists = drivew_db()->prepare('SELECT id FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            flash('danger', 'A user with that email already exists.', 'circle-exclamation');
        } else {
            $quotaBytes = max(0, $quotaMB) * 1048576;
            $stmt = drivew_db()->prepare(
                'INSERT INTO users (email, display_name, password_hash, role, is_active, storage_quota, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, NOW(), NOW())'
            );
            $stmt->execute([$email, $name, password_hash($pass, PASSWORD_DEFAULT), $role, $quotaBytes]);
            $newId = (int)drivew_db()->lastInsertId();
            \Drivew\F::log($uid, 'create_user', 'user', $newId, 'Created ' . $role . ' ' . $email . ' (quota ' . $quotaMB . ' MB)');
            flash('success', 'User created.', 'user-plus');
            header('Location: users.php');
            exit;
        }
    }
    header('Location: user_create.php');
    exit;
}

$flashes = getFlashes();
$pageTitle = 'Add user';
$activeNav = 'users';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-12 col-lg-7 col-xl-6">
<div class="card shadow-sm rounded-4">
  <div class="card-body p-4">
    <div class="mb-4">
      <h1 class="h4 fw-bold"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Create user</h1>
      <p class="text-secondary small">New accounts start active. Set their storage quota and initial password here.</p>
    </div>
    <form method="post" action="user_create.php">
      <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
      <div class="mb-3">
        <label class="form-label fw-semibold small" for="name">Display name</label>
        <input type="text" id="name" name="display_name" class="form-control" required minlength="2" maxlength="80" placeholder="e.g. Jane Doe">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold small" for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" required placeholder="jane@example.com">
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6">
          <label class="form-label fw-semibold small" for="role">Role</label>
          <select id="role" name="role" class="form-select">
            <option value="user">Standard user</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
        <div class="col-6">
          <label class="form-label fw-semibold small" for="quota">Storage quota (MB)</label>
          <input type="number" id="quota" name="quota_mb" class="form-control" value="10240" min="0">
          <div class="form-text">0 = unlimited</div>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold small" for="password">Initial password</label>
        <input type="password" id="password" name="password" class="form-control" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
        <div class="form-text">Share this with the user securely; they can change it from their profile.</div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-plus me-2"></i>Create user</button>
        <a class="btn btn-outline-secondary rounded-pill px-4" href="users.php">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div></div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
