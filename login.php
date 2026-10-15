<?php
/**
 * Drivew - Login.
 */
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

if (\Drivew\Auth::user() !== null) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Drivew\Csrf::requireValid();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = drivew_db()->prepare(
            'SELECT * FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            if ((int)$user['is_active'] !== 1) {
                \Drivew\F::log((int)$user['id'], 'login_blocked', 'user', (int)$user['id'], 'Inactive login attempt');
                $error = 'This account is deactivated. Contact an administrator.';
            } else {
                \Drivew\Auth::login((int)$user['id']);
                \Drivew\F::log((int)$user['id'], 'login', 'user', (int)$user['id'], 'Signed in');
                $target = $user['role'] === 'admin' ? 'admin/' : 'dashboard.php';
                header('Location: ' . $target);
                exit;
            }
        } else {
            \Drivew\F::log(0, 'login_failed', 'user', 0, 'Failed login for ' . $email);
            $error = 'Incorrect email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Sign in · Drivew</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--brand:#4f46e5;--ink:#0f172a}
*{box-sizing:border-box}
body{font-family:'Inter',system-ui,sans-serif;min-height:100vh;background:linear-gradient(140deg,#eef2ff 0%,#f8fafc 45%,#f1f5f9 100%);display:grid;place-items:center;padding:1.5rem;margin:0;color:var(--ink)}
.login-shell{width:100%;max-width:420px}
.brand{display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem}
.brand-mark{width:44px;height:44px;background:var(--brand);color:#fff;border-radius:12px;display:grid;place-items:center;font-size:1.25rem;box-shadow:0 8px 20px rgba(79,70,229,.35)}
.login-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:2rem;box-shadow:0 20px 50px rgba(15,23,42,.08)}
.eyebrow{font-size:.75rem;font-weight:600;color:var(--brand);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.35rem}
h1{font-size:1.4rem;font-weight:700;margin:0 0 1.25rem}
.field{margin-bottom:1rem}
.field label{display:block;font-size:.85rem;font-weight:600;color:#334155;margin-bottom:.35rem}
.field input{width:100%;padding:.65rem .85rem;border:1px solid #cbd5e1;border-radius:10px;font-size:.95rem}
.field input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(79,70,229,.15)}
.btn-primary{background:var(--brand);border-color:var(--brand);width:100%;padding:.65rem;border-radius:10px;font-weight:600}
.btn-primary:hover{background:#4338ca}
.alert{border-radius:10px}
.fine{font-size:.75rem;color:#64748b;text-align:center;margin-top:1.25rem}
</style>
</head>
<body>
<main class="login-shell" aria-labelledby="loginTitle">
  <div class="brand">
    <div class="brand-mark"><i class="fa-solid fa-box-archive"></i></div>
    <div>
      <div class="fw-bold fs-5">Drivew</div>
      <div class="small text-secondary">Cloud file management</div>
    </div>
  </div>

  <div class="login-card">
    <div class="eyebrow">Welcome back</div>
    <h1 id="loginTitle">Sign in to your account</h1>

    <?php if ($error) : ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
        <i class="fa-solid fa-circle-exclamation"></i><span><?= \Drivew\F::esc($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php" novalidate>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(\Drivew\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" required autocomplete="email"
               placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket me-2"></i>Sign in</button>
    </form>

    <p class="fine">Accounts are created by an administrator.<br>Need access? Ask your system admin.</p>
  </div>
</main>
</body>
</html>
