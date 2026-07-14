<?php
require_once __DIR__ . '/auth.php';
require_setup_not_done();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($username === '' || strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        save_credentials($username, $password);
        header('Location: login.php?created=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Up Admin Account — Skypoint Toners</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="center-wrap">
    <div class="card login-card">
      <div class="brand">
        <div class="badge-icon">SP</div>
        <div>
          <strong>Skypoint Admin</strong>
          <span>First-time setup</span>
        </div>
      </div>
      <h1>Create your admin account</h1>
      <p class="hint" style="margin-bottom:1.25rem;">This page only works once. After you create your login, it will refuse to run again — use the "Change Password" page inside the dashboard if you need to update it later.</p>

      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required value="<?= e($_POST['username'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8">
        <div class="hint">At least 8 characters.</div>

        <label for="confirm">Confirm Password</label>
        <input type="password" id="confirm" name="confirm" required minlength="8">

        <button type="submit" class="btn btn-block" style="margin-top:1.5rem;">Create Account</button>
      </form>
    </div>
  </div>
</body>
</html>
