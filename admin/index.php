<?php
/**
 * Drivew - Admin dashboard.
 */
require_once __DIR__ . '/includes_admin.php';

$totalUsers = (int)drivew_db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
$activeUsers = (int)drivew_db()->query("SELECT COUNT(*) c FROM users WHERE is_active = 1 AND role = 'user'")->fetch()['c'];
$inactiveUsers = (int)drivew_db()->query("SELECT COUNT(*) c FROM users WHERE is_active = 0")->fetch()['c'];
$totalFiles = (int)drivew_db()->query('SELECT COUNT(*) c FROM files WHERE is_deleted = 0')->fetch()['c'];
$trashCount = (int)drivew_db()->query('SELECT COUNT(*) c FROM files WHERE is_deleted = 1')->fetch()['c'];
$totalStorage = \Drivew\F::totalStorageUsed();

$recentUploads = drivew_db()->query(
    "SELECT f.*, u.display_name AS owner_name FROM files f
     LEFT JOIN users u ON u.id = f.user_id
     WHERE f.is_deleted = 0 ORDER BY f.created_at DESC LIMIT 8"
)->fetchAll();

$recentUsers = drivew_db()->query(
    "SELECT * FROM users ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

$recentActivity = drivew_db()->query(
    "SELECT al.*, u.display_name AS actor FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC LIMIT 12"
)->fetchAll();

$securityEvents = drivew_db()->query(
    "SELECT al.*, u.display_name AS actor FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE al.action IN ('login_failed','login_blocked','permanent_delete','delete_share','rollback_version')
     ORDER BY al.created_at DESC LIMIT 10"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-0"><i class="fa-solid fa-shield-halved me-2 text-danger"></i>System overview</h1>
  <div class="text-secondary">Storage, users, and recent events at a glance.</div></div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary rounded-pill px-3" href="users.php"><i class="fa-solid fa-users me-1"></i>Manage users</a>
    <a class="btn btn-primary rounded-pill px-3" href="user_create.php"><i class="fa-solid fa-user-plus me-1"></i>Add user</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100"><div class="stat-icon" style="background:#eef2ff;color:#4f46e5"><i class="fa-solid fa-users"></i></div>
      <div class="stat-value"><?= $totalUsers ?></div><div class="stat-label">Total users</div>
      <div class="small text-secondary"><?= $activeUsers ?> active · <?= $inactiveUsers ?> inactive</div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100"><div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fa-solid fa-file-lines"></i></div>
      <div class="stat-value"><?= $totalFiles ?></div><div class="stat-label">Files stored</div>
      <div class="small text-secondary"><?= $trashCount ?> in trash</div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100"><div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="fa-solid fa-database"></i></div>
      <div class="stat-value"><?= \Drivew\F::humanSize($totalStorage) ?></div><div class="stat-label">Total storage used</div>
      <div class="small text-secondary">across all users</div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100"><div class="stat-icon" style="background:#fce7f3;color:#db2777"><i class="fa-solid fa-bolt"></i></div>
      <div class="stat-value"><?= $securityEvents ? count($securityEvents) : 0 ?></div><div class="stat-label">Security events (recent)</div>
      <div class="small text-secondary">logins, deletions, rollbacks</div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm rounded-4 h-100"><div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 fw-semibold mb-0">Recent uploads</h2>
      <a href="files.php" class="small">All files →</a></div>
      <?php if (empty($recentUploads)) : ?><div class="text-secondary small">No files yet.</div><?php else : ?>
      <ul class="list-unstyled small">
        <?php foreach ($recentUploads as $f) : ?>
        <li class="d-flex align-items-center gap-2 py-2 border-bottom">
          <i class="fa-solid fa-file text-secondary"></i>
          <span class="text-truncate flex-grow-1"><?= \Drivew\F::esc($f['original_name']) ?></span>
          <span class="text-secondary text-nowrap"><?= \Drivew\F::esc($f['owner_name'] ?: '—') ?></span>
          <span class="text-nowrap small"><?= \Drivew\F::humanSize($f['size']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div></div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm rounded-4 h-100"><div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 fw-semibold mb-0">Security &amp; recent events</h2>
      <a href="logs.php" class="small">Full log →</a></div>
      <?php if (empty($recentActivity)) : ?><div class="text-secondary small">No activity yet.</div><?php else : ?>
      <ul class="list-unstyled small">
        <?php foreach ($recentActivity as $a) : ?>
        <li class="d-flex align-items-start gap-2 py-2 border-bottom">
          <i class="fa-solid fa-circle-dot text-secondary mt-1" style="font-size:.6rem"></i>
          <div class="flex-grow-1">
            <div><strong><?= \Drivew\F::esc($a['actor'] ?: 'system') ?></strong> · <?= \Drivew\F::esc($a['action']) ?></div>
            <div class="text-secondary"><?= \Drivew\F::esc($a['description']) ?></div>
          </div>
          <span class="text-secondary text-nowrap"><?= \Drivew\F::humanTime($a['created_at']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div></div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm rounded-4"><div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 fw-semibold mb-0">Newest users</h2>
      <a href="users.php" class="small">Manage →</a></div>
      <div class="row g-2">
      <?php foreach ($recentUsers as $ru) : ?>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="p-2 rounded-3 bg-light text-center h-100">
            <div class="user-avatar mx-auto mb-1"><?= \Drivew\F::esc(strtoupper(substr(trim($ru['display_name'] ?: $ru['email']),0,2))) ?></div>
            <div class="small fw-semibold text-truncate"><?= \Drivew\F::esc($ru['display_name']) ?></div>
            <div class="text-secondary" style="font-size:.7rem"><?= \Drivew\F::esc($ru['role']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div></div>
  </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
