<?php
/**
 * Drivew - Logout.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

$u = \Drivew\Auth::user();
if ($u !== null) {
    \Drivew\F::log((int)$u['id'], 'logout', 'user', (int)$u['id'], 'Signed out');
}
\Drivew\Auth::logout();
header('Location: login.php');
exit;
