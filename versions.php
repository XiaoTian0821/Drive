<?php
/**
 * Drivew - File version history + rollback.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];
$fid = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $action = $_POST['action'] ?? '';
    $verId = (int)($_POST['id'] ?? 0);

    $f = \Drivew\F::fileFor($fid, $uid);
    if ($f === null) { flash('danger', 'File not found.', 'circle-exclamation'); }
    else {
        switch ($action) {
            case 'rollback':
                // Find the target version row
                $vst = drivew_db()->prepare('SELECT * FROM file_versions WHERE id = ? AND user_id = ? AND file_id = ?');
                $vst->execute([$verId, $uid, $fid]);
                $ver = $vst->fetch();
                if ($ver) {
                    $verPath = UPLOAD_ROOT . '/' . $ver['stored_path'];
                    if (is_file($verPath)) {
                        // Make current physical into a version, then promote the target
                        $curPath = UPLOAD_ROOT . '/' . $f['stored_path'];
                        $curRand = bin2hex(random_bytes(8));
                        $curRel = 'u' . $uid . '/v_' . $curRand . '.' . ($f['ext'] ?? 'bin');
                        if (is_file($curPath)) @copy($curPath, UPLOAD_ROOT . '/' . $curRel);

                        $newVerNum = ((int)$f['version_num']) + 1;
                        // Archive current
                        $insV = drivew_db()->prepare('INSERT INTO file_versions (file_id, user_id, version_num, stored_path, size, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                        $insV->execute([$fid, $uid, (int)$f['version_num'], $curRel, (int)$f['size']]);

                        // Promote target to current
                        $upd = drivew_db()->prepare('UPDATE files SET stored_path = ?, size = ?, version_num = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
                        $upd->execute([$ver['stored_path'], $ver['size'], $newVerNum, $fid, $uid]);
                        @unlink($verPath); // consumed
                        \Drivew\F::log($uid, 'rollback_version', 'file', $fid, 'Rolled back to v' . $ver['version_num']);
                        flash('success', 'Rolled back to version ' . $ver['version_num'] . '.', 'rotate-left');
                    }
                } else {
                    flash('danger', 'Version not found.', 'circle-exclamation');
                }
                break;
        }
    }
    header('Location: versions.php?file_id=' . $fid);
    exit;
}

if ($f === null) {
    flash('danger', 'File not found.', 'circle-exclamation');
    header('Location: files.php');
    exit;
}

$versions = \Drivew\F::fileVersions($fid, $uid);

$pageTitle = 'Versions · ' . $f['original_name'];
$activeNav = 'files';
require __DIR__ . '/includes/header.php';
?>
<div class="mb-4">
  <nav aria-label="Breadcrumb"><ol class="breadcrumb list-unstyled d-flex gap-1 small m-0">
    <li><a class="breadcrumb-link" href="files.php">My Files</a></li>
    <li class="text-secondary">/</li>
    <li><a class="breadcrumb-link" href="preview.php?id=<?= $fid ?>"><?= \Drivew\F::esc($f['original_name']) ?></a></li>
    <li class="text-secondary">/</li>
    <li class="fw-semibold">Version history</li>
  </ol></nav>
</div>

<div class="card shadow-sm rounded-4 overflow-hidden">
  <div class="card-body p-4">
    <h1 class="h4 fw-bold"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Version history</h1>
    <p class="text-secondary small">Current version is v<?= (int)$f['version_num'] ?>. Re-uploading a same-named file creates a new version. Rollback promotes an older version to current.</p>

    <ul class="list-unstyled version-list mt-3">
      <li class="d-flex align-items-center justify-content-between gap-2 p-3 rounded-3 bg-primary bg-opacity-10 border">
        <div class="d-flex align-items-center gap-3">
          <i class="fa-solid fa-file-circle-check text-primary fs-4"></i>
          <div>
            <div class="fw-semibold">v<?= (int)$f['version_num'] ?> <span class="badge text-bg-primary ms-1">Current</span></div>
            <div class="small text-secondary"><?= \Drivew\F::humanSize($f['size']) ?> · modified <?= date('M j, Y H:i', strtotime($f['updated_at'])) ?></div>
          </div>
        </div>
        <a class="btn btn-sm btn-primary rounded-pill px-3" href="download.php?id=<?= $fid ?>"><i class="fa-solid fa-download me-1"></i>Download</a>
      </li>

      <?php foreach ($versions as $v) : ?>
      <li class="d-flex align-items-center justify-content-between gap-2 p-3 rounded-3 border mb-2">
        <div class="d-flex align-items-center gap-3">
          <i class="fa-solid fa-file text-secondary fs-4"></i>
          <div>
            <div class="fw-semibold">v<?= (int)$v['version_num'] ?></div>
            <div class="small text-secondary"><?= \Drivew\F::humanSize($v['size']) ?> · saved <?= date('M j, Y H:i', strtotime($v['created_at'])) ?></div>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-primary rounded-pill px-3" href="download.php?v=<?= $v['id'] ?>"><i class="fa-solid fa-download me-1"></i>Download</a>
          <form method="post" action="versions.php?file_id=<?= $fid ?>" onsubmit="return confirm('Roll back to v<?= (int)$v['version_num'] ?>? The current version becomes v<?= (int)$f['version_num'] + 1 ?> (an old copy).')">
            <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
            <input type="hidden" name="action" value="rollback">
            <input type="hidden" name="id" value="<?= $v['id'] ?>">
            <button class="btn btn-sm btn-outline-warning rounded-pill px-3"><i class="fa-solid fa-rotate-left me-1"></i>Roll back</button>
          </form>
        </div>
      </li>
      <?php endforeach; ?>

      <?php if (empty($versions)) : ?>
        <li class="text-secondary small py-3 text-center">No older versions. Upload a same-named file to create one.</li>
      <?php endif; ?>
    </ul>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
