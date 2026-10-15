<?php
/**
 * Compatibility entry point for the installer URL used by older redirects.
 */

declare(strict_types=1);

$query = http_build_query($_GET);
$location = '../../install/install.php' . ($query !== '' ? '?' . $query : '');

header('Location: ' . $location, true, 302);
exit;
