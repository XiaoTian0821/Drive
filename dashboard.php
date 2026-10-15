<?php
/**
 * Drivew - User dashboard.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];
$flashes = getFlashes();

$used = \Drivew\F::storageUsed($uid);
$quota = \Drivew\F::storageQuota($uid);
$pct = \Drivew\F::storagePercent($uid);
$recent = \Drivew\F::recentFiles($uid, 6);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1">Hello, <?= \Drivew\F::esc($u['display_name']) ?> 👋</h1>
    <div class="text-secondary">Here's what's happening in your storage today.</div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="upload.php" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload file</a>
    <button type="button" class="btn btn-outline-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#newFolderModal"><i class="fa-solid fa-folder-plus me-2"></i>New folder</button>
  </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-icon" style="background:#eef2ff;color:#4f46e5"><i class="fa-solid fa-file"></i></div>
      <div class="stat-value"><?= number_format(\Drivew\F::countFiles($uid)) ?></div>
      <div class="stat-label">Files</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i class="fa-solid fa-folder"></i></div>
      <div class="stat-value"><?= number_format(\Drivew\F::countFolders($uid)) ?></div>
      <div class="stat-label">Folders</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fa-solid fa-database"></i></div>
      <div class="stat-value"><?= \Drivew\F::humanSize($used) ?></div>
      <div class="stat-label">Storage used</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-icon" style="background:#fce7f3;color:#db2777"><i class="fa-solid fa-star"></i></div>
      <div class="stat-value">
        <?php $st = \Drivew\F::starredItems($uid); echo count($st['files']) + count($st['folders']); ?>
      </div>
      <div class="stat-label">Starred</div>
    </div>
  </div>
</div>

<!-- Storage meter + quick actions -->
<div class="row g-3 mb-4">
  <div class="col-12 col-lg-7">
    <div class="card shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 fw-semibold mb-0">Storage</h2>
          <span class="badge text-bg-light border"><?= $quota > 0 ? $pct . '% used' : 'Unlimited' ?></span>
        </div>
        <div class="progress" style="height:10px" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar" style="width:<?= min(100,$pct) ?>%;
                background:<?= $pct >= 90 ? '#dc2626' : ($pct >= 70 ? '#d97706' : '#4f46e5') ?>"></div>
        </div>
        <div class="d-flex justify-content-between small text-secondary mt-2">
          <span><?= \Drivew\F::humanSize($used) ?> used</span>
          <span><?= $quota > 0 ? \Drivew\F::humanSize($quota - $used) . ' free of ' . \Drivew\F::humanSize($quota) : 'No limit' ?></span>
        </div>

        <hr class="my-4">
        <div class="d-flex flex-wrap gap-2">
          <a class="quick-action" href="recent.php"><i class="fa-solid fa-clock"></i> Recent</a>
          <a class="quick-action" href="starred.php"><i class="fa-solid fa-star"></i> Starred</a>
          <a class="quick-action" href="trash.php"><i class="fa-solid fa-trash"></i> Trash</a>
          <a class="quick-action" href="tags.php"><i class="fa-solid fa-tags"></i> Tags</a>
          <a class="quick-action" href="share.php"><i class="fa-solid fa-link"></i> Shared</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="card shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h6 fw-semibold mb-0">Recent files</h2>
          <a href="recent.php" class="small text-primary text-decoration-none">View all</a>
        </div>
        <?php if (empty($recent)) : ?>
          <div class="empty-state text-center py-4">
            <i class="fa-solid fa-folder-open display-6 text-secondary opacity-50 mb-3"></i>
            <div class="fw-semibold">No files yet</div>
            <div class="small text-secondary">Upload your first file to get started.</div>
            <a href="upload.php" class="btn btn-sm btn-primary rounded-pill px-3 mt-3"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload</a>
          </div>
        <?php else : ?>
          <ul class="list-unstyled mb-0 file-list">
            <?php foreach ($recent as $f) : ?>
            <li class="file-list-item">
              <div class="file-badge"><?= \Drivew\F::fileIcon($f['ext']) ?></div>
              <div class="flex-grow-1">
                <a href="files.php?file_id=<?= $f['id'] ?>" class="file-link text-truncate d-block"><?= \Drivew\F::esc($f['original_name']) ?></a>
                <div class="small text-secondary"><?= \Drivew\F::humanSize($f['size']) ?> · <?= date('M j, Y', strtotime($f['updated_at'])) ?></div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- New folder modal -->
<div class="modal fade" id="newFolderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-semibold">Create folder</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="post" action="folders.php">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="parent_id" value="">
          <label class="form-label fw-semibold" for="folderName">Folder name</label>
          <input type="text" id="folderName" class="form-control" name="name" required maxlength="180" placeholder="e.g. Documents" autofocus>
          <button class="btn btn-primary w-100 mt-3 rounded-pill">Create folder</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
