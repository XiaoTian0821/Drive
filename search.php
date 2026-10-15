<?php
/**
 * Drivew - Search results.
 * Searches the current user's files + folders by name only.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];
$q = trim((string)($_GET['q'] ?? ''));
$files = [];
$folders = [];

if ($q !== '') {
    $like = '%' . addcslashes($q, '_%\\') . '%';
    $st1 = drivew_db()->prepare('SELECT * FROM files WHERE user_id = ? AND is_deleted = 0 AND original_name LIKE ? ORDER BY original_name LIMIT 200');
    $st1->execute([$uid, $like]);
    $files = $st1->fetchAll();

    $st2 = drivew_db()->prepare('SELECT * FROM folders WHERE user_id = ? AND is_deleted = 0 AND name LIKE ? ORDER BY name LIMIT 200');
    $st2->execute([$uid, $like]);
    $folders = $st2->fetchAll();
}

$pageTitle = 'Search';
$activeNav = 'search';
$searchBox = true;
require __DIR__ . '/includes/header.php';
?>
<div class="mb-4">
  <h1 class="h3 fw-bold">Search results</h1>
  <?php if ($q !== '') : ?>
    <p class="text-secondary">Showing matches for <span class="fw-semibold text-dark">"<?= \Drivew\F::esc($q) ?>"</span>
      — <?= count($files) ?> file(s), <?= count($folders) ?> folder(s).</p>
  <?php endif; ?>
</div>

<?php if ($q === '') : ?>
  <div class="empty-state text-center py-5 card shadow-sm rounded-4">
    <i class="fa-solid fa-magnifying-glass display-4 text-secondary opacity-50 mb-3"></i>
    <h2 class="h5 fw-semibold">Search your files &amp; folders</h2>
    <p class="text-secondary">Type something above to find what you're looking for.</p>
  </div>
<?php elseif (empty($files) && empty($folders)) : ?>
  <div class="empty-state text-center py-5 card shadow-sm rounded-4">
    <i class="fa-solid fa-face-meh display-4 text-secondary opacity-50 mb-3"></i>
    <h2 class="h5 fw-semibold">No matches</h2>
    <p class="text-secondary">Nothing named "<?= \Drivew\F::esc($q) ?>" in your storage.</p>
  </div>
<?php else : ?>
  <?php if (!empty($folders)) : ?>
    <h2 class="h6 fw-semibold text-uppercase text-secondary mb-2">Folders</h2>
    <div class="row g-3 mb-4">
      <?php foreach ($folders as $sf) : ?>
        <div class="col-6 col-md-4 col-lg-3">
          <a class="folder-card h-100 text-decoration-none" href="files.php?folder_id=<?= $sf['id'] ?>">
            <div class="folder-glyph"><i class="fa-solid fa-folder"></i></div>
            <div class="folder-name"><?= \Drivew\F::esc($sf['name']) ?></div>
            <div class="folder-meta small text-secondary">Folder · <?= \Drivew\F::esc($sf['parent_id'] ? \Drivew\F::folderPath((int)$sf['parent_id'], $uid) : 'root') ?></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($files)) : ?>
    <h2 class="h6 fw-semibold text-uppercase text-secondary mb-2">Files</h2>
    <div class="card shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Name</th><th class="d-none d-md-table-cell">Location</th><th>Size</th><th class="d-none d-sm-table-cell">Modified</th></tr></thead>
        <tbody>
        <?php foreach ($files as $f) : ?>
          <tr>
            <td><div class="d-flex align-items-center gap-2">
              <i class="fa-solid fa-file-<?= \Drivew\F::fileIcon($f['ext']) === 'file' ? '' : \Drivew\F::fileIcon($f['ext']) ?>"></i>
              <a class="text-decoration-none" href="preview.php?id=<?= $f['id'] ?>"><?= \Drivew\F::esc($f['original_name']) ?></a></div></td>
            <td class="d-none d-md-table-cell small text-secondary"><?= \Drivew\F::esc($f['parent_folder_id'] ? \Drivew\F::folderPath((int)$f['parent_folder_id'], $uid) : 'root') ?></td>
            <td class="small"><?= \Drivew\F::humanSize($f['size']) ?></td>
            <td class="d-none d-sm-table-cell small"><?= date('M j, Y', strtotime($f['updated_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
