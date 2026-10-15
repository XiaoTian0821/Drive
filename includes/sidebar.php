<?php
/**
 * Drivew - User sidebar.
 */
$navItems = [
    ['href' => 'dashboard.php', 'icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'key' => 'dashboard'],
    ['href' => 'files.php', 'icon' => 'fa-folder', 'label' => 'My Files', 'key' => 'files'],
    ['href' => 'recent.php', 'icon' => 'fa-clock-rotate-left', 'label' => 'Recent', 'key' => 'recent'],
    ['href' => 'starred.php', 'icon' => 'fa-star', 'label' => 'Starred', 'key' => 'starred'],
    ['href' => 'tags.php', 'icon' => 'fa-tags', 'label' => 'Tags', 'key' => 'tags'],
    ['href' => 'share.php', 'icon' => 'fa-link', 'label' => 'Shared', 'key' => 'shares'],
    ['href' => 'trash.php', 'icon' => 'fa-trash-can', 'label' => 'Trash', 'key' => 'trash'],
];
?>
<aside class="app-sidebar" id="sidebar" aria-label="Primary navigation">
  <div class="sidebar-brand d-flex align-items-center gap-3 px-4 py-4">
    <div class="brand-mark" aria-hidden="true">
      <i class="fa-solid fa-box-archive"></i>
    </div>
    <div>
      <div class="brand-name fw-bold">Drivew</div>
      <div class="brand-tag small text-secondary">Cloud file storage</div>
    </div>
  </div>
  <nav class="sidebar-nav px-2">
    <ul class="list-unstyled mb-0 d-flex flex-column gap-1">
      <?php foreach ($navItems as $item) : ?>
      <li>
        <a class="side-link<?= $activeNav === $item['key'] ? ' active' : '' ?>"
           href="<?= $item['href'] ?>" aria-current="<?= $activeNav === $item['key'] ? 'page' : 'false' ?>">
          <i class="fa-solid <?= $item['icon'] ?>" aria-hidden="true"></i>
          <span><?= \Drivew\F::esc($item['label']) ?></span>
        </a>
      </li>
      <?php endforeach; ?>
      <li>
        <a class="side-link<?= $activeNav === 'search' ? ' active' : '' ?>" href="search.php">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <span>Search</span>
        </a>
      </li>
      <li>
        <a class="side-link<?= $activeNav === 'profile' ? ' active' : '' ?>" href="profile.php">
          <i class="fa-solid fa-user" aria-hidden="true"></i>
          <span>Profile</span>
        </a>
      </li>
      <?php if ($isAdmin) : ?>
      <li class="mt-2"><hr class="text-secondary opacity-25 my-2"></li>
      <li>
        <a class="side-link text-danger" href="admin/"><i class="fa-solid fa-shield-halved me-0"></i>
          <span>Admin Panel</span></a>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
  <div class="sidebar-footer px-4 py-3">
    <div class="d-flex align-items-center gap-3">
      <div class="user-avatar"><?= \Drivew\F::esc($initials) ?></div>
      <div class="flex-grow-1">
        <div class="fw-semibold small text-truncate"><?= \Drivew\F::esc($displayUser['display_name'] ?: '—') ?></div>
        <div class="small text-secondary text-truncate"><?= \Drivew\F::esc($displayUser['email']) ?></div>
      </div>
    </div>
  </div>
</aside>

<!-- Off-canvas variant for mobile -->
<div class="offcanvas offcanvas-start sidebar-offcanvas" tabindex="-1" id="sidebarOffcanvas" aria-label="Mobile navigation">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title fw-bold mb-0 d-flex align-items-center gap-2">
      <i class="fa-solid fa-box-archive text-primary"></i> Drivew
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="list-unstyled d-flex flex-column gap-1">
      <?php foreach ($navItems as $item) : ?>
      <li>
        <a class="side-link side-link-mobile<?= $activeNav === $item['key'] ? ' active' : '' ?>" href="<?= $item['href'] ?>">
          <i class="fa-solid <?= $item['icon'] ?>"></i><span><?= \Drivew\F::esc($item['label']) ?></span>
        </a>
      </li>
      <?php endforeach; ?>
      <li><a class="side-link side-link-mobile<?= $activeNav === 'search' ? ' active' : '' ?>" href="search.php"><i class="fa-solid fa-magnifying-glass"></i><span>Search</span></a></li>
      <li><a class="side-link side-link-mobile<?= $activeNav === 'profile' ? ' active' : '' ?>" href="profile.php"><i class="fa-solid fa-user"></i><span>Profile</span></a></li>
      <?php if ($isAdmin) : ?><li><a class="side-link side-link-mobile text-danger" href="admin/"><i class="fa-solid fa-shield-halved"></i><span>Admin Panel</span></a></li><?php endif; ?>
    </ul>
  </div>
</div>
