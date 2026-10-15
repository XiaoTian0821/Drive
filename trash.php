<?php
/**
 * Drivew - Trash / Recycle bin.
 * Restore files & folders; permanent-delete; empty trash.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    switch ($action) {
        case 'restore_file':
            $f = \Drivew\F::fileFor($id, $uid);
            if ($f && (int)$f['is_deleted'] === 1) {
                // If parent folder is still deleted, restore it too (best effort)
                if ($f['parent_folder_id']) {
                    $pf = \Drivew\F::folderFor((int)$f['parent_folder_id'], $uid);
                    if ($pf && (int)$pf['is_deleted'] === 1) {
                        $stmt = drivew_db()->prepare('UPDATE folders SET is_deleted = 0, deleted_at = NULL, updated_at = NOW() WHERE id = ? AND user_id = ?');
                        $stmt->execute([(int)$f['parent_folder_id'], $uid]);
                    }
                }
                $stmt = drivew_db()->prepare('UPDATE files SET is_deleted = 0, deleted_at = NULL, updated_at = NOW() WHERE id = ? AND user_id = ?');
                $stmt->execute([$id, $uid]);
                \Drivew\F::log($uid, 'restore_file', 'file', $id, 'Restored "' . $f['original_name'] . '"');
                flash('success', 'File restored.', 'rotate-left');
            }
            break;

        case 'restore_folder':
            $fo = \Drivew\F::folderFor($id, $uid);
            if ($fo && (int)$fo['is_deleted'] === 1) {
                // Restore the folder and all its deleted descendants + files
                $stmt = drivew_db()->prepare('UPDATE folders SET is_deleted = 0, deleted_at = NULL, updated_at = NOW() WHERE id = ? AND user_id = ?');
                $queue = [$id];
                $childStmt = drivew_db()->prepare('SELECT id FROM folders WHERE parent_id = ? AND user_id = ?');
                $fileStmt = drivew_db()->prepare('UPDATE files SET is_deleted = 0, deleted_at = NULL, updated_at = NOW() WHERE parent_folder_id = ? AND user_id = ?');
                while ($queue) {
                    $c = array_shift($queue);
                    $stmt->execute([$c, $uid]);
                    $fileStmt->execute([$c, $uid]);
                    $childStmt->execute([$c, $uid]);
                    foreach ($childStmt->fetchAll() as $ch) $queue[] = (int)$ch['id'];
                }
                \Drivew\F::log($uid, 'restore_folder', 'folder', $id, 'Restored folder "' . $fo['name'] . '"');
                flash('success', 'Folder (and contents) restored.', 'rotate-left');
            }
            break;

        case 'permanent_delete_file':
            $f = \Drivew\F::fileFor($id, $uid);
            if ($f && (int)$f['is_deleted'] === 1) {
                permanentDeleteFile($f, $uid);
                \Drivew\F::log($uid, 'permanent_delete', 'file', $id, 'Permanently deleted "' . $f['original_name'] . '"');
                flash('success', 'File permanently deleted.', 'trash-can');
            }
            break;

        case 'permanent_delete_folder':
            $fo = \Drivew\F::folderFor($id, $uid);
            if ($fo && (int)$fo['is_deleted'] === 1) {
                permanentDeleteFolder($fo, $uid);
                \Drivew\F::log($uid, 'permanent_delete_folder', 'folder', $id, 'Permanently deleted folder "' . $fo['name'] . '"');
                flash('success', 'Folder permanently deleted.', 'trash-can');
            }
            break;

        case 'empty_trash':
            $trash = \Drivew\F::trashItems($uid);
            foreach ($trash['files'] as $f) permanentDeleteFile($f, $uid);
            foreach ($trash['folders'] as $fo) permanentDeleteFolder($fo, $uid);
            \Drivew\F::log($uid, 'empty_trash', 'trash', 0, 'Emptied trash');
            flash('success', 'Trash emptied.', 'trash-can');
            break;
    }
    header('Location: trash.php');
    exit;
}

function permanentDeleteFile(array $f, int $uid): void
{
    // Physical: current + all versions
    $cur = UPLOAD_ROOT . '/' . $f['stored_path'];
    if (is_file($cur)) @unlink($cur);
    $vst = drivew_db()->prepare('SELECT stored_path FROM file_versions WHERE file_id = ? AND user_id = ?');
    $vst->execute([$f['id'], $uid]);
    foreach ($vst->fetchAll() as $v) {
        $vp = UPLOAD_ROOT . '/' . $v['stored_path'];
        if (is_file($vp)) @unlink($vp);
    }
    // DB: tags, shares, versions, file row
    drivew_db()->prepare('DELETE FROM file_tags WHERE file_id = ?')->execute([$f['id']]);
    drivew_db()->prepare("DELETE FROM shares WHERE resource_type = 'file' AND resource_id = ?")->execute([$f['id']]);
    drivew_db()->prepare('DELETE FROM file_versions WHERE file_id = ?')->execute([$f['id']]);
    drivew_db()->prepare('DELETE FROM files WHERE id = ? AND user_id = ?')->execute([$f['id'], $uid]);
}

function permanentDeleteFolder(array $fo, int $uid): void
{
    // Delete contained files physically + DB
    $fs = drivew_db()->prepare('SELECT * FROM files WHERE parent_folder_id = ? AND user_id = ? AND is_deleted = 1');
    $fs->execute([$fo['id'], $uid]);
    foreach ($fs->fetchAll() as $f) permanentDeleteFile($f, $uid);
    // Recursively handle deleted sub-folders
    $cs = drivew_db()->prepare('SELECT * FROM folders WHERE parent_id = ? AND user_id = ? AND is_deleted = 1');
    $cs->execute([$fo['id'], $uid]);
    foreach ($cs->fetchAll() as $child) permanentDeleteFolder($child, $uid);
    // Shares for this folder
    drivew_db()->prepare("DELETE FROM shares WHERE resource_type = 'folder' AND resource_id = ?")->execute([$fo['id']]);
    // DB folder row
    drivew_db()->prepare('DELETE FROM folders WHERE id = ? AND user_id = ?')->execute([$fo['id'], $uid]);
}

$trash = \Drivew\F::trashItems($uid);
$emptyTrash = empty($trash['files']) && empty($trash['folders']);

$pageTitle = 'Trash';
$activeNav = 'trash';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-trash-can me-2"></i>Trash</h1>
    <p class="text-secondary mb-0">Deleted items are kept here for safe recovery.</p>
  </div>
  <?php if (!$emptyTrash) : ?>
  <button class="btn btn-danger rounded-pill px-4" onclick="confirmEmptyTrash()"><i class="fa-solid fa-trash-can me-2"></i>Empty trash</button>
  <?php endif; ?>
</div>

<?php if ($emptyTrash) : ?>
  <div class="empty-state text-center py-5 card shadow-sm rounded-4">
    <i class="fa-solid fa-trash display-4 text-secondary opacity-50 mb-3"></i>
    <h2 class="h5 fw-semibold">Your trash is empty</h2>
    <p class="text-secondary">Deleted files and folders will appear here.</p>
  </div>
<?php else : ?>
  <?php if (!empty($trash['folders'])) : ?>
    <h2 class="h6 fw-semibold text-uppercase text-secondary mb-2">Folders</h2>
    <div class="card shadow-sm rounded-4 overflow-hidden mb-4">
      <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Folder</th><th class="d-none d-md-table-cell">Deleted</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($trash['folders'] as $fo) : ?>
          <tr>
            <td><i class="fa-solid fa-folder text-secondary me-2"></i><?= \Drivew\F::esc($fo['name']) ?></td>
            <td class="d-none d-md-table-cell small text-secondary"><?= $fo['deleted_at'] ? date('M j, Y', strtotime($fo['deleted_at'])) : '—' ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="trashAction('restore_folder', <?= $fo['id'] ?>)"><i class="fa-solid fa-rotate-left me-1"></i>Restore</button>
              <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="trashConfirm('permanent_delete_folder', <?= $fo['id'] ?>, '<?= \Drivew\F::esc($fo['name']) ?>')"><i class="fa-solid fa-trash me-1"></i>Delete forever</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($trash['files'])) : ?>
    <h2 class="h6 fw-semibold text-uppercase text-secondary mb-2">Files</h2>
    <div class="card shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>File</th><th class="d-none d-md-table-cell">Original location</th><th class="d-none d-md-table-cell">Size</th><th>Deleted</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($trash['files'] as $f) : ?>
          <tr>
            <td><i class="fa-solid fa-file text-secondary me-2"></i><?= \Drivew\F::esc($f['original_name']) ?></td>
            <td class="d-none d-md-table-cell small text-secondary"><?= \Drivew\F::esc($f['parent_folder_id'] ? \Drivew\F::folderPath((int)$f['parent_folder_id'], $uid) : 'root') ?></td>
            <td class="d-none d-md-table-cell small"><?= \Drivew\F::humanSize($f['size']) ?></td>
            <td class="small text-secondary"><?= $f['deleted_at'] ? date('M j, Y', strtotime($f['deleted_at'])) : '—' ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="trashAction('restore_file', <?= $f['id'] ?>)"><i class="fa-solid fa-rotate-left me-1"></i>Restore</button>
              <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="trashConfirm('permanent_delete_file', <?= $f['id'] ?>, '<?= \Drivew\F::esc($f['original_name']) ?>')"><i class="fa-solid fa-trash me-1"></i>Delete forever</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- Hidden form target for trash POST actions -->
<form id="trashForm" method="post" action="trash.php" class="d-none">
  <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
  <input type="hidden" name="action" id="trashAction">
  <input type="hidden" name="id" id="trashId">
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
function trashAction(action, id){
  document.getElementById('trashAction').value = action;
  document.getElementById('trashId').value = id;
  document.getElementById('trashForm').submit();
}
function trashConfirm(action, id, name){
  if(confirm('Permanently delete "' + name + '"? This cannot be undone.')){
    document.getElementById('trashAction').value = action;
    document.getElementById('trashId').value = id;
    document.getElementById('trashForm').submit();
  }
}
function confirmEmptyTrash(){
  if(confirm('Empty the entire trash? All items will be permanently deleted.')){
    document.getElementById('trashAction').value = 'empty_trash';
    document.getElementById('trashId').value = 0;
    document.getElementById('trashForm').submit();
  }
}
</script>
