<?php
/**
 * Drivew Installer
 *
 * A wizard that:
 *  1. Verifies PHP version + required extensions
 *  2. Verifies writable directories
 *  3. Connects to MySQL and creates the schema
 *  4. Creates the initial admin account
 *  5. Seeds default settings
 *  6. Creates the storage directories and install lock
 *  7. Locks the installer against re-use
 *
 * IMPORTANT: After install completes, the /install directory should be
 * deleted on production. The lock file (install.lock) also blocks re-runs.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1'); // installer shows errors for diagnosis

define('BASE_DIR', dirname(__DIR__));
define('INSTALLER_LOCK', BASE_DIR . '/install.lock');
define('APP_NAME', 'Drivew');
define('UPLOAD_ROOT', BASE_DIR . '/uploads');

// Refuse to run if already installed and lock file exists
if (is_file(INSTALLER_LOCK) && !isset($_POST['force_reinstall'])) {
    http_response_code(403);
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Drivew</title>
    <body style="font-family:system-ui;max-width:520px;margin:80px auto;color:#0f172a">
    <div style="background:#fff8f1;border:1px solid #fde68a;padding:24px;border-radius:12px">
      <h2 style="margin-top:0"><i>⚠️</i> Drivew is already installed</h2>
      <p>Install has already completed. To run the installer again, delete
      <code>install.lock</code> and call <code>install/install.php?force=1</code>.</p>
      <p><a href="../login.php" style="color:#4f46e5;font-weight:600">Go to login →</a></p>
    </div></body>';
    exit;
}

$steps = [
    ['id' => 'env', 'name' => 'Environment check'],
    ['id' => 'db',  'name' => 'Database setup'],
    ['id' => 'admin','name' => 'Admin account'],
    ['id' => 'done','name' => 'Finalize'],
];

$step = $_GET['step'] ?? 'env';
$pdo  = null;
$errors = [];
$ok = [];

// ---------- STEP 1: ENVIRONMENT ----------
if ($step === 'env') {
    // PHP version
    if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
        $ok[] = 'PHP ' . PHP_VERSION . ' ✓';
    } else {
        $errors[] = 'PHP 8.1+ required (found ' . PHP_VERSION . ')';
    }
    foreach (['pdo_mysql', 'json', 'zip'] as $ext) {
        if (extension_loaded($ext)) $ok[] = 'Extension ' . $ext . ' ✓';
        else $errors[] = 'Extension ' . $ext . ' missing';
    }
    foreach (['uploads', 'logs'] as $dir) {
        $p = BASE_DIR . '/' . $dir;
        if (!is_dir($p)) @mkdir($p, 0755, true);
        if (is_writable($p)) $ok[] = 'Directory /' . $dir . ' writable ✓';
        else $errors[] = 'Directory /' . $dir . ' not writable';
    }
    // uploads/.htaccess protection
    $ht = BASE_DIR . '/uploads/.htaccess';
    if (!is_file($ht)) {
        file_put_contents($ht,
"Options -ExecCGI\nIndexIgnore \*\n<FilesMatch \"\\.(php|phtml|phar|pl|cgi)$\">\n  Order allow,deny\n  Deny from all\n</FilesMatch>\n<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteCond %{REQUEST_FILENAME} !-f\n  RewriteRule \\.php$ - [L,R=403]\n</IfModule>\n");
        $ok[] = 'uploads/.htaccess created ✓';
    } else {
        $ok[] = 'uploads/.htaccess present ✓';
    }
}

// ---------- STEP 2: DATABASE ----------
elseif ($step === 'db') {
    if (($_POST['save_db'] ?? null)) {
        $host = trim((string)($_POST['db_host'] ?? 'localhost'));
        $port = trim((string)($_POST['db_port'] ?? '3306'));
        $name = trim((string)($_POST['db_name'] ?? 'drivew_db'));
        $user = trim((string)($_POST['db_user'] ?? 'root'));
        $pass = (string)($_POST['db_pass'] ?? '123456');
        $testPdo = null;

        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $errors[] = 'Database name may contain only letters, numbers, and underscores.';
        } else {
            try {
                $testPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (PDOException $e) {
                $errors[] = 'Database connection failed: ' . $e->getMessage();
            }
        }

        if ($testPdo !== null) {
            try {
                $testPdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $testPdo->exec("USE `{$name}`");
                $schema = file_get_contents(__DIR__ . '/schema.sql');
                // schema.sql also contains CREATE DATABASE and USE statements.
                // Remove complete statements so multiline CREATE DATABASE
                // clauses cannot leave fragments such as CHARACTER SET behind.
                $clean = preg_replace('/CREATE\s+DATABASE\b.*?;\s*/is', '', $schema, 1);
                $clean = preg_replace('/^\s*USE\s+[`]?[^;]+[`]?;\s*/im', '', $clean, 1);
                $clean = "SET FOREIGN_KEY_CHECKS=0;\n"
                       . preg_replace('/^DROP TABLE IF EXISTS.*$/m', '', $clean)
                       . "SET FOREIGN_KEY_CHECKS=1;\n";
                $clean = preg_replace(
                    '/SET FOREIGN_KEY_CHECKS=0;\s*/',
                    "SET FOREIGN_KEY_CHECKS=0;\n"
                    . "DROP TABLE IF EXISTS activity_logs;\n"
                    . "DROP TABLE IF EXISTS file_versions;\n"
                    . "DROP TABLE IF EXISTS file_tags;\n"
                    . "DROP TABLE IF EXISTS tags;\n"
                    . "DROP TABLE IF EXISTS shares;\n"
                    . "DROP TABLE IF EXISTS files;\n"
                    . "DROP TABLE IF EXISTS folders;\n"
                    . "DROP TABLE IF EXISTS settings;\n"
                    . "DROP TABLE IF EXISTS users;\n",
                    $clean, 1
                );
                $stmts = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $clean)));
                $count = 0;
                foreach ($stmts as $s) {
                    if ($s !== '' && !preg_match('/^(--|\/\*|SET\s+FOREIGN_KEY_CHECKS)/i', $s)) {
                        $testPdo->exec($s);
                        $count++;
                    }
                }
                $ok[] = "Database {$name} ready — $count tables ✓";
                $pdo = $testPdo;
            } catch (PDOException $e) {
                $errors[] = 'Schema error: ' . $e->getMessage();
            }
        }

        // Persist credentials only when we can actually reach the server
        if ($testPdo !== null && empty($errors)) {
            $cfg = "<?php\n"
                 . "/** Drivew - generated by installer " . date('Y-m-d H:i:s') . " **/\n\n"
                 . "define('DB_HOST', '" . addslashes($host) . "');\n"
                 . "define('DB_PORT', '" . addslashes($port) . "');\n"
                 . "define('DB_NAME', '" . addslashes($name) . "');\n"
                 . "define('DB_USER', '" . addslashes($user) . "');\n"
                 . "define('DB_PASS', '" . addslashes($pass) . "');\n\n"
                 . "define('DB_DSN', 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4');\n\n"
                 . "function drivew_db(): PDO\n{\n"
                 . "    static \$pdo = null;\n"
                 . "    if (\$pdo === null) {\n"
                 . "        \$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [\n"
                 . "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
                 . "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
                 . "            PDO::ATTR_EMULATE_PREPARES => false,\n"
                 . "        ]);\n"
                 . "    }\n"
                 . "    return \$pdo;\n}\n";
            file_put_contents(BASE_DIR . '/config/database.php', $cfg);
            $ok[] = 'config/database.php written ✓';
            header('Location: install.php?step=admin');
            exit;
        }
    }
}

// ---------- STEP 3: ADMIN ----------
elseif ($step === 'admin') {
    // Require DB config first
    require BASE_DIR . '/config/database.php';
    try {
        $pdo = drivew_db();
    } catch (Throwable $e) {
        $errors[] = 'Cannot connect using saved credentials: ' . $e->getMessage();
    }

    if ($_POST['save_admin'] ?? null) {
        $email = strtolower(trim($_POST['admin_email'] ?? ''));
        $name  = trim($_POST['admin_name'] ?? '');
        $pass  = (string)($_POST['admin_pass'] ?? '');
        $pass2 = (string)($_POST['admin_pass2'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid admin email is required.';
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors[] = 'Admin display name must be 2–80 characters.';
        }
        if (strlen($pass) < 8) {
            $errors[] = 'Admin password must be at least 8 characters.';
        }
        if ($pass !== $pass2) {
            $errors[] = 'Passwords do not match.';
        }
        if (empty($errors)) {
            // Quota from config / default
            $quotaRaw = (int)($_POST['admin_quota_mb'] ?? 10240);
            $quota = $quotaRaw * 1048576;

            $stmt = $pdo->prepare(
                'INSERT INTO users (email, display_name, password_hash, role, is_active, storage_quota, created_at, updated_at)
                 VALUES (?, ?, ?, "admin", 1, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE email = email'
            );
            $stmt->execute([
                $email,
                $name,
                password_hash($pass, PASSWORD_DEFAULT),
                $quota
            ]);
            $adminId = (int)$pdo->lastInsertId();
            if ($adminId === 0) {
                $idStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $idStmt->execute([$email]);
                $adminId = (int)$idStmt->fetchColumn();
            }
            $ok[] = 'Admin account created ✓';

            // Seed default settings
            $defaults = [
                'app_name'            => 'Drivew',
                'max_upload_mb'       => '100',
                'default_quota_mb'    => (string)$quotaRaw,
                'trash_retention'     => '30',
                'allowed_extensions'  => implode(',', [
                    'pdf','doc','docx','xls','xlsx','csv','ppt','pptx','txt','md','json','js','css','html','xml',
                    'jpg','jpeg','png','gif','webp','bmp','svg','mp3','mp4','wav','zip','rar','7z','tar','gz',
                    'ini','conf','yml','yaml','log'
                ]),
            ];
            $ins = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($defaults as $k => $v) {
                $ins->execute([$k, $v, $adminId]);
            }
            $ok[] = 'Default settings seeded ✓';
            header('Location: install.php?step=done');
            exit;
        }
    }
}

// ---------- STEP 4: DONE ----------
elseif ($step === 'done') {
    // Write install lock
    file_put_contents(INSTALLER_LOCK, json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'version' => '1.0.0',
    ]));
    $ok[] = 'Install lock written ✓';

    // Ensure logs dir
    if (!is_dir(BASE_DIR . '/logs')) @mkdir(BASE_DIR . '/logs', 0755, true);
    $ok[] = 'All systems ready ✓';
}

function renderStep(string $step, array $errors, array $ok, ?PDO $pdo): void
{
    $stepIdx = array_search($step, ['env','db','admin','done'], true);
    $labels = ['env' => 'Environment', 'db' => 'Database', 'admin' => 'Admin', 'done' => 'Done'];
    ?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<title>Drivew Installer</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',system-ui,sans-serif;background:linear-gradient(160deg,#eef2ff 0%,#f8fafc 60%);min-height:100vh;padding:2rem}
.installer-card{max-width:680px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:2rem;box-shadow:0 10px 30px rgba(15,23,42,.06)}
.wizard{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.5rem}
.wizard .wstep{flex:1;min-width:110px;background:#f1f5f9;border-radius:10px;padding:10px;text-align:center;font-size:.8rem;color:#64748b}
.wizard .wstep.on{background:#4f46e5;color:#fff;font-weight:600}
.wizard .wstep.done{background:#dcfce7;color:#166534}
.field{margin-bottom:1rem}
.field label{display:block;font-weight:600;font-size:.85rem;color:#334155;margin-bottom:.35rem}
.field input,.field select{width:100%;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.95rem}
.alert{border-radius:10px}
.btn-primary{background:#4f46e5;border-color:#4f46e5}
.btn-primary:hover{background:#4338ca}
.log-line{font-family:ui-monospace,monospace;font-size:.8rem;background:#0f172a;color:#a5f3fc;padding:1rem 1.25rem;border-radius:10px;max-height:160px;overflow:auto;white-space:pre-wrap}
.brand{display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem}
.brand-mark{width:48px;height:48px;background:#4f46e5;color:#fff;border-radius:12px;display:grid;place-items:center;font-size:1.4rem}
</style></head><body>
<div class="installer-card">
  <div class="brand">
    <div class="brand-mark"><i class="fa-solid fa-box-archive"></i></div>
    <div>
      <h1 class="h5 mb-0 fw-bold">Drivew Installer</h1>
      <div class="small text-secondary">Web-based file management &amp; storage</div>
    </div>
  </div>

  <div class="wizard">
    <?php foreach ($labels as $k => $l) :
        $si = array_search($k, array_keys($labels));
        $st = $si === $stepIdx ? 'on' : ($si < $stepIdx ? 'done' : '');
        echo '<div class="wstep ' . $st . '">' . $l . '</div>';
    endforeach; ?>
  </div>

  <?php if (!empty($ok)) : ?>
    <div class="log-line mb-3" aria-label="Success messages">
      <?php foreach ($ok as $line) echo htmlspecialchars($line) . "\n"; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)) : ?>
    <div class="alert alert-danger"><strong>Errors:</strong><ul class="mb-0">
      <?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?>
    </ul></div>
  <?php endif; ?>

  <?php if ($step === 'env') : ?>
    <h2 class="h5">1 · Environment</h2>
    <p class="text-secondary small">PHP version, extensions, and directory permissions are being verified. If everything is green, continue.</p>
    <form method="get">
      <input type="hidden" name="step" value="db">
      <button class="btn btn-primary px-4"><i class="fa-solid fa-database me-2"></i>Configure database</button>
    </form>

  <?php elseif ($step === 'db') : ?>
    <h2 class="h5">2 · Database connection</h2>
    <p class="text-secondary small">Provide your MySQL/MariaDB credentials. The installer will create the database and all tables.</p>
    <form method="post">
      <input type="hidden" name="step" value="admin">
      <input type="hidden" name="save_db" value="1">
      <div class="row g-2">
        <div class="col-6 col-md-4 field"><label>Host</label><input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"></div>
        <div class="col-6 col-md-2 field"><label>Port</label><input name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>"></div>
        <div class="col-12 col-md-6 field"><label>Database name</label><input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'drivew_db') ?>"></div>
        <div class="col-6 col-md-4 field"><label>Username</label><input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>"></div>
        <div class="col-6 col-md-8 field"><label>Password</label><input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '123456') ?>"></div>
      </div>
      <button class="btn btn-primary px-4"><i class="fa-solid fa-check me-2"></i>Test &amp; create database</button>
    </form>

  <?php elseif ($step === 'admin') : ?>
    <h2 class="h5">3 · Administrator account</h2>
    <p class="text-secondary small">This account will have full control over the system. No public registration is ever created.</p>
    <form method="post">
      <input type="hidden" name="step" value="done">
      <input type="hidden" name="save_admin" value="1">
      <div class="field"><label>Display name</label><input name="admin_name" required minlength="2" maxlength="80" placeholder="e.g. System Admin"></div>
      <div class="field"><label>Admin email</label><input type="email" name="admin_email" required placeholder="admin@example.com"></div>
      <div class="row g-2">
        <div class="col-12 col-md-6 field"><label>Password</label><input type="password" name="admin_pass" required minlength="8" autocomplete="new-password"></div>
        <div class="col-12 col-md-6 field"><label>Confirm password</label><input type="password" name="admin_pass2" required minlength="8" autocomplete="new-password"></div>
      </div>
      <div class="field"><label>Default user storage quota (MB)</label><input type="number" name="admin_quota_mb" value="<?= htmlspecialchars($_POST['admin_quota_mb'] ?? '10240') ?>" min="10" max="1048576"></div>
      <button class="btn btn-primary px-4"><i class="fa-solid fa-user-shield me-2"></i>Create admin &amp; finish</button>
    </form>

  <?php elseif ($step === 'done') : ?>
    <div class="text-center py-4">
      <div class="display-3 text-success mb-3"><i class="fa-solid fa-circle-check"></i></div>
      <h2 class="h4 fw-bold mb-1">Installation complete!</h2>
      <p class="text-secondary">You can now log in as the administrator you just created.</p>
      <a class="btn btn-primary px-5 py-2" href="../login.php"><i class="fa-solid fa-right-to-bracket me-2"></i>Go to login</a>
      <p class="small text-secondary mt-4"><i class="fa-solid fa-triangle-exclamation me-1"></i>
      For production, delete the <code>/install</code> directory now that <code>install.lock</code> is in place.</p>
    </div>
  <?php endif; ?>
</div>
</body></html>
    <?php
}

renderStep($step, $errors, $ok, $pdo);
