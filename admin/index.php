<?php
require_once __DIR__ . '/auth.php';
require_login();

$data = load_data();
$categories = $data['categories'];
$products = $data['products'];
usort($products, function ($a, $b) {
    return strcmp($a['category'] ?? '', $b['category'] ?? '') ?: strcmp($a['name'] ?? '', $b['name'] ?? '');
});

$flash = $_GET['flash'] ?? '';
$flashMessages = [
    'added'    => 'Product added.',
    'updated'  => 'Product updated.',
    'deleted'  => 'Product deleted.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products — Skypoint Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div class="brand" style="margin-bottom:0;">
        <div class="badge-icon">SP</div>
        <div>
          <strong>Skypoint Admin</strong>
          <span>Product Manager</span>
        </div>
      </div>
      <div class="topbar-actions">
        <span class="who">Signed in as <strong><?= e($_SESSION['admin_username']) ?></strong></span>
        <a href="categories.php" class="btn btn-secondary btn-sm">Categories</a>
        <a href="account.php" class="btn btn-secondary btn-sm">Account</a>
        <a href="../products.html" target="_blank" class="btn btn-secondary btn-sm">View Live Page</a>
        <a href="logout.php" class="btn btn-secondary btn-sm">Log Out</a>
      </div>
    </div>

    <?php if ($flash && isset($flashMessages[$flash])): ?>
      <div class="alert alert-success"><?= e($flashMessages[$flash]) ?></div>
    <?php endif; ?>

    <div class="card">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.75rem;">
        <h1 style="margin:0;">Products (<?= count($products) ?>)</h1>
        <a href="edit.php" class="btn">+ Add Product</a>
      </div>

      <?php if (empty($products)): ?>
        <div class="empty-state">No products yet. Click "Add Product" to create the first one.</div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th></th>
            <th>Category</th>
            <th>Name</th>
            <th>Price</th>
            <th>Badge</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
          <tr>
            <td>
              <?php if (!empty($p['image'])): ?>
                <img src="../<?= e($p['image']) ?>" alt="" style="width:48px; height:36px; object-fit:cover; border-radius:4px; border:1px solid var(--border);">
              <?php else: ?>
                <div style="width:48px; height:36px; border-radius:4px; background:var(--bg); border:1px solid var(--border);"></div>
              <?php endif; ?>
            </td>
            <td><span class="tag"><?= e($categories[$p['category']] ?? $p['category']) ?></span></td>
            <td>
              <strong><?= e($p['name']) ?></strong><br>
              <span class="muted"><?= e(truncate_str($p['specs'] ?? '', 80)) ?></span>
            </td>
            <td>
              <?php if (($p['priceType'] ?? 'quote') === 'fixed'): ?>
                KSh <?= e($p['price']) ?>
              <?php elseif (($p['priceType'] ?? '') === 'from'): ?>
                From KSh <?= e($p['price']) ?>
              <?php else: ?>
                <span class="muted"><?= e($p['quoteLabel'] ?? 'Get Quote') ?></span>
              <?php endif; ?>
            </td>
            <td><?= $p['badge'] ? e($p['badge']) : '<span class="muted">—</span>' ?></td>
            <td>
              <div class="row-actions">
                <a href="edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                <form method="post" action="delete.php" onsubmit="return confirm('Delete &quot;<?= e(addslashes($p['name'])) ?>&quot;? This cannot be undone.');" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </div>
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
