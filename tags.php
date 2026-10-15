<?php
/**
 * Drivew - Tags page.
 * List user tags, create new, attach/detach to files, filter by tag.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/flash.php';

$u = \Drivew\Auth::requireAuth();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $name = trim((string)($_POST['name'] ?? ''));
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? '')) ? (string)$_POST['color'] : '#4f46e5';
            if ($name === '' || mb_strlen($name) > 60) { flash('danger', 'Invalid tag name.', 'circle-exclamation'); break; }
            $stmt = drivew_db()->prepare('INSERT IGNORE INTO tags (user_id, name, color, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$uid, $name, $color]);
            if ($stmt->rowCount() > 0) {
                \Drivew\F::log($uid, 'create_tag', 'tag', 0, 'Created tag "' . $name . '"');
                flash('success', 'Tag created.', 'tag');
            } else {
                flash('warning', 'Tag already exists.', 'circle-exclamation');
            }
            break;

        case 'attach':
            $tagId = (int)($_POST['tag_id'] ?? 0);
            $fileId = (int)($_POST['file_id'] ?? 0);
            // Verify both belong to user
            $t = drivew_db()->prepare('SELECT id FROM tags WHERE id = ? AND user_id = ?');
            $t->execute([$tagId, $uid]);
            if ($t->fetch()) {
                $f = \Drivew\F::fileFor($fileId, $uid);
                if ($f) {
                    $stmt = drivew_db()->prepare('INSERT IGNORE INTO file_tags (file_id, tag_id) VALUES (?, ?)');
                    $stmt->execute([$fileId, $tagId]);
                }
            }
            break;

        case 'detach':
            $tagId = (int)($_POST['tag_id'] ?? 0);
            $fileId = (int)($_POST['file_id'] ?? 0);
            $stmt = drivew_db()->prepare('DELETE FROM file_tags WHERE file_id = ? AND tag_id = ?');
            $stmt->execute([$fileId, $tagId]);
            break;

        case 'delete':
            $tagId = (int)($_POST['id'] ?? 0);
            $t = drivew_db()->prepare('SELECT * FROM tags WHERE id = ? AND user_id = ?');
            $t->execute([$tagId, $uid]);
            if ($t->fetch()) {
                drivew_db()->prepare('DELETE FROM file_tags WHERE tag_id = ?')->execute([$tagId]);
                drivew_db()->prepare('DELETE FROM tags WHERE id = ? AND user_id = ?')->execute([$tagId, $uid]);
                \Drivew\F::log($uid, 'delete_tag', 'tag', $tagId);
                flash('success', 'Tag deleted.', 'trash-can');
            }
            break;
    }
    header('Location: tags.php');
    exit;
}

// Filter view
$tagFilter = isset($_GET['tag']) ? (int)$_GET['tag'] : null;
$tags = \Drivew\F::userTags($uid);

$filteredFiles = [];
if ($tagFilter !== null) {
    $stmt = drivew_db()->prepare(
        'SELECT f.* FROM files f
         INNER JOIN file_tags ft ON ft.file_id = f.id
         WHERE f.user_id = ? AND f.is_deleted = 0 AND ft.tag_id = ?
         ORDER BY f.original_name'
    );
    $stmt->execute([$uid, $tagFilter]);
    $filteredFiles = $stmt->fetchAll();
}

$pageTitle = 'Tags';
$activeNav = 'tags';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <div><h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-tags me-2 text-primary"></i>Tags</h1>
  <p class="text-secondary mb-0">Organize and filter your files with labels.</p></div>
</div>

<?php if ($tagFilter !== null) : ?>
  <div class="mb-4 p-3 rounded-3 bg-light">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <div>Filtering by:
        <?php foreach ($tags as $t) { if ((int)$t['id'] === $tagFilter) : ?>
          <span class="tag-chip ms-1" style="background:<?= \Drivew\F::esc($t['color']) ?>;color:<?= contrastColor($t['color']) ?>"><?= \Drivew\F::esc($t['name']) ?></span>
        <?php endif; } ?>
      </div>
      <a class="small text-decoration-none" href="tags.php">Clear filter</a>
    </div>
    <?php if (empty($filteredFiles)) : ?>
      <div class="text-secondary small mt-2">No files carry this tag.</div>
    <?php else : ?>
      <ul class="list-unstyled small mt-2">
        <?php foreach ($filteredFiles as $f) : ?>
          <li class="d-flex align-items-center gap-2 py-1 border-bottom">
            <i class="fa-solid fa-file text-secondary"></i>
            <a class="text-decoration-none flex-grow-1" href="preview.php?id=<?= $f['id'] ?>"><?= \Drivew\F::esc($f['original_name']) ?></a>
            <span class="text-secondary"><?= \Drivew\F::humanSize($f['size']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm rounded-4">
      <div class="card-body p-4">
        <h2 class="h6 fw-semibold mb-3">All tags</h2>
        <?php if (empty($tags)) : ?>
          <div class="text-secondary small">No tags yet. Create one below.</div>
        <?php else : ?>
          <ul class="list-unstyled tag-list">
            <?php foreach ($tags as $t) : ?>
              <li class="d-flex align-items-center gap-2 py-2 border-bottom">
                <a class="tag-chip flex-grow-1 text-decoration-none" style="background:<?= \Drivew\F::esc($t['color']) ?>;color:<?= contrastColor($t['color']) ?>"
                   href="tags.php?tag=<?= $t['id'] ?>"><?= \Drivew\F::esc($t['name']) ?></a>
                <a class="small text-danger text-decoration-none" href="#" onclick="deleteTag(<?= $t['id'] ?>);return false"><i class="fa-solid fa-xmark"></i></a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <hr>
        <h3 class="h6 fw-semibold mb-2">Create a tag</h3>
        <form method="post" action="tags.php" class="d-flex flex-column gap-2">
          <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
          <input type="hidden" name="action" value="create">
          <div class="d-flex gap-2">
            <input type="text" name="name" class="form-control form-control-sm" required maxlength="60" placeholder="Tag name" style="flex:1">
            <input type="color" name="color" class="form-control form-control-color" value="#4f46e5" style="width:50px;padding:2px" aria-label="Tag color">
          </div>
          <button class="btn btn-primary btn-sm rounded-pill px-3"><i class="fa-solid fa-plus me-1"></i>Add tag</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card shadow-sm rounded-4">
      <div class="card-body p-4">
        <h2 class="h6 fw-semibold mb-3">Tag a file</h2>
        <?php if (empty($tags)) : ?>
          <div class="text-secondary small">Create a tag first, then attach it to a file.</div>
        <?php else : ?>
          <form method="post" action="tags.php" class="row g-2 align-items-center">
            <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
            <input type="hidden" name="action" value="attach">
            <div class="col-12 col-md-5">
              <select name="file_id" class="form-select form-select-sm" required>
                <option value="">Select a file…</option>
                <?php
                  $fl = drivew_db()->prepare('SELECT id, original_name FROM files WHERE user_id = ? AND is_deleted = 0 ORDER BY original_name LIMIT 200');
                  $fl->execute([$uid]);
                  foreach ($fl->fetchAll() as $f) : ?>
                <option value="<?= $f['id'] ?>"><?= \Drivew\F::esc($f['original_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-5">
              <select name="tag_id" class="form-select form-select-sm" required>
                <option value="">Select a tag…</option>
                <?php foreach ($tags as $t) : ?>
                  <option value="<?= $t['id'] ?>"><?= \Drivew\F::esc($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-2">
              <button class="btn btn-primary w-100 rounded-pill btn-sm"><i class="fa-solid fa-plus me-1"></i>Attach</button>
            </div>
          </form>
          <p class="small text-secondary mt-3 mb-0"><i class="fa-solid fa-circle-info me-1"></i>Tip: use the filter above to see files that carry a tag.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<form id="deleteTagForm" method="post" action="tags.php" class="d-none">
  <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="deleteTagId">
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
<script>
function deleteTag(id){
  if(confirm('Delete this tag? Files will keep their data, but the tag link is removed.')){
    document.getElementById('deleteTagId').value = id;
    document.getElementById('deleteTagForm').submit();
  }
}
function contrastColor(hex){
  if(!hex) return '#fff';
  const c = hex.replace('#','');
  const r = parseInt(c.substr(0,2),16), g = parseInt(c.substr(2,2),16), b = parseInt(c.substr(4,2),16);
  return (0.299*r + 0.587*g + 0.114*b) > 150 ? '#111827' : '#ffffff';
}
</script>
