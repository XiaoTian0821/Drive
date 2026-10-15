<?php
/**
 * Drivew - File upload handler + UI.
 * Supports single + multiple, drag-and-drop, progress, quota, MIME,
 * extension whitelist, and version creation when overwriting a name.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];
$settings = \Drivew\F::settings();

$allowed = array_filter(array_map('strtolower', explode(',', $settings['allowed_extensions'] ?? '')));
$maxBytes = ((int)($settings['max_upload_mb'] ?? 100)) * 1048576;
if ($maxBytes <= 0) $maxBytes = MAX_UPLOAD_BYTES;

$dangerous = [
    'php','php3','php4','php5','phtml','phar','exe','bat','cmd','sh',
    'cgi','pl','py','vbs','msi','dll','com','scr','jsp','aspx','asp',
    'shtml','hta','jar','ps1','wsf','reg','asp','phps','phtm'
];

$folderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
if ($folderId !== null) {
    $folder = \Drivew\F::folderFor($folderId, $uid);
    if ($folder === null || $folder['is_deleted']) { $folderId = null; }
} else {
    $folder = null;
}

// AJAX/JSON endpoint for progress upload (fetch + XHR)
if (($_POST['ajax'] ?? '') === '1' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    \Drivew\Csrf::requireValid();
    $results = [];
    foreach (normalizeUploadedFiles($_FILES['files'] ?? []) as $file) {
        $res = processUpload($file, $uid, $folderId, $allowed, $maxBytes, $dangerous);
        $results[] = $res;
    }
    $anySuccess = array_filter($results, fn($r) => $r['ok']);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => count($anySuccess) > 0,
        'message' => count($anySuccess) . ' of ' . count($results) . ' file(s) uploaded.',
        'results' => $results,
    ]);
    exit;
}

// Standard form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    \Drivew\Csrf::requireValid();
    $results = [];
    foreach (normalizeUploadedFiles($_FILES['files'] ?? []) as $file) {
        $results[] = processUpload($file, $uid, $folderId, $allowed, $maxBytes, $dangerous);
    }
    $anySuccess = array_filter($results, fn($r) => $r['ok']);
    if (count($anySuccess) === count($results) && count($results) > 0) {
        flash('success', 'File(s) uploaded successfully.', 'cloud-arrow-up');
    } elseif (count($anySuccess) > 0) {
        flash('warning', 'Some files uploaded, others failed. Check the list below.', 'triangle-exclamation');
    } else {
        flash('danger', 'Upload failed.', 'circle-exclamation');
    }
    $_SESSION['upload_results'] = $results;
    header('Location: files.php' . ($folderId ? '?folder_id=' . $folderId : ''));
    exit;
}

// --- Core upload logic ---
function normalizeUploadedFiles(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $normalized = [];
    foreach (array_keys($files['name']) as $index) {
        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }
    return $normalized;
}

function processUpload(array $file, int $uid, ?int $folderId, array $allowed, int $maxBytes, array $dangerous): array
{
    $name = basename($file['name']);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        ];
        return ['ok' => false, 'file' => $name, 'message' => $msgs[$file['error']] ?? 'Upload error ' . $file['error']];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'file' => $name, 'message' => 'File too large (' . \Drivew\F::humanSize($file['size']) . ', limit ' . \Drivew\F::humanSize($maxBytes) . ').'];
    }
    if (in_array($ext, $dangerous, true)) {
        return ['ok' => false, 'file' => $name, 'message' => 'Dangerous file type (. ' . $ext . ') is blocked for security.'];
    }
    if (!empty($allowed) && !in_array($ext, $allowed, true)) {
        return ['ok' => false, 'file' => $name, 'message' => 'Unsupported file type (' . $ext . ').'];
    }
    if (!\Drivew\F::hasStorage($uid, (int)$file['size'])) {
        return ['ok' => false, 'file' => $name, 'message' => 'Storage quota exceeded. Free space required: ' . \Drivew\F::humanSize((int)$file['size']) . '.'];
    }

    // Resolve a safe physical path
    $rand = bin2hex(random_bytes(12));
    $userDir = 'u' . $uid;
    $storedRel = $userDir . '/' . $rand . '_' . preg_replace('/[^A-Za-z0-9._-]/', '', $ext ? $ext : 'bin');
    $destDir = UPLOAD_ROOT . '/' . $userDir;
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
    $dest = UPLOAD_ROOT . '/' . $storedRel;

    // MIME sniffing via finfo when available
    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($file['tmp_name']);
        if ($detected && $detected !== 'application/octet-stream') $mime = $detected;
    }

    // Check for existing file with same name in same folder -> create a version
    $stmt = drivew_db()->prepare(
        'SELECT id, version_num FROM files
         WHERE user_id = ? AND is_deleted = 0 AND original_name = ?
         AND (' . ($folderId !== null ? 'parent_folder_id = ?' : 'parent_folder_id IS NULL') . ')
         ORDER BY id DESC LIMIT 1'
    );
    $params = $folderId !== null ? [$uid, $name, $folderId] : [$uid, $name];
    $stmt->execute($params);
    $existing = $stmt->fetch();

    if (is_uploaded_file($file['tmp_name'])) {
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['ok' => false, 'file' => $name, 'message' => 'Failed to store file on disk.'];
        }
    } else {
        return ['ok' => false, 'file' => $name, 'message' => 'Invalid upload.'];
    }

    if ($existing) {
        // Version this: archive the current physical file, increment
        $curStmt = drivew_db()->prepare('SELECT * FROM files WHERE id = ? AND user_id = ?');
        $curStmt->execute([$existing['id'], $uid]);
        $cur = $curStmt->fetch();
        $newVersion = ((int)$cur['version_num']) + 1;

        // Archive current into versions table
        $oldStored = UPLOAD_ROOT . '/' . $cur['stored_path'];
        $verRand = bin2hex(random_bytes(8));
        $verRel = 'u' . $uid . '/v_' . $verRand . '.' . ($cur['ext'] ?? 'bin');
        if (is_file($oldStored)) {
            @copy($oldStored, UPLOAD_ROOT . '/' . $verRel);
            @unlink($oldStored);
        }
        $vIns = drivew_db()->prepare(
            'INSERT INTO file_versions (file_id, user_id, version_num, stored_path, size, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $vIns->execute([$cur['id'], $uid, (int)$cur['version_num'], $verRel, (int)$cur['size']]);

        // Update the file row to point at the new physical file
        $upd = drivew_db()->prepare(
            'UPDATE files SET stored_path = ?, size = ?, version_num = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $upd->execute([$storedRel, $file['size'], $newVersion, $cur['id'], $uid]);
        \Drivew\F::log($uid, 'upload_revision', 'file', (int)$cur['id'], 'New revision of "' . $name . '" (v' . $newVersion . ')');

        return ['ok' => true, 'file' => $name, 'message' => 'Created version ' . $newVersion . ' of ' . $name, 'id' => (int)$cur['id']];
    }

    // Brand-new file
    $ins = drivew_db()->prepare(
        'INSERT INTO files (user_id, parent_folder_id, original_name, stored_path, ext, mime_type, size, is_starred, is_deleted, version_num, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 1, NOW(), NOW())'
    );
    $ins->execute([$uid, $folderId, $name, $storedRel, $ext, $mime, $file['size']]);
    $fid = (int)drivew_db()->lastInsertId();
    \Drivew\F::log($uid, 'upload', 'file', $fid, 'Uploaded "' . $name . '" (' . \Drivew\F::humanSize($file['size']) . ')');

    return ['ok' => true, 'file' => $name, 'message' => 'Uploaded ' . $name, 'id' => $fid];
}

$pageTitle = 'Upload';
$activeNav = 'files';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card shadow-sm rounded-4">
      <div class="card-body p-4">
        <h1 class="h4 fw-bold mb-1"><i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>Upload files</h1>
        <p class="text-secondary small">
          Drop files below or browse. Uploading a file with the same name in the same folder
          creates a new <strong>version</strong> automatically.
        </p>

        <?php if (!empty($_SESSION['upload_results'])) : ?>
          <div class="mb-3">
            <?php foreach ($_SESSION['upload_results'] as $r) : ?>
              <div class="d-flex align-items-center gap-2 small mb-1">
                <i class="fa-solid fa-<?= $r['ok'] ? 'circle-check text-success' : 'circle-xmark text-danger' ?>"></i>
                <span class="text-truncate"><?= \Drivew\F::esc($r['file']) ?></span>
                <span class="text-secondary ms-auto"><?= \Drivew\F::esc($r['message']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php unset($_SESSION['upload_results']); ?>

        <form id="uploadForm" method="post" enctype="multipart/form-data"
              action="upload.php<?= $folderId ? '?folder_id=' . $folderId : '' ?>" data-ajax="1">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <div id="dropZone" class="dropzone" role="button" tabindex="0"
               aria-label="Drag and drop files here, or click to browse">
            <i class="fa-solid fa-cloud-arrow-up display-5 text-primary opacity-50 mb-2"></i>
            <div class="fw-semibold">Drag &amp; drop files here</div>
            <div class="small text-secondary">or</div>
            <label class="btn btn-outline-primary rounded-pill px-4 mt-2 mb-0 d-inline-block">
              <i class="fa-solid fa-folder-open me-1"></i>Browse files
              <input type="file" name="files[]" id="fileInput" multiple hidden>
            </label>
            <input type="checkbox" name="ajax" value="1" class="d-none">
          </div>
          <div id="fileQueue" class="mt-3"></div>
          <div id="uploadProgress" class="progress mt-3 d-none" style="height:8px">
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
          </div>
          <button type="submit" class="btn btn-primary rounded-pill px-4 mt-3 d-none" id="uploadBtn">
            <i class="fa-solid fa-upload me-2"></i>Upload <span id="uploadCount"></span>
          </button>
        </form>

        <hr>
        <div class="small text-secondary">
          <i class="fa-solid fa-circle-info me-1"></i>
          Limit: <?= \Drivew\F::humanSize($maxBytes) ?> per file ·
          Allowed: <?= $allowed ? 'common document, image, audio, video, archive types' : 'all types' ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card shadow-sm rounded-4">
      <div class="card-body p-4">
        <h2 class="h6 fw-semibold mb-3">Uploading to</h2>
        <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light">
          <i class="fa-solid fa-folder-open text-primary fs-4"></i>
          <div>
            <div class="fw-semibold"><?= $folder ? \Drivew\F::esc($folder['name']) : 'My Storage (root)' ?></div>
            <div class="small text-secondary"><?= $folder ? \Drivew\F::folderPath($folder['id'], $uid) : 'Top level' ?></div>
          </div>
        </div>
        <?php if ($folder) : ?>
        <a class="small" href="files.php">← Back to root</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
