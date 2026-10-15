<?php
/**
 * Drivew - Admin page bootstrap.
 * Verifies admin role. Set $pageTitle / $activeNav before including header.
 */
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/flash.php';

$u = \Drivew\Auth::requireAdmin();
$uid = (int)$u['id'];
$flashes = getFlashes();
$activeNav = $activeNav ?? '';
