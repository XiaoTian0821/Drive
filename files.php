<?php
/**
 * Drivew - File explorer (grid + list views, upload, move, rename, star, delete).
 * This is the primary working interface.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];

// --- POST actions (rename / star / move / delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'rename_file':
            $fid = (int)($_POST['id'] ?? 0);
            $f = \Drivew\F::fileFor($fid, $uid);
            if ($f === null) { flash('danger', 'File not found.', 'circle-exclamation'); break; }
            $newName = trim((string)($_POST['name'] ?? ''));
            if ($newName === '' || mb_strlen($newName) > 255) {
                flash('danger', 'Invalid file name.', 'circle-exclamation'); break;
            }
            $stmt = drivew_db()->prepare('UPDATE files SET original_name = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$newName, $fid, $uid]);
            \Drivew\F::log($uid, 'rename_file', 'file', $fid, 'Renamed file to "' . $newName . '"');
            flash('success', 'File renamed.', 'pen');
            break;

        case 'star_file':
            $fid = (int)($_POST['id'] ?? 0);
            $f = \Drivew\F::fileFor($fid, $uid);
            if ($f === null) break;
            $wasStarred = (int)$f['is_starred'];
            $stmt = drivew_db()->prepare('UPDATE files SET is_starred = 1 - is_starred, updated_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$fid, $uid]);
            \Drivew\F::log($uid, $wasStarred ? 'unstar' : 'star', 'file', $fid, ($wasStarred ? 'Unstarred' : 'Starred') . ' "' . $f['original_name'] . '"');
            flash('success', $wasStarred ? 'Removed from starred.' : 'Added to starred.', 'star');
            break;

        case 'move_file':
            $fid = (int)($_POST['id'] ?? 0);
            $destId = !empty($_POST['dest']) ? (int)$_POST['dest'] : null;
            $f = \Drivew\F::fileFor($fid, $uid);
            if ($f === null) { flash('danger', 'File not found.', 'circle-exclamation'); break; }
            if ($destId !== null) {
                $dest = \Drivew\F::folderFor($destId, $uid);
                if ($dest === null || $dest['is_deleted']) {
                    flash('danger', 'Destination folder not available.', 'circle-exclamation'); break;
                }
            }
            $stmt = drivew_db()->prepare('UPDATE files SET parent_folder_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$destId, $fid, $uid]);
            \Drivew\F::log($uid, 'move_file', 'file', $fid, 'Moved "' . $f['original_name'] . '"');
            flash('success', 'File moved.', 'arrows-turn-right');
            break;

        case 'delete_file':
            $fid = (int)($_POST['id'] ?? 0);
            $f = \Drivew\F::fileFor($fid, $uid);
            if ($f === null) { flash('danger', 'File not found.', 'circle-exclamation'); break; }
            $stmt = drivew_db()->prepare('UPDATE files SET is_deleted = 1, deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ?');
            $stmt->execute([$fid, $uid]);
            \Drivew\F::log($uid, 'delete_file', 'file', $fid, 'Deleted "' . $f['original_name'] . '"');
            flash('success', 'File moved to trash.', 'trash-can');
            break;
    }
    header('Location: ' . ($_POST['redirect'] ?? 'files.php'));
    exit;
}

// --- GET state ---
$viewMode = ($_GET['view'] ?? 'grid') === 'list' ? 'list' : 'grid';
$folderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

if ($folderId !== null) {
    $folder = \Drivew\F::folderFor($folderId, $uid);
    if ($folder === null || $folder['is_deleted']) { $folderId = null; $folder = null; }
} else {
    $folder = null;
}
$crumbs = $folder ? \Drivew\F::breadcrumb($folder['id'], $uid) : [];
$folderName = $folder ? $folder['name'] : 'My Storage';

// List files in current folder
$stmt = drivew_db()->prepare(
    'SELECT * FROM files WHERE user_id = ? AND is_deleted = 0
     AND (' . ($folderId !== null ? 'parent_folder_id = ?' : 'parent_folder_id IS NULL') . ')
     ORDER BY original_name'
);
$stmt->execute($folderId !== null ? [$uid, $folderId] : [$uid]);
$files = $stmt->fetchAll();

// Sub-folders
$stmt2 = drivew_db()->prepare('SELECT * FROM folders WHERE user_id = ? AND is_deleted = 0 AND parent_id ' . ($folderId !== null ? '= ?' : 'IS NULL'));
$stmt2->execute($folderId !== null ? [$uid, $folderId] : [$uid]);
$subfolders = $stmt2->fetchAll();

// All folders for move dialog (tree)
$allFolders = drivew_db()->prepare('SELECT * FROM folders WHERE user_id = ? AND is_deleted = 0 ORDER BY name');
$allFolders->execute([$uid]);
$allFolders = $allFolders->fetchAll();

$pageTitle = $folder ? $folder['name'] : 'My Files';
$activeNav = 'files';
$searchBox = true;
require __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumbs -->
<nav aria-label="Breadcrumb" class="mb-3">
  <ol class="breadcrumb list-unstyled d-flex align-items-center gap-1 small m-0">
    <li><a class="breadcrumb-link" href="files.php">My Storage</a></li>
    <?php foreach ($crumbs as $c) : ?>
      <?php if ($c['id'] !== ($folderId ?? 0)) : ?>
        <li class="text-secondary" aria-hidden="true">/</li>
        <li><a class="breadcrumb-link" href="files.php?folder_id=<?= $c['id'] ?>"><?= \Drivew\F::esc($c['name']) ?></a></li>
      <?php else : ?>
        <li class="text-secondary" aria-hidden="true">/</li>
        <li class="fw-semibold text-primary"><?= \Drivew\F::esc($c['name']) ?></li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ol>
</nav>

<!-- Toolbar -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <div class="d-flex align-items-center gap-2">
    <h1 class="h4 fw-bold mb-0"><i class="fa-solid fa-folder-open me-2 text-primary"></i><?= \Drivew\F::esc($folderName) ?></h1>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a href="upload.php<?= $folderId ? '?folder_id=' . $folderId : '' ?>" class="btn btn-primary rounded-pill px-3 py-2"><i class="fa-solid fa-cloud-arrow-up me-1"></i><span class="d-none d-sm-inline">Upload</span></a>
    <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-2" data-bs-toggle="modal" data-bs-target="#newFolderModal"><i class="fa-solid fa-folder-plus me-1"></i><span class="d-none d-sm-inline">New folder</span></button>
    <div class="btn-group" role="group" aria-label="View mode">
      <button class="btn btn-outline-secondary rounded-pill py-2 px-3 <?= $viewMode === 'grid' ? 'active' : '' ?>" data-view="grid" aria-label="Grid view"><i class="fa-solid fa-table-cells"></i></button>
      <button class="btn btn-outline-secondary rounded-pill py-2 px-3 <?= $viewMode === 'list' ? 'active' : '' ?>" data-view="list" aria-label="List view"><i class="fa-solid fa-list"></i></button>
    </div>
  </div>
</div>

<?php if (empty($files) && empty($subfolders)) : ?>
  <div class="empty-state text-center py-5 card shadow-sm rounded-4">
    <i class="fa-solid fa-inbox display-4 text-secondary opacity-50 mb-3"></i>
    <h2 class="h5 fw-semibold">This folder is empty</h2>
    <p class="text-secondary">Upload files or create a sub-folder to organize your content.</p>
    <a href="upload.php<?= $folderId ? '?folder_id=' . $folderId : '' ?>" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload file</a>
  </div>
<?php endif; ?>

<!-- Sub-folders -->
<?php if (!empty($subfolders)) : ?>
  <div class="row g-3 mb-4">
    <?php foreach ($subfolders as $sf) : ?>
    <div class="col-6 col-md-4 col-lg-3">
      <a class="folder-card h-100 text-decoration-none" href="files.php?folder_id=<?= $sf['id'] ?>">
        <div class="folder-glyph" <?= $sf['color'] ? 'style="background:' . \Drivew\F::esc($sf['color']) . '"' : '' ?>>
          <i class="fa-solid fa-folder"></i>
        </div>
        <div class="folder-name d-flex align-items-center gap-2">
          <?= \Drivew\F::esc($sf['name']) ?>
          <?php if ($sf['is_starred']) : ?><i class="fa-solid fa-star text-warning small" title="Starred"></i><?php endif; ?>
        </div>
        <div class="folder-meta small text-secondary">Folder</div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Files: grid or list -->
<?php if (!empty($files)) : ?>
  <?php if ($viewMode === 'grid') : ?>
    <div class="row g-3" data-view-container="grid">
      <?php foreach ($files as $f) : ?>
        <div class="col-6 col-md-4 col-lg-3" data-file-card="<?= $f['id'] ?>">
          <div class="file-card h-100 position-relative">
            <?php if ($f['is_starred']) : ?><span class="star-badge"><i class="fa-solid fa-star"></i></span><?php endif; ?>
            <a class="file-thumb" href="preview.php?id=<?= $f['id'] ?>" aria-label="Preview <?= \Drivew\F::esc($f['original_name']) ?>">
              <i class="fa-solid fa-<?= \Drivew\F::fileIcon($f['ext']) === 'file' ? 'file-lines' : ($f['ext'] === 'pdf' ? 'file-pdf' : ($f['ext'] === 'txt' || \Drivew\F::previewType($f['ext']) === 'text' ? 'file-lines' : 'file')) ?>"></i>
            </a>
            <div class="file-card-body">
              <a class="file-title text-truncate d-block text-decoration-none" href="files.php?file_id=<?= $f['id'] ?>" title="<?= \Drivew\F::esc($f['original_name']) ?>">
                <?= \Drivew\F::esc($f['original_name']) ?>
              </a>
              <div class="file-meta small text-secondary"><?= \Drivew\F::humanSize($f['size']) ?> · <?= date('M j', strtotime($f['updated_at'])) ?></div>
            </div>
            <div class="file-card-actions" data-file-id="<?= $f['id'] ?>" data-name="<?= \Drivew\F::esc($f['original_name']) ?>">
              <button class="icon-btn" data-file-action="download" title="Download"><i class="fa-solid fa-download"></i></button>
              <button class="icon-btn" data-file-action="rename" title="Rename"><i class="fa-solid fa-pen"></i></button>
              <button class="icon-btn" data-file-action="move" title="Move"><i class="fa-solid fa-arrows-turn-right"></i></button>
              <button class="icon-btn" data-file-action="star" title="Star"><i class="fa-solid fa-star"></i></button>
              <button class="icon-btn" data-file-action="share" title="Share"><i class="fa-solid fa-share-nodes"></i></button>
              <button class="icon-btn danger" data-file-action="delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div class="card shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 file-table">
        <thead class="table-light">
          <tr>
            <th style="width:45%">Name</th>
            <th class="d-none d-md-table-cell">Type</th>
            <th class="d-none d-md-table-cell">Size</th>
            <th>Modified</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($files as $f) : ?>
          <tr data-file-row="<?= $f['id'] ?>">
            <td>
              <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-file-<?= \Drivew\F::fileIcon($f['ext']) === 'file' ? '' : \Drivew\F::fileIcon($f['ext']) . '  fa-fw' ?>" style="width:20px;text-align:center" aria-hidden="true"></i>
                <a class="text-decoration-none text-truncate d-inline-block max-w-250" href="files.php?file_id=<?= $f['id'] ?>" title="<?= \Drivew\F::esc($f['original_name']) ?>">
                  <?= \Drivew\F::esc($f['original_name']) ?>
                </a>
                <?php if ($f['is_starred']) : ?><i class="fa-solid fa-star text-warning small"></i><?php endif; ?>
              </div>
            </td>
            <td class="d-none d-md-table-cell text-secondary small"><?= strtoupper($f['ext']) ?></td>
            <td class="d-none d-md-table-cell small"><?= \Drivew\F::humanSize($f['size']) ?></td>
            <td class="small"><?= date('M j, Y H:i', strtotime($f['updated_at'])) ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1" data-file-id="<?= $f['id'] ?>" data-name="<?= \Drivew\F::esc($f['original_name']) ?>">
                <button class="icon-btn" data-file-action="download" title="Download"><i class="fa-solid fa-download"></i></button>
                <button class="icon-btn" data-file-action="rename" title="Rename"><i class="fa-solid fa-pen"></i></button>
                <button class="icon-btn" data-file-action="move" title="Move"><i class="fa-solid fa-arrows-turn-right"></i></button>
                <button class="icon-btn" data-file-action="star" title="Star"><i class="fa-solid fa-star"></i></button>
                <button class="icon-btn" data-file-action="share" title="Share"><i class="fa-solid fa-share-nodes"></i></button>
                <button class="icon-btn danger" data-file-action="delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- New Folder modal -->
<div class="modal fade" id="newFolderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title fw-semibold">New folder</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="post" action="folders.php">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="parent_id" value="<?= $folderId ? (int)$folderId : '' ?>">
          <label class="form-label fw-semibold">Name</label>
          <input type="text" name="name" class="form-control" required maxlength="180" autofocus placeholder="Folder name">
          <button class="btn btn-primary w-100 mt-3 rounded-pill">Create</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Rename File modal -->
<div class="modal fade" id="renameModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title fw-semibold">Rename file</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="post" action="files.php" id="renameForm">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <input type="hidden" name="action" value="rename_file">
          <input type="hidden" name="id" id="renameId">
          <input type="hidden" name="redirect" value="files.php<?= $folderId ? '?folder_id=' . $folderId : '' ?>">
          <label class="form-label fw-semibold">New name</label>
          <input type="text" name="name" id="renameInput" class="form-control" required maxlength="255">
          <button class="btn btn-primary w-100 mt-3 rounded-pill">Rename</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Move File modal -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title fw-semibold">Move file</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="post" action="files.php">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <input type="hidden" name="action" value="move_file">
          <input type="hidden" name="id" id="moveId">
          <input type="hidden" name="redirect" value="files.php<?= $folderId ? '?folder_id=' . $folderId : '' ?>">
          <label class="form-label fw-semibold">Destination folder</label>
          <select name="dest" class="form-select">
            <option value="">My Storage (root)</option>
            <?php foreach ($allFolders as $af) : ?>
              <option value="<?= $af['id'] ?>" <?= $af['id'] == $folderId ? '' : '' ?>>
                <?= str_repeat('— ', 0) . \Drivew\F::esc($af['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary w-100 mt-3 rounded-pill">Move here</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
