<?php
/**
 * Drivew - Recent files.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];

$stmt = drivew_db()->prepare(
    'SELECT * FROM files WHERE user_id = ? AND is_deleted = 0
     ORDER BY COALESCE(last_downloaded_at, updated_at) DESC LIMIT 50'
);
$stmt->execute([$uid]);
$recent = $stmt->fetchAll();

$pageTitle = 'Recent';
$activeNav = 'recent';
require __DIR__ . '/includes/header.php';
?>
<div class="mb-4"><h1 class="h3 fw-bold"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent activity</h1>
<p class="text-secondary">Files you've most recently modified or downloaded.</p></div>

<?php if (empty($recent)) : ?>
  <div class="empty-state text-center py-5 card shadow-sm rounded-4">
    <i class="fa-solid fa-clock display-4 text-secondary opacity-50 mb-3"></i>
    <h2 class="h5 fw-semibold">Nothing recent</h2>
    <p class="text-secondary">Files you upload or download will show up here.</p>
    <a href="upload.php" class="btn btn-primary rounded-pill px-4 mt-2"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload file</a>
  </div>
<?php else : ?>
  <div class="card shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>File</th><th class="d-none d-md-table-cell">Location</th><th>Size</th><th>Last activity</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $f) : $last = $f['last_downloaded_at'] ?? $f['updated_at']; ?>
        <tr>
          <td><a class="text-decoration-none" href="preview.php?id=<?= $f['id'] ?>"><i class="fa-solid fa-file me-1 text-secondary"></i><?= \Drivew\F::esc($f['original_name']) ?></a></td>
          <td class="d-none d-md-table-cell small text-secondary"><?= \Drivew\F::esc($f['parent_folder_id'] ? \Drivew\F::folderPath((int)$f['parent_folder_id'], $uid) : 'root') ?></td>
          <td class="small"><?= \Drivew\F::humanSize($f['size']) ?></td>
          <td class="small"><?= $f['last_downloaded_at'] ? 'Downloaded ' . \Drivew\F::humanTime($f['last_downloaded_at']) : \Drivew\F::humanTime($f['updated_at']) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary rounded-pill px-3" href="download.php?id=<?= $f['id'] ?>"><i class="fa-solid fa-download me-1"></i>Download</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
