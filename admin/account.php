<?php
require_once __DIR__ . '/auth.php';
require_login();

$error = '';
$success = '';
$creds = load_credentials();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $currentPassword = $_POST['current_password'] ?? '';
    $newUsername = trim($_POST['username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPassword, $creds['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif ($newUsername === '' || strlen($newUsername) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== '' && $newPassword !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $passwordToSave = $newPassword !== '' ? $newPassword : null;
        if ($passwordToSave === null) {
            // Keep existing password hash, only change username.
            $data = ['username' => $newUsername, 'password_hash' => $creds['password_hash']];
            file_put_contents(CREDENTIALS_FILE, json_encode($data, JSON_PRETTY_PRINT));
        } else {
            save_credentials($newUsername, $passwordToSave);
        }
        $_SESSION['admin_username'] = $newUsername;
        $creds = load_credentials();
        $success = 'Account updated.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account — Skypoint Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="wrap" style="max-width:520px;">
    <div class="topbar">
      <div class="brand" style="margin-bottom:0;">
        <div class="badge-icon">SP</div>
        <div>
          <strong>Skypoint Admin</strong>
          <span>Account Settings</span>
        </div>
      </div>
      <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Products</a>
    </div>

    <div class="card">
      <h1>Account Settings</h1>

      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($creds['username'] ?? '') ?>" required>

        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" minlength="8">
        <div class="hint">Leave blank to keep your current password.</div>

        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="8">

        <label for="current_password">Current Password (required to save changes)</label>
        <input type="password" id="current_password" name="current_password" required>

        <button type="submit" class="btn btn-block" style="margin-top:1.5rem;">Save Changes</button>
      </form>
    </div>
  </div>
</body>
</html>
