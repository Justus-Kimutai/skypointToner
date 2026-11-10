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

if (post_too_large()) {
    $errors[] = 'That photo is too large for the server, so nothing was saved. Please choose a smaller photo and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $input = [
        'category'      => $_POST['category'] ?? 'toner',
        'categoryLabel' => trim($_POST['categoryLabel'] ?? ''),
        'name'          => trim($_POST['name'] ?? ''),
        'specs'         => trim($_POST['specs'] ?? ''),
        'image'         => $existing['image'] ?? '',
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
        $errors[] = upload_error_message($_FILES['image']['error']);
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
<link rel="stylesheet" href="style.css?v=20261110b">
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
            <div class="photo-row">
              <?php if (!empty($input['image'])): ?>
              <figure class="photo-box" id="currentPhotoBox">
                <button type="button" class="photo-remove" id="removePhotoBtn" title="Remove this photo" aria-label="Remove current photo">&times;</button>
                <img src="../<?= e($input['image']) ?>" alt="<?= e($input['name']) ?>" data-lightbox data-caption="Current photo" data-lightbox-remove="removePhotoBtn" tabindex="0" title="Click to enlarge">
                <figcaption>Current photo &middot; click to enlarge</figcaption>
              </figure>
              <?php endif; ?>
              <figure class="photo-box photo-box--new" id="newPhotoBox" hidden>
                <img id="imagePreview" alt="New photo preview" data-lightbox data-caption="New photo (not saved yet)" tabindex="0" title="Click to enlarge">
                <figcaption>New photo &middot; not saved yet</figcaption>
              </figure>
            </div>
            <input type="hidden" name="removeImage" id="removeImage" value="">
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
            <div class="hint" id="imageHint">JPG, PNG, or WEBP, max 15MB. Large phone photos are shrunk automatically before upload. Uploading a new photo replaces the current one.</div>
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

        <div class="confirm-backdrop" id="removeConfirm" hidden>
          <div class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="removeConfirmTitle" aria-describedby="removeConfirmText">
            <h2 id="removeConfirmTitle">Remove this photo?</h2>
            <p id="removeConfirmText">The photo will be deleted from the website straight away. Your other changes on this page will be saved too. This cannot be undone.</p>
            <div class="confirm-actions">
              <button type="button" class="btn btn-secondary" id="removeCancel">Cancel</button>
              <button type="button" class="btn btn-danger" id="removeOk">Yes, remove photo</button>
            </div>
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

      // Remove current photo: red x on the photo (or in the enlarged view)
      // opens a confirmation dialog; confirming saves with the photo removed.
      var removePhotoBtn = document.getElementById('removePhotoBtn');
      var removeConfirm = document.getElementById('removeConfirm');
      if (removePhotoBtn && removeConfirm) {
        var removeOk = document.getElementById('removeOk');
        var removeCancel = document.getElementById('removeCancel');
        var closeConfirm = function () { removeConfirm.hidden = true; removePhotoBtn.focus(); };
        removePhotoBtn.addEventListener('click', function () {
          removeConfirm.hidden = false;
          removeCancel.focus();
        });
        removeCancel.addEventListener('click', closeConfirm);
        removeConfirm.addEventListener('click', function (e) { if (e.target === removeConfirm) closeConfirm(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !removeConfirm.hidden) closeConfirm(); });
        removeOk.addEventListener('click', function () {
          document.getElementById('removeImage').value = '1';
          var fi = document.getElementById('image');
          if (fi) fi.value = '';
          removeOk.disabled = true;
          removeOk.textContent = 'Removing...';
          document.getElementById('productForm').submit();
        });
      }

      // Shrink photos in the browser before upload. Phone photos are often
      // 3-10MB; resized to max 1600px JPEG they come out ~150-400KB, upload
      // fast on mobile data, and load fast on the storefront.
      var MAX_DIM = 1600;
      var MAX_BYTES = <?= (int)PRODUCT_IMAGE_MAX_BYTES ?>;
      var fileInput = document.getElementById('image');
      var hint = document.getElementById('imageHint');
      var preview = document.getElementById('imagePreview');
      var newBox = document.getElementById('newPhotoBox');
      var currentBox = document.getElementById('currentPhotoBox');
      function showPreview(src) {
        if (src) { preview.src = src; newBox.hidden = false; }
        else { preview.removeAttribute('src'); newBox.hidden = true; }
        if (currentBox) currentBox.classList.toggle('is-replaced', !!src);
      }
      var submitBtn = document.querySelector('#productForm button[type="submit"]');
      var originalHint = hint.textContent;

      function size(bytes) { return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + 'MB' : Math.round(bytes / 1024) + 'KB'; }
      function setHint(text, isError) { hint.textContent = text; hint.style.color = isError ? '#c0392b' : ''; }
      function rejectTooLarge(bytes) {
        fileInput.value = '';
        showPreview(null);
        submitBtn.disabled = false;
        setHint('Photo is too large (' + size(bytes) + '). Maximum is ' + size(MAX_BYTES) + '. Please choose a smaller photo.', true);
      }

      // Hard stop: never submit the form with a photo over the limit.
      document.getElementById('productForm').addEventListener('submit', function (e) {
        var f = fileInput.files && fileInput.files[0];
        if (f && f.size > MAX_BYTES) {
          e.preventDefault();
          rejectTooLarge(f.size);
          fileInput.scrollIntoView({ block: 'center' });
        }
      });

      fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        showPreview(null);
        setHint(originalHint);
        if (!file) return;

        var url = URL.createObjectURL(file);
        var img = new Image();
        submitBtn.disabled = true;
        setHint('Preparing photo...');

        img.onload = function () {
          var w = img.naturalWidth, h = img.naturalHeight;
          var scale = Math.min(1, MAX_DIM / Math.max(w, h));
          var small = scale === 1 && file.size <= 1048576 && /^image\/(jpeg|png|webp)$/.test(file.type);
          if (small || typeof DataTransfer === 'undefined') {
            if (file.size > MAX_BYTES) { URL.revokeObjectURL(url); rejectTooLarge(file.size); return; }
            showPreview(url);
            setHint('Ready: ' + file.name + ' (' + size(file.size) + '). Click "' + submitBtn.textContent + '" to save.');
            submitBtn.disabled = false;
            return;
          }
          var canvas = document.createElement('canvas');
          canvas.width = Math.round(w * scale);
          canvas.height = Math.round(h * scale);
          var ctx = canvas.getContext('2d');
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          canvas.toBlob(function (blob) {
            URL.revokeObjectURL(url);
            submitBtn.disabled = false;
            if (!blob) { setHint(originalHint); return; }
            var name = (file.name.replace(/\.[^.]+$/, '') || 'photo') + '.jpg';
            var resized = new File([blob], name, { type: 'image/jpeg' });
            if (resized.size > MAX_BYTES) { rejectTooLarge(resized.size); return; }
            var dt = new DataTransfer();
            dt.items.add(resized);
            fileInput.files = dt.files;
            showPreview(URL.createObjectURL(resized));
            setHint('Ready: photo shrunk from ' + size(file.size) + ' to ' + size(resized.size) + '. Click "' + submitBtn.textContent + '" to save.');
          }, 'image/jpeg', 0.85);
        };
        img.onerror = function () {
          URL.revokeObjectURL(url);
          submitBtn.disabled = false;
          fileInput.value = '';
          setHint('This photo format can\'t be used (e.g. iPhone HEIC). Please choose a JPG or PNG photo.', true);
        };
        img.src = url;
      });
    })();
  </script>
<script src="../js/lightbox.js?v=20261110b"></script>
</body>
</html>
