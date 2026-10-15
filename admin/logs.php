<?php
/**
 * Drivew - Admin: activity / audit log.
 */
require_once __DIR__ . '/includes_admin.php';

$perPage = 40;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$actionFilter = (string)($_GET['action'] ?? '');
$knownActions = ['login','logout','login_failed','login_blocked','upload','download','download_version','create_folder','rename_file','rename_folder','move_file','star','unstar','delete_file','delete_folder','restore_file','restore_folder','permanent_delete','permanent_delete_folder','empty_trash','create_share','enable_share','disable_share','delete_share','upload_revision','rollback_version','change_password','update_settings','create_user','edit_user','activate_user','delete_user','reset_password','create_tag','delete_tag','admin_delete_file','update_profile'];

$where = '';
$params = [];
if (in_array($actionFilter, $knownActions, true)) {
    $where = 'WHERE al.action = ?';
    $params[] = $actionFilter;
}

$total = (int)drivew_db()->query('SELECT COUNT(*) c FROM activity_logs al ' . $where)->fetch()['c'];
$stmt = drivew_db()->prepare(
    "SELECT al.*, u.display_name AS actor FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id $where
     ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();
$totalPages = max(1, (int)ceil($total / $perPage));

// Apply trash retention cleanup once per admin log view (best-effort)
$retentionDays = (int)(\Drivew\F::setting('trash_retention', '30'));
if ($retentionDays > 0) {
    $cutoff = date('Y-m-d H:i:s', time() - $retentionDays * 86400);
    $oldFiles = drivew_db()->prepare('SELECT * FROM files WHERE is_deleted = 1 AND deleted_at < ?');
    $oldFiles->execute([$cutoff]);
    foreach ($oldFiles->fetchAll() as $of) {
        // reuse user-side physical delete
        $cur = UPLOAD_ROOT . '/' . $of['stored_path'];
        if (is_file($cur)) @unlink($cur);
        $vs = drivew_db()->prepare('SELECT stored_path FROM file_versions WHERE file_id = ?');
        $vs->execute([$of['id']]);
        foreach ($vs->fetchAll() as $v) { $vp = UPLOAD_ROOT . '/' . $v['stored_path']; if (is_file($vp)) @unlink($vp); }
        drivew_db()->prepare('DELETE FROM file_tags WHERE file_id = ?')->execute([$of['id']]);
        drivew_db()->prepare("DELETE FROM shares WHERE resource_type='file' AND resource_id=?")->execute([$of['id']]);
        drivew_db()->prepare('DELETE FROM file_versions WHERE file_id = ?')->execute([$of['id']]);
        drivew_db()->prepare('DELETE FROM files WHERE id = ?')->execute([$of['id']]);
    }
    $oldFolders = drivew_db()->prepare('SELECT * FROM folders WHERE is_deleted = 1 AND deleted_at < ?');
    $oldFolders->execute([$cutoff]);
    foreach ($oldFolders->fetchAll() as $of) {
        drivew_db()->prepare("DELETE FROM shares WHERE resource_type='folder' AND resource_id=?")->execute([$of['id']]);
        drivew_db()->prepare('DELETE FROM folders WHERE id = ?')->execute([$of['id']]);
    }
}

$pageTitle = 'Activity log';
$activeNav = 'logs';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-scroll me-2"></i>Activity log</h1>
  <p class="text-secondary mb-0">Audit trail of all significant actions. Retention cleanup runs automatically.</p></div>
  <span class="badge text-bg-light border rounded-pill"><?= $total ?> total events</span>
</div>

<form method="get" class="row g-2 mb-4">
  <div class="col-auto">
    <select name="action" class="form-select form-select-sm">
      <option value="">All actions</option>
      <?php foreach ($knownActions as $a) : ?>
        <option value="<?= $a ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= $a ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary btn-sm rounded-pill px-3">Filter</button></div>
</form>

<div class="card shadow-sm rounded-4 overflow-hidden mb-3">
  <div class="table-responsive">
  <table class="table align-middle mb-0 small">
    <thead class="table-light"><tr><th>When</th><th>Actor</th><th>Action</th><th>Resource</th><th>Description</th><th class="d-none d-lg-table-cell">IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l) : ?>
      <tr>
        <td class="text-secondary text-nowrap"><?= \Drivew\F::humanTime($l['created_at']) ?></td>
        <td><?= \Drivew\F::esc($l['actor'] ?: 'system') ?></td>
        <td><code class="small"><?= \Drivew\F::esc($l['action']) ?></code></td>
        <td class="text-secondary"><?= $l['resource_type'] ? \Drivew\F::esc($l['resource_type']) . ' #' . $l['resource_id'] : '—' ?></td>
        <td class="text-truncate" style="max-width:280px" title="<?= \Drivew\F::esc($l['description']) ?>"><?= \Drivew\F::esc($l['description']) ?></td>
        <td class="d-none d-lg-table-cell text-secondary"><?= \Drivew\F::esc($l['ip_address']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($logs)) : ?><tr><td colspan="6" class="text-center text-secondary py-4">No events recorded.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($totalPages > 1) : ?>
<nav><ul class="pagination justify-content-center">
  <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
    <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="logs.php?page=<?= $i ?>"><?= $i ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
