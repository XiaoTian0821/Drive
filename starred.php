<?php
/**
 * Drivew - Starred items.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];
$items = \Drivew\F::starredItems($uid);

$pageTitle = 'Starred';
$activeNav = 'starred';
require __DIR__ . '/includes/header.php';
?>
<div class="mb-4"><h1 class="h3 fw-bold"><i class="fa-solid fa-star me-2 text-warning"></i>Starred items</h1>
<p class="text-secondary">Files and folders you've marked for quick access.</p></div>

<?php if (empty($items['files']) && empty($items['folders'])) : ?>
  <div class="empty-state text-center py-5 card shadow-sm rounded-4">
    <i class="fa-solid fa-star display-4 text-warning opacity-50 mb-3"></i>
    <h2 class="h5 fw-semibold">No starred items yet</h2>
    <p class="text-secondary">Star important files and folders to pin them here.</p>
    <a href="files.php" class="btn btn-primary rounded-pill px-4 mt-2">Browse files</a>
  </div>
<?php else : ?>
  <?php if (!empty($items['folders'])) : ?>
    <h2 class="h6 fw-semibold text-uppercase text-secondary mb-2">Folders</h2>
    <div class="row g-3 mb-4">
      <?php foreach ($items['folders'] as $sf) : ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="folder-card h-100">
            <a class="text-decoration-none" href="files.php?folder_id=<?= $sf['id'] ?>">
              <div class="folder-glyph" <?= $sf['color'] ? 'style="background:' . \Drivew\F::esc($sf['color']) . '"' : '' ?>><i class="fa-solid fa-folder"></i></div>
              <div class="folder-name d-flex align-items-center justify-content-between">
                <span class="text-truncate"><?= \Drivew\F::esc($sf['name']) ?></span>
                <i class="fa-solid fa-star text-warning" title="Unstar"></i>
              </div>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($items['files'])) : ?>
    <h2 class="h6 fw-semibold text-uppercase text-secondary mb-2">Files</h2>
    <div class="row g-3">
      <?php foreach ($items['files'] as $f) : ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="file-card h-100 position-relative">
            <span class="star-badge"><i class="fa-solid fa-star"></i></span>
            <a class="file-thumb" href="preview.php?id=<?= $f['id'] ?>"><i class="fa-solid fa-file-<?= \Drivew\F::fileIcon($f['ext']) === 'file' ? 'lines' : \Drivew\F::fileIcon($f['ext']) ?>"></i></a>
            <div class="file-card-body">
              <a class="file-title text-truncate d-block text-decoration-none" href="preview.php?id=<?= $f['id'] ?>"><?= \Drivew\F::esc($f['original_name']) ?></a>
              <div class="file-meta small text-secondary"><?= \Drivew\F::humanSize($f['size']) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
