<?php
require_once __DIR__ . '/auth.php';

if (load_credentials() === null) {
    header('Location: setup.php');
    exit;
}

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$created = isset($_GET['created']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_throttled()) {
        $error = 'Too many failed attempts. Please wait 30 seconds and try again.';
    } else {
        csrf_check();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (attempt_login($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            register_failed_login();
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Skypoint Toners</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="center-wrap">
    <div class="card login-card">
      <div class="brand">
        <div class="badge-icon">SP</div>
        <div>
          <strong>Skypoint Admin</strong>
          <span>Product Manager</span>
        </div>
      </div>
      <h1>Sign in</h1>

      <?php if ($created): ?><div class="alert alert-success">Account created. Please sign in.</div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>

        <label for="password">Password</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" required>
          <button type="button" class="pw-toggle" data-pw-toggle="password" aria-label="Show password" aria-pressed="false">
            <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a13.16 13.16 0 0 1-3.05 3.94M6.61 6.61C3.35 8.36 1 12 1 12s4 7 11 7a9.26 9.26 0 0 0 5.39-1.61M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M1 1l22 22"/></svg>
          </button>
        </div>

        <button type="submit" class="btn btn-block" style="margin-top:1.5rem;">Sign In</button>
      </form>
    </div>
  </div>
  <script>
    document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-pw-toggle'));
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.classList.toggle('is-visible', !showing);
        btn.setAttribute('aria-pressed', String(!showing));
        btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      });
    });
  </script>
</body>
</html>
