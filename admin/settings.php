<?php
/**
 * Drivew - Admin: system settings.
 */
require_once __DIR__ . '/includes_admin.php';

$settings = \Drivew\F::settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();

    $appName = trim((string)($_POST['app_name'] ?? APP_NAME));
    if (mb_strlen($appName) < 1 || mb_strlen($appName) > 60) {
        flash('danger', 'App name must be 1–60 characters.', 'circle-exclamation');
        header('Location: settings.php'); exit;
    }

    $maxUploadMB = (int)($_POST['max_upload_mb'] ?? 100);
    if ($maxUploadMB < 1 || $maxUploadMB > 10240) {
        flash('danger', 'Max upload must be between 1 MB and 10 GB.', 'circle-exclamation');
        header('Location: settings.php'); exit;
    }

    $defaultQuotaMB = (int)($_POST['default_quota_mb'] ?? 10240);
    if ($defaultQuotaMB < 0) {
        flash('danger', 'Default quota cannot be negative.', 'circle-exclamation');
        header('Location: settings.php'); exit;
    }

    $retention = (int)($_POST['trash_retention'] ?? 30);
    if ($retention < 1) {
        flash('danger', 'Trash retention must be at least 1 day.', 'circle-exclamation');
        header('Location: settings.php'); exit;
    }

    $allowedExt = strtolower(trim((string)($_POST['allowed_extensions'] ?? '')));
    $allowedExt = implode(',', array_filter(array_map('trim', explode(',', $allowedExt))));

    \Drivew\F::setSetting('app_name', $appName, $uid);
    \Drivew\F::setSetting('max_upload_mb', (string)$maxUploadMB, $uid);
    \Drivew\F::setSetting('default_quota_mb', (string)$defaultQuotaMB, $uid);
    \Drivew\F::setSetting('trash_retention', (string)$retention, $uid);
    \Drivew\F::setSetting('allowed_extensions', $allowedExt, $uid);

    // Apply default quota to existing users who still have the default value
    if ($defaultQuotaMB > 0) {
        $prevDefault = (int)($settings['default_quota_mb'] ?? 0);
        if ($prevDefault > 0 && $prevDefault !== $defaultQuotaMB) {
            $upd = drivew_db()->prepare('UPDATE users SET storage_quota = ? WHERE storage_quota = ?');
            $upd->execute([$defaultQuotaMB * 1048576, $prevDefault * 1048576]);
        }
    }

    flash('success', 'Settings saved.', 'gear');
    header('Location: settings.php');
    exit;
}

$pageTitle = 'Settings';
$activeNav = 'settings';
$flashes = getFlashes();
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-12 col-lg-9 col-xl-8">
<div class="card shadow-sm rounded-4">
  <div class="card-body p-4">
    <div class="mb-4">
      <h1 class="h4 fw-bold"><i class="fa-solid fa-gears me-2"></i>System settings</h1>
      <p class="text-secondary small">Changes here affect the whole system. Validate carefully before saving.</p>
    </div>

    <form method="post" action="settings.php">
      <input type="hidden" name="csrf" value="<?= \Drivew\Csrf::token() ?>">

      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold small">Application name</label>
          <input type="text" name="app_name" class="form-control" value="<?= \Drivew\F::esc($settings['app_name'] ?? APP_NAME) ?>" required maxlength="60">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold small">Max upload size (MB)</label>
          <input type="number" name="max_upload_mb" class="form-control" value="<?= \Drivew\F::esc($settings['max_upload_mb'] ?? '100') ?>" min="1" max="10240" required>
          <div class="form-text">Per-file ceiling; 1–10240 MB.</div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold small">Default user quota (MB)</label>
          <input type="number" name="default_quota_mb" class="form-control" value="<?= \Drivew\F::esc($settings['default_quota_mb'] ?? '10240') ?>" min="0">
          <div class="form-text">Applied to new users &amp; users still on the old default. 0 = unlimited.</div>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label fw-semibold small">Trash retention (days)</label>
          <input type="number" name="trash_retention" class="form-control" value="<?= \Drivew\F::esc($settings['trash_retention'] ?? '30') ?>" min="1">
          <div class="form-text">Items older than this are permanently purged.</div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold small">Allowed file extensions</label>
          <textarea name="allowed_extensions" class="form-control" rows="3" required><?= \Drivew\F::esc($settings['allowed_extensions'] ?? '') ?></textarea>
          <div class="form-text">Comma-separated. Edit to restrict what users can upload.</div>
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-primary rounded-pill px-5"><i class="fa-solid fa-floppy-disk me-2"></i>Save settings</button>
        <a class="btn btn-outline-secondary rounded-pill px-4" href="index.php">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div></div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
