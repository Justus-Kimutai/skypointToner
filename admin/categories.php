<?php
require_once __DIR__ . '/auth.php';
require_login();

$data = load_data();
$categories = $data['categories'];
$products = $data['products'];

$errors = [];
$flash = $_GET['flash'] ?? '';
$flashMessages = [
    'added'   => 'Category added.',
    'deleted' => 'Category deleted.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $label = trim($_POST['label'] ?? '');
        $slug = slugify($label);
        if ($label === '' || $slug === '') {
            $errors[] = 'Please enter a category name.';
        } elseif (array_key_exists($slug, $categories)) {
            $errors[] = 'A category with that name already exists.';
        } else {
            $categories[$slug] = $label;
            save_data($categories, $products);
            header('Location: categories.php?flash=added');
            exit;
        }
    } elseif ($action === 'delete') {
        $slug = $_POST['slug'] ?? '';
        if (!array_key_exists($slug, $categories)) {
            $errors[] = 'That category no longer exists.';
        } else {
            $inUse = 0;
            foreach ($products as $p) {
                if (($p['category'] ?? '') === $slug) $inUse++;
            }
            if ($inUse > 0) {
                $errors[] = 'Cannot delete "' . $categories[$slug] . '" — ' . $inUse . ' product' . ($inUse === 1 ? '' : 's') . ' still use' . ($inUse === 1 ? 's' : '') . ' it. Reassign or delete ' . ($inUse === 1 ? 'it' : 'them') . ' first.';
            } else {
                unset($categories[$slug]);
                save_data($categories, $products);
                header('Location: categories.php?flash=deleted');
                exit;
            }
        }
    }
}

$counts = [];
foreach ($products as $p) {
    $cat = $p['category'] ?? '';
    $counts[$cat] = ($counts[$cat] ?? 0) + 1;
}

$labelInput = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') ? ($_POST['label'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Categories — Skypoint Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="wrap" style="max-width:760px;">
    <div class="topbar">
      <div class="brand" style="margin-bottom:0;">
        <div class="badge-icon">SP</div>
        <div>
          <strong>Skypoint Admin</strong>
          <span>Category Manager</span>
        </div>
      </div>
      <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Products</a>
    </div>

    <?php if ($flash && isset($flashMessages[$flash])): ?>
      <div class="alert alert-success"><?= e($flashMessages[$flash]) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <h1>Add Category</h1>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label for="label">Category Name</label>
        <input type="text" id="label" name="label" placeholder="e.g. 3D Printers" value="<?= e($labelInput) ?>">
        <div class="hint">Shown in the sidebar and filter tabs on the Products page.</div>
        <button type="submit" class="btn" style="margin-top:1.25rem;">+ Add Category</button>
      </form>
    </div>

    <div class="card" style="margin-top:1.5rem;">
      <h1>Categories (<?= count($categories) ?>)</h1>
      <?php if (empty($categories)): ?>
        <div class="empty-state">No categories yet.</div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Products</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $slug => $label): $count = $counts[$slug] ?? 0; ?>
          <tr>
            <td><strong><?= e($label) ?></strong><br><span class="muted"><?= e($slug) ?></span></td>
            <td><?= (int)$count ?></td>
            <td>
              <?php if ($count > 0): ?>
                <span class="tag" title="Reassign or delete its products first">In use</span>
              <?php else: ?>
                <form method="post" onsubmit="return confirm('Delete category &quot;<?= e(addslashes($label)) ?>&quot;?');" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="slug" value="<?= e($slug) ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>

    <p class="footer-note">Changes here update <code>products.json</code> and appear on the live Products page immediately.</p>
  </div>
</body>
</html>
