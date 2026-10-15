<?php
/**
 * Drivew - Shared HTML header.
 * Include after setting $pageTitle, $activeNav.
 */
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/functions.php';

$currentUser = \Drivew\Auth::user();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$isAdmin = \Drivew\Auth::isAdmin();
$displayUser = $currentUser; // user-facing layout only

if ($displayUser === null) {
    header('Location: login.php');
    exit;
}

$initials = strtoupper(substr(trim($displayUser['display_name'] ?: $displayUser['email']), 0, 2));
$usedBytes = \Drivew\F::storageUsed((int)$displayUser['id']);
$quota = \Drivew\F::storageQuota((int)$displayUser['id']);
$usedPct = \Drivew\F::storagePercent((int)$displayUser['id']);
$usedHuman = \Drivew\F::humanSize($usedBytes);
$quotaHuman = $quota > 0 ? \Drivew\F::humanSize($quota) : 'Unlimited';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= \Drivew\F::esc($pageTitle) ?> · Drivew</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?= $isAdmin ? 'assets/style.css' : 'assets/css/style.css' ?>" rel="stylesheet">
<style>
:root{
  --brand:#4f46e5; --brand-dark:#4338ca; --brand-light:#eef2ff;
  --ink:#0f172a; --ink-2:#334155; --ink-3:#64748b; --line:#e2e8f0;
  --bg:#f8fafc; --card:#ffffff; --ok:#16a34a; --warn:#d97706; --err:#dc2626;
}
body{font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;background:var(--bg);color:var(--ink);}
</style>
</head>
<body>
<div class="d-flex" id="appShell">

<?php if ($isAdmin) : include __DIR__ . '/sidebar_admin.php'; else : include __DIR__ . '/sidebar.php'; endif; ?>

<main class="app-main flex-grow-1 d-flex flex-column">
  <header class="app-topbar d-flex align-items-center gap-3 px-3 px-lg-4 py-3">
    <button class="btn btn-link text-decoration-none nav-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Toggle navigation">
      <i class="fa-solid fa-bars fs-4"></i>
    </button>
    <div class="d-flex align-items-center gap-3 flex-grow-1">
      <div class="fs-5 fw-semibold text-nowrap">
        <span class="d-none d-md-inline"><?= \Drivew\F::esc($pageTitle) ?></span>
        <span class="d-md-none"><?= \Drivew\F::esc($pageTitle) ?></span>
      </div>
      <?php if (isset($searchBox) && $searchBox) : ?>
        <form class="search-box" action="search.php" method="get" role="search">
          <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
          <input type="search" name="q" placeholder="Search your files and folders…" aria-label="Search" autocomplete="off"
                 value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </form>
      <?php endif; ?>
    </div>
    <div class="d-flex align-items-center gap-3">
      <div class="storage-chip d-flex align-items-center gap-2 me-2 me-md-3" title="Storage used">
        <div class="storage-mini" aria-hidden="true"><span style="width:<?= min(100, max(0, $usedPct)) ?>%"></span></div>
        <span class="storage-label text-nowrap d-none d-sm-inline"><?= $usedHuman ?> / <?= $quotaHuman ?></span>
      </div>
      <div class="dropdown">
        <button class="btn btn-light rounded-circle user-avatar" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
          <?= \Drivew\F::esc($initials) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0 bg-white rounded-3">
          <li><h6 class="dropdown-header">Signed in as</h6></li>
          <li class="dropdown-item-text px-3 py-1 small text-secondary text-nowrap"><?= \Drivew\F::esc($displayUser['email']) ?></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?= $isAdmin ? '../profile.php' : 'profile.php' ?>"><i class="fa-solid fa-user me-2"></i>Profile</a></li>
          <li><a class="dropdown-item" href="<?= $isAdmin ? '../profile.php#change-password' : 'profile.php#change-password' ?>"><i class="fa-solid fa-key me-2"></i>Change Password</a></li>
          <?php if ($isAdmin) : ?><li><a class="dropdown-item" href="admin/"><i class="fa-solid fa-shield-halved me-2"></i>Admin Panel</a></li><?php endif; ?>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= $isAdmin ? '../logout.php' : 'logout.php' ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Sign out</a></li>
        </ul>
      </div>
    </div>
  </header>

  <?php if (isset($flashes) && $flashes) : foreach ($flashes as $f) : ?>
    <div class="flash-container px-3 px-lg-4">
      <div class="alert alert-<?= \Drivew\F::esc($f['type']) ?> alert-dismissible fade show shadow-sm rounded-3 mb-2" role="alert">
        <div class="d-flex align-items-center gap-2">
          <i class="fa-solid fa-<?= \Drivew\F::esc($f['icon']) ?>" aria-hidden="true"></i>
          <span><?= \Drivew\F::esc($f['text']) ?></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <div class="app-content flex-grow-1 px-3 px-lg-4 py-4">
