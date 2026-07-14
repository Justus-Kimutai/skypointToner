<?php
require_once __DIR__ . '/auth.php';
require_login();

$data = load_data();
$categories = $data['categories'];
$products = $data['products'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
$existing = $id ? find_product($products, $id) : null;
$isEdit = $existing !== null;

if ($id && !$existing) {
    header('Location: index.php');
    exit;
}

$errors = [];
$input = $existing ?: [
    'category'     => 'toner',
    'categoryLabel'=> '',
    'name'         => '',
    'specs'        => '',
    'image'        => '',
    'badge'        => '',
    'priceType'    => 'quote',
    'price'        => '',
    'quoteLabel'   => 'Get Quote',
    'waProduct'    => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $input = [
        'category'      => $_POST['category'] ?? 'toner',
        'categoryLabel' => trim($_POST['categoryLabel'] ?? ''),
        'name'          => trim($_POST['name'] ?? ''),
        'specs'         => trim($_POST['specs'] ?? ''),
        'image'         => trim($_POST['currentImage'] ?? ''),
        'badge'         => trim($_POST['badge'] ?? ''),
        'priceType'     => $_POST['priceType'] ?? 'quote',
        'price'         => trim($_POST['price'] ?? ''),
        'quoteLabel'    => trim($_POST['quoteLabel'] ?? '') ?: 'Get Quote',
        'waProduct'     => trim($_POST['waProduct'] ?? ''),
    ];

    if (!array_key_exists($input['category'], $categories)) {
        $errors[] = 'Please choose a valid category.';
    }
    if ($input['categoryLabel'] === '') {
        $errors[] = 'Category label is required (e.g. "Toner Cartridge").';
    }
    if ($input['name'] === '') {
        $errors[] = 'Product name is required.';
    }
    if ($input['specs'] === '') {
        $errors[] = 'Description / specs is required.';
    }
    if (!in_array($input['priceType'], ['quote', 'fixed', 'from'], true)) {
        $errors[] = 'Invalid price type.';
    }
    if (in_array($input['priceType'], ['fixed', 'from'], true)) {
        $digits = preg_replace('/[^0-9]/', '', $input['price']);
        if ($digits === '') {
            $errors[] = 'Please enter a price amount.';
        } else {
            $input['price'] = number_format((int)$digits);
        }
    } else {
        $input['price'] = '';
    }
    if ($input['waProduct'] === '') {
        $input['waProduct'] = $input['name'];
    }

    $hasNewImage = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($hasNewImage && $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'There was a problem uploading the photo. Please try again.';
        $hasNewImage = false;
    }
    $shouldRemove = !$hasNewImage && !empty($_POST['removeImage']);

    if (empty($errors)) {
        $productId = $isEdit ? $id : next_product_id($products);

        if ($hasNewImage) {
            $result = save_product_image($productId, $_FILES['image'], $input['image']);
            if ($result['ok']) {
                $input['image'] = $result['path'];
            } else {
                $errors[] = $result['error'];
            }
        } elseif ($shouldRemove) {
            delete_product_image_file($input['image']);
            $input['image'] = '';
        }
    }

    if (empty($errors)) {
        if ($isEdit) {
            foreach ($products as &$p) {
                if ((int)$p['id'] === $id) {
                    $p = array_merge(['id' => $id], $input);
                    break;
                }
            }
            unset($p);
            $flash = 'updated';
        } else {
            $products[] = array_merge(['id' => $productId], $input);
            $flash = 'added';
        }
        save_data($categories, $products);
        header('Location: index.php?flash=' . $flash);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isEdit ? 'Edit Product' : 'Add Product' ?> — Skypoint Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="wrap" style="max-width:760px;">
    <div class="topbar">
      <div class="brand" style="margin-bottom:0;">
        <div class="badge-icon">SP</div>
        <div>
          <strong>Skypoint Admin</strong>
          <span>Product Manager</span>
        </div>
      </div>
      <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Products</a>
    </div>

    <div class="card">
      <h1><?= $isEdit ? 'Edit Product' : 'Add Product' ?></h1>

      <?php if ($errors): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" id="productForm" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
        <input type="hidden" name="currentImage" value="<?= e($input['image']) ?>">

        <div class="form-grid">
          <div>
            <label for="category">Category</label>
            <select id="category" name="category" data-labels="<?= e(json_encode($categories)) ?>">
              <?php foreach ($categories as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $input['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="categoryLabel">Category Label (shown on card)</label>
            <input type="text" id="categoryLabel" name="categoryLabel" value="<?= e($input['categoryLabel']) ?>" placeholder="e.g. Toner Cartridge">
          </div>

          <div class="full">
            <label for="name">Product Name</label>
            <input type="text" id="name" name="name" value="<?= e($input['name']) ?>" placeholder="e.g. Kyocera TK-3130 Toner">
          </div>

          <div class="full">
            <label for="specs">Description / Specs</label>
            <textarea id="specs" name="specs" placeholder="Separate details with a middle dot: Compatible with ... · High-yield · Bulk supply available"><?= e($input['specs']) ?></textarea>
          </div>

          <div class="full">
            <label for="image">Product Photo</label>
            <?php if (!empty($input['image'])): ?>
              <div style="margin-bottom:0.5rem;">
                <img src="../<?= e($input['image']) ?>" alt="" style="width:120px; height:90px; object-fit:cover; border-radius:8px; border:1px solid var(--border);">
              </div>
              <label style="display:inline-flex; align-items:center; gap:0.4rem; font-weight:400;">
                <input type="checkbox" name="removeImage" value="1" style="width:auto;"> Remove current photo
              </label>
            <?php endif; ?>
            <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
            <div class="hint">JPG, PNG, or WEBP, max 4MB. Uploading a new photo replaces the current one.</div>
          </div>

          <div>
            <label for="badge">Badge Text (optional)</label>
            <input type="text" id="badge" name="badge" value="<?= e($input['badge']) ?>" placeholder="e.g. Best Seller">
            <div class="hint">Shown as a small gold tag on the card. Leave blank for no badge.</div>
          </div>

          <div class="full">
            <label>Pricing</label>
            <div class="radio-row">
              <label><input type="radio" name="priceType" value="quote" <?= $input['priceType'] === 'quote' ? 'checked' : '' ?>> Quote only</label>
              <label><input type="radio" name="priceType" value="fixed" <?= $input['priceType'] === 'fixed' ? 'checked' : '' ?>> Fixed price</label>
              <label><input type="radio" name="priceType" value="from" <?= $input['priceType'] === 'from' ? 'checked' : '' ?>> Starting "From"</label>
            </div>
          </div>

          <div class="full price-fields" id="quoteFields">
            <label for="quoteLabel">Quote Button Text</label>
            <input type="text" id="quoteLabel" name="quoteLabel" value="<?= e($input['quoteLabel']) ?>" placeholder="e.g. Get Quote / Wholesale Price">
          </div>

          <div class="full price-fields" id="priceFields">
            <label for="price">Price Amount (KSh)</label>
            <input type="text" inputmode="numeric" id="price" name="price" value="<?= e($input['price']) ?>" placeholder="e.g. 12500">
            <div class="hint">Numbers only — formatted automatically (e.g. 12,500).</div>
          </div>

          <div class="full">
            <label for="waProduct">WhatsApp Order Text</label>
            <input type="text" id="waProduct" name="waProduct" value="<?= e($input['waProduct']) ?>" placeholder="Defaults to the product name">
            <div class="hint">This is what gets sent when a customer taps the WhatsApp button. Leave blank to reuse the product name.</div>
          </div>
        </div>

        <button type="submit" class="btn" style="margin-top:1.5rem;"><?= $isEdit ? 'Save Changes' : 'Add Product' ?></button>
        <a href="index.php" class="btn btn-secondary" style="margin-top:1.5rem;">Cancel</a>
      </form>
    </div>
  </div>

  <script>
    (function () {
      var priceRadios = document.querySelectorAll('input[name="priceType"]');
      var quoteFields = document.getElementById('quoteFields');
      var priceFields = document.getElementById('priceFields');

      function updatePriceFields() {
        var val = document.querySelector('input[name="priceType"]:checked').value;
        quoteFields.classList.toggle('active', val === 'quote');
        priceFields.classList.toggle('active', val === 'fixed' || val === 'from');
      }
      priceRadios.forEach(function (r) { r.addEventListener('change', updatePriceFields); });
      updatePriceFields();

      // Auto-fill Category Label from the chosen category, but only while the
      // field still matches a known category label (i.e. hasn't been hand-edited).
      var categorySelect = document.getElementById('category');
      var categoryLabelField = document.getElementById('categoryLabel');
      var categoryLabels = JSON.parse(categorySelect.getAttribute('data-labels') || '{}');
      categorySelect.addEventListener('change', function () {
        var known = Object.values(categoryLabels);
        if (categoryLabelField.value === '' || known.indexOf(categoryLabelField.value) !== -1) {
          categoryLabelField.value = categoryLabels[categorySelect.value] || '';
        }
      });
    })();
  </script>
</body>
</html>
