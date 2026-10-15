<?php
/**
 * Drivew - Admin: view all files system-wide.
 */
require_once __DIR__ . '/includes_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    $fid = (int)($_POST['id'] ?? 0);
    // Destructive admin action - confirm with a token
    if ($action === 'delete') {
        $f = drivew_db()->prepare('SELECT * FROM files WHERE id = ?');
        $f->execute([$fid]);
        $file = $f->fetch();
        if ($file) {
            $phys = UPLOAD_ROOT . '/' . $file['stored_path'];
            if (is_file($phys)) @unlink($phys);
            drivew_db()->prepare('DELETE FROM file_tags WHERE file_id = ?')->execute([$fid]);
            drivew_db()->prepare("DELETE FROM shares WHERE resource_type='file' AND resource_id = ?")->execute([$fid]);
            drivew_db()->prepare('DELETE FROM files WHERE id = ?')->execute([$fid]);
            \Drivew\F::log($uid, 'admin_delete_file', 'file', $fid, 'Admin permanently deleted "' . $file['original_name'] . '"');
            flash('success', 'File permanently deleted.', 'trash-can');
        }
    }
    header('Location: files.php');
    exit;
}

$perPage = 30;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$ownerFilter = isset($_GET['owner']) ? (int)$_GET['owner'] : 0;
$users = drivew_db()->query('SELECT id, display_name FROM users ORDER BY display_name')->fetchAll();

$where = '';
$params = [];
if ($ownerFilter) { $where = 'WHERE f.user_id = ?'; $params[] = $ownerFilter; }

$total = (int)drivew_db()->query('SELECT COUNT(*) c FROM files f ' . $where)->fetch()['c'];
$stmt = drivew_db()->prepare(
    "SELECT f.*, u.display_name AS owner_name FROM files f LEFT JOIN users u ON u.id = f.user_id
     $where ORDER BY f.created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$files = $stmt->fetchAll();
$totalPages = max(1, (int)ceil($total / $perPage));

$pageTitle = 'System files';
$activeNav = 'files';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-file-lines me-2"></i>System files</h1>
  <p class="text-secondary mb-0">Inspect files across all users. Destructive actions are logged.</p></div>
</div>

<form method="get" class="row g-2 mb-4">
  <div class="col-auto">
    <select name="owner" class="form-select form-select-sm">
      <option value="0">All users</option>
      <?php foreach ($users as $usr) : ?><option value="<?= $usr['id'] ?>" <?= $ownerFilter === (int)$usr['id'] ? 'selected' : '' ?>><?= \Drivew\F::esc($usr['display_name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary btn-sm rounded-pill px-3">Filter</button></div>
  <div class="col text-secondary small d-flex align-items-center">Showing page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> files)</div>
</form>

<div class="card shadow-sm rounded-4 overflow-hidden mb-3">
  <div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead class="table-light"><tr><th>File</th><th>Owner</th><th class="d-none d-md-table-cell">Folder</th><th>Type</th><th>Size</th><th class="d-none d-lg-table-cell">Uploaded</th><th>Status</th><th class="text-end">Action</th></tr></thead>
    <tbody>
    <?php foreach ($files as $f) : ?>
      <tr>
        <td><i class="fa-solid fa-file me-1 text-secondary"></i><span class="text-truncate d-inline-block" style="max-width:200px" title="<?= \Drivew\F::esc($f['original_name']) ?>"><?= \Drivew\F::esc($f['original_name']) ?></span></td>
        <td class="small"><?= \Drivew\F::esc($f['owner_name'] ?: '—') ?></td>
        <td class="d-none d-md-table-cell small text-secondary"><?= $f['parent_folder_id'] ? 'Folder #' . $f['parent_folder_id'] : 'Root' ?></td>
        <td class="small"><?= strtoupper(\Drivew\F::esc($f['ext'])) ?></td>
        <td class="small"><?= \Drivew\F::humanSize($f['size']) ?></td>
        <td class="d-none d-lg-table-cell small text-secondary"><?= date('M j, Y', strtotime($f['created_at'])) ?></td>
        <td><span class="badge text-bg-<?= $f['is_deleted'] ? 'secondary' : 'success' ?> rounded-pill"><?= $f['is_deleted'] ? 'Trashed' : 'Active' ?></span></td>
        <td class="text-end">
          <form class="d-inline" method="post" action="files.php" onsubmit="return confirm('Permanently delete this file for all users? This cannot be undone.')">
            <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $f['id'] ?>">
            <button class="btn btn-sm btn-outline-danger rounded-pill px-2" title="Delete"><i class="fa-solid fa-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($files)) : ?><tr><td colspan="8" class="text-center text-secondary py-4">No files found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($totalPages > 1) : ?>
<nav><ul class="pagination justify-content-center">
  <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
    <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="files.php?page=<?= $i ?><?= $ownerFilter ? '&owner=' . $ownerFilter : '' ?>"><?= $i ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
