<?php
/**
 * Drivew - Admin sidebar.
 */
$adminNav = [
    ['href' => 'index.php', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'key' => 'dashboard'],
    ['href' => 'users.php', 'icon' => 'fa-users', 'label' => 'Users', 'key' => 'users'],
    ['href' => 'files.php', 'icon' => 'fa-file-lines', 'label' => 'Files', 'key' => 'files'],
    ['href' => 'logs.php', 'icon' => 'fa-scroll', 'label' => 'Activity Log', 'key' => 'logs'],
    ['href' => 'settings.php', 'icon' => 'fa-gears', 'label' => 'Settings', 'key' => 'settings'],
];
?>
<aside class="app-sidebar admin-sidebar" id="sidebar" aria-label="Admin navigation">
  <div class="sidebar-brand d-flex align-items-center gap-3 px-4 py-4">
    <div class="brand-mark admin-mark" aria-hidden="true">
      <i class="fa-solid fa-shield-halved"></i>
    </div>
    <div>
      <div class="brand-name fw-bold">Drivew <span class="admin-pill">Admin</span></div>
      <div class="brand-tag small text-secondary">Management console</div>
    </div>
  </div>
  <nav class="sidebar-nav px-2">
    <ul class="list-unstyled mb-0 d-flex flex-column gap-1">
      <?php foreach ($adminNav as $item) : ?>
      <li>
        <a class="side-link<?= $activeNav === $item['key'] ? ' active' : '' ?>"
           href="<?= $item['href'] ?>" aria-current="<?= $activeNav === $item['key'] ? 'page' : 'false' ?>">
          <i class="fa-solid <?= $item['icon'] ?>" aria-hidden="true"></i>
          <span><?= \Drivew\F::esc($item['label']) ?></span>
        </a>
      </li>
      <?php endforeach; ?>
      <li class="mt-2"><hr class="text-secondary opacity-25 my-2"></li>
      <li>
        <a class="side-link" href="../dashboard.php"><i class="fa-solid fa-left-from-bracket"></i><span>Back to User View</span></a>
      </li>
    </ul>
  </nav>
  <div class="sidebar-footer px-4 py-3">
    <div class="d-flex align-items-center gap-3">
      <div class="user-avatar admin-avatar"><?= \Drivew\F::esc($initials) ?></div>
      <div class="flex-grow-1">
        <div class="fw-semibold small text-truncate"><?= \Drivew\F::esc($displayUser['display_name'] ?: 'Admin') ?></div>
        <div class="small text-secondary text-truncate"><?= \Drivew\F::esc($displayUser['email']) ?></div>
      </div>
    </div>
  </div>
</aside>

<div class="offcanvas offcanvas-start sidebar-offcanvas" tabindex="-1" id="sidebarOffcanvas" aria-label="Mobile navigation">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title fw-bold mb-0 d-flex align-items-center gap-2">
      <i class="fa-solid fa-shield-halved text-danger"></i> Drivew Admin
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="list-unstyled d-flex flex-column gap-1">
      <?php foreach ($adminNav as $item) : ?>
      <li><a class="side-link side-link-mobile<?= $activeNav === $item['key'] ? ' active' : '' ?>" href="<?= $item['href'] ?>"><i class="fa-solid <?= $item['icon'] ?>"></i><span><?= \Drivew\F::esc($item['label']) ?></span></a></li>
      <?php endforeach; ?>
      <li><a class="side-link side-link-mobile" href="../dashboard.php"><i class="fa-solid fa-left-from-bracket"></i><span>Back to User View</span></a></li>
    </ul>
  </div>
</div>
