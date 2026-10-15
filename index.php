<?php
/**
 * Drivew - Root entry point.
 * Redirects authenticated users to their home, others to login.
 */
require_once __DIR__ . '/includes/Auth.php';

$u = \Drivew\Auth::user();
if ($u !== null) {
    header('Location: ' . ($u['role'] === 'admin' ? 'admin/' : 'dashboard.php'));
} else {
    header('Location: login.php');
}
exit;
