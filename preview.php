<?php
/**
 * Drivew - File preview page.
 * Displays inline preview for supported types; fallback message otherwise.
 * All access is auth + ownership gated.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];

$fid = (int)($_GET['id'] ?? 0);
$f = \Drivew\F::fileFor($fid, $uid);
if (!$f || (int)$f['is_deleted'] === 1) {
    flash('danger', 'File not found or unavailable.', 'circle-exclamation');
    header('Location: files.php');
    exit;
}

$path = \Drivew\F::resolvePath($f);
$preview = \Drivew\F::previewType($f['ext']);

// Mark as accessed
$stmt = drivew_db()->prepare('UPDATE files SET updated_at = NOW() WHERE id = ? AND user_id = ?');
$stmt->execute([$fid, $uid]);

$tags = \Drivew\F::fileTags($fid, $uid);
$versions = \Drivew\F::fileVersions($fid, $uid);
$shares = \Drivew\F::fileShares($fid, $uid);

$pageTitle = 'Preview · ' . $f['original_name'];
$activeNav = 'files';
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="Breadcrumb" class="mb-3">
  <ol class="breadcrumb list-unstyled d-flex align-items-center gap-1 small m-0">
    <li><a class="breadcrumb-link" href="files.php">My Files</a></li>
    <li class="text-secondary" aria-hidden="true">/</li>
    <li class="fw-semibold"><?= \Drivew\F::esc($f['original_name']) ?></li>
  </ol>
</nav>

<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card shadow-sm rounded-4 overflow-hidden">
      <div class="preview-pane">
        <?php if ($preview === 'pdf' && $path) : ?>
          <iframe src="<?= \Drivew\F::esc(UPLOAD_ROOT . '/' . $f['stored_path']) ?>" title="<?= \Drivew\F::esc($f['original_name']) ?>" style="width:100%;height:70vh;border:0"></iframe>
        <?php elseif ($preview === 'image' && $path) : ?>
          <img src="<?= \Drivew\F::esc(UPLOAD_ROOT . '/' . $f['stored_path']) ?>" alt="<?= \Drivew\F::esc($f['original_name']) ?>" style="max-width:100%;max-height:70vh;margin:auto;display:block">
        <?php elseif ($preview === 'media' && $path) : ?>
          <div class="text-center p-4">
            <?php if (in_array($f['ext'], ['mp4','webm','ogg'], true)) : ?>
              <video controls class="w-100" style="max-height:60vh" src="<?= \Drivew\F::esc(UPLOAD_ROOT . '/' . $f['stored_path']) ?>"></video>
            <?php else : ?>
              <audio controls class="w-100" src="<?= \Drivew\F::esc(UPLOAD_ROOT . '/' . $f['stored_path']) ?>"></audio>
            <?php endif; ?>
          </div>
        <?php elseif ($preview === 'text' && $path) : ?>
          <pre class="preview-text m-0"><?= htmlspecialchars(substr(file_get_contents($path), 0, 100000), ENT_QUOTES, 'UTF-8') ?></pre>
        <?php else : ?>
          <div class="text-center py-5">
            <i class="fa-solid fa-file-circle-exclamation display-4 text-secondary opacity-50 mb-3"></i>
            <h2 class="h5 fw-semibold">Preview unavailable</h2>
            <p class="text-secondary">You can download this file to open it on your device.</p>
            <a class="btn btn-primary rounded-pill px-4" href="download.php?id=<?= $fid ?>"><i class="fa-solid fa-download me-2"></i>Download</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card shadow-sm rounded-4 mb-3">
      <div class="card-body p-4">
        <h2 class="h6 fw-semibold mb-3">File details</h2>
        <dl class="detail-list small">
          <dt>Name</dt><dd class="text-truncate" title="<?= \Drivew\F::esc($f['original_name']) ?>"><?= \Drivew\F::esc($f['original_name']) ?></dd>
          <dt>Type</dt><dd>
            <?= strtoupper(\Drivew\F::esc($f['ext'])) ?> · <?= \Drivew\F::esc($f['mime_type']) ?><br>
            <span class="text-secondary">Current version: v<?= (int)$f['version_num'] ?></span>
          </dd>
          <dt>Size</dt><dd><?= \Drivew\F::humanSize($f['size']) ?></dd>
          <dt>Owner</dt><dd><?= \Drivew\F::esc($u['display_name']) ?></dd>
          <dt>Folder</dt><dd><?= \Drivew\F::esc($f['parent_folder_id'] ? \Drivew\F::folderPath((int)$f['parent_folder_id'], $uid) : 'My Storage (root)') ?></dd>
          <dt>Created</dt><dd><?= date('M j, Y H:i', strtotime($f['created_at'])) ?></dd>
          <dt>Modified</dt><dd><?= date('M j, Y H:i', strtotime($f['updated_at'])) ?></dd>
          <dt>Starred</dt><dd>
            <label class="form-check-label d-inline-flex align-items-center gap-2">
              <input type="checkbox" class="form-check-input m-0" <?= $f['is_starred'] ? 'checked' : '' ?> onchange="toggleStar(<?= $fid ?>, this.checked)">
              <span><?= $f['is_starred'] ? 'Yes' : 'No' ?></span>
            </label>
          </dd>
        </dl>

        <div class="d-flex gap-2 flex-wrap mt-3">
          <a class="btn btn-primary rounded-pill px-3 py-1 small" href="download.php?id=<?= $fid ?>"><i class="fa-solid fa-download me-1"></i>Download</a>
          <button class="btn btn-outline-primary rounded-pill px-3 py-1 small" data-bs-toggle="modal" data-bs-target="#shareFileModal"><i class="fa-solid fa-share-nodes me-1"></i>Share</button>
          <button class="btn btn-outline-secondary rounded-pill px-3 py-1 small" data-bs-toggle="modal" data-bs-target="#moveFileModal"><i class="fa-solid fa-arrows-turn-right me-1"></i>Move</button>
          <button class="btn btn-outline-danger rounded-pill px-3 py-1 small" onclick="confirmDelete(<?= $fid ?>, '<?= \Drivew\F::esc($f['original_name']) ?>')"><i class="fa-solid fa-trash me-1"></i>Delete</button>
        </div>
      </div>
    </div>

    <?php if (!empty($tags)) : ?>
    <div class="card shadow-sm rounded-4 mb-3">
      <div class="card-body p-4">
        <h2 class="h6 fw-semibold mb-3">Tags</h2>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($tags as $t) : ?>
            <span class="tag-chip" style="background:<?= \Drivew\F::esc($t['color'] ?? '#e2e8f0') ?>;color:<?= contrastText($t['color'] ?? '#e2e8f0') ?>"><?= \Drivew\F::esc($t['name']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (count($versions) > 0) : ?>
    <div class="card shadow-sm rounded-4">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h6 fw-semibold mb-0">Versions</h2>
          <a href="versions.php?file_id=<?= $fid ?>" class="small">Manage →</a>
        </div>
        <ul class="list-unstyled small version-list">
          <li class="d-flex justify-content-between py-1 border-bottom">
            <span class="fw-semibold">v<?= (int)$f['version_num'] ?> <span class="badge text-bg-primary ms-1">Current</span></span>
            <span class="text-secondary"><?= date('M j', strtotime($f['updated_at'])) ?></span>
          </li>
          <?php foreach ($versions as $v) : ?>
          <li class="d-flex justify-content-between py-1 border-bottom">
            <a class="text-decoration-none" href="download.php?v=<?= $v['id'] ?>">v<?= (int)$v['version_num'] ?></a>
            <span class="text-secondary"><?= date('M j, Y', strtotime($v['created_at'])) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Share modal -->
<div class="modal fade" id="shareFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title fw-semibold">Share file</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php if (empty($shares)) : ?>
          <p class="text-secondary small">No share links yet. Create one below.</p>
        <?php else : foreach ($shares as $sh) : ?>
          <div class="share-item d-flex align-items-center gap-2 mb-2 p-2 rounded-3 bg-light">
            <i class="fa-solid fa-link text-primary"></i>
            <code class="small flex-grow-1 text-truncate"><?= \Drivew\F::esc(substr($sh['token'], 0, 16)) ?>…</code>
            <span class="badge text-bg-<?= $sh['is_active'] ? 'success' : 'secondary' ?>"><?= $sh['is_active'] ? 'Active' : 'Inactive' ?></span>
          </div>
        <?php endforeach; endif; ?>
        <form method="post" action="share.php" class="mt-3">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="resource_type" value="file">
          <input type="hidden" name="resource_id" value="<?= $fid ?>">
          <div class="mb-2"><label class="form-label small fw-semibold">Password (optional)</label>
            <input type="password" name="password" class="form-control form-control-sm" placeholder="Leave blank for no password"></div>
          <div class="mb-2"><label class="form-label small fw-semibold">Expires</label>
            <select name="expires" class="form-select form-select-sm">
              <option value="never">Never</option>
              <option value="1">1 day</option>
              <option value="7">7 days</option>
              <option value="30" selected>30 days</option>
              <option value="90">90 days</option>
            </select></div>
          <button class="btn btn-primary w-100 rounded-pill"><i class="fa-solid fa-link me-2"></i>Create share link</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Move modal -->
<div class="modal fade" id="moveFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0"><h5 class="modal-title fw-semibold">Move file</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form method="post" action="files.php">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <input type="hidden" name="action" value="move_file">
          <input type="hidden" name="id" value="<?= $fid ?>">
          <input type="hidden" name="redirect" value="preview.php?id=<?= $fid ?>">
          <label class="form-label fw-semibold">Destination</label>
          <select name="dest" class="form-select">
            <option value="">My Storage (root)</option>
            <?php
              $allFolders = drivew_db()->prepare('SELECT * FROM folders WHERE user_id = ? AND is_deleted = 0 ORDER BY name');
              $allFolders->execute([$uid]);
              foreach ($allFolders->fetchAll() as $af) :
            ?>
              <option value="<?= $af['id'] ?>"><?= \Drivew\F::esc($af['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary w-100 mt-3 rounded-pill">Move here</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
function toggleStar(fid, val){
  const f = new FormData();
  f.append('csrf', window.DRIVEW.csrf);
  f.append('action','star_file');
  f.append('id', fid);
  f.append('redirect', 'preview.php?id=' + fid);
  fetch('files.php', {method:'POST', body:f});
}
function confirmDelete(fid, name){
  if(confirm('Move "' + name + '" to trash?')){
    const f = new FormData();
    f.append('csrf', window.DRIVEW.csrf);
    f.append('action','delete_file');
    f.append('id', fid);
    f.append('redirect', 'preview.php?id=' + fid);
    fetch('files.php', {method:'POST', body:f}).then(() => location.reload());
  }
}
function contrastText(hex){
  if(!hex || hex === '#e2e8f0') return '#334155';
  const c = hex.replace('#','');
  const r = parseInt(c.substr(0,2),16), g = parseInt(c.substr(2,2),16), b = parseInt(c.substr(4,2),16);
  const lum = (0.299*r + 0.587*g + 0.114*b);
  return lum > 150 ? '#111827' : '#ffffff';
}
</script>
