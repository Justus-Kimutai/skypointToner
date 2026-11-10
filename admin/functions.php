<?php
define('PRODUCTS_FILE', __DIR__ . '/../products.json');
define('CREDENTIALS_FILE', __DIR__ . '/credentials.json');
define('PRODUCT_IMAGES_DIR', __DIR__ . '/../images/products/');
define('PRODUCT_IMAGES_URL', 'images/products/');
define('PRODUCT_IMAGE_MAX_BYTES', 15 * 1024 * 1024);

$GLOBALS['DEFAULT_CATEGORIES'] = [
    'toner'      => 'Toner Cartridges',
    'copier'     => 'Photocopiers',
    'printer'    => 'All-in-One Printers',
    '3d-printer' => '3D Printers',
    'laptop'     => 'Laptops & Desktops',
    'other'      => 'Other',
];

function load_data() {
    $fallback = ['categories' => $GLOBALS['DEFAULT_CATEGORIES'], 'products' => []];
    if (!file_exists(PRODUCTS_FILE)) return $fallback;
    $data = json_decode(file_get_contents(PRODUCTS_FILE), true);
    if (!is_array($data) || !isset($data['products']) || !is_array($data['products'])) return $fallback;
    if (!isset($data['categories']) || !is_array($data['categories']) || empty($data['categories'])) {
        $data['categories'] = $GLOBALS['DEFAULT_CATEGORIES'];
    }
    return $data;
}

function save_data(array $categories, array $products) {
    $fp = fopen(PRODUCTS_FILE, 'c+');
    if (!$fp) return false;
    $ok = flock($fp, LOCK_EX);
    if ($ok) {
        ftruncate($fp, 0);
        rewind($fp);
        $payload = ['categories' => $categories, 'products' => array_values($products)];
        fwrite($fp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $ok;
}

// Deletes the current product photo (if any) then moves the uploaded file into
// place as images/products/{id}.{ext}. Returns ['ok' => true, 'path' => ...]
// on success, or ['ok' => false, 'error' => ...] on failure.
function save_product_image($id, array $file, $oldImage = '') {
    // Detect the real image type from the file contents rather than trusting
    // the extension (phones often send odd names like "IMG_1234" or ".JPEG").
    $byMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    if (!isset($byMime[$mime])) {
        return ['ok' => false, 'error' => 'Photo must be a JPG, PNG, or WEBP image. (iPhone HEIC photos are not supported - change the camera setting to "Most Compatible" or send the photo as JPG.)'];
    }
    if ($file['size'] > PRODUCT_IMAGE_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Photo is too large (max 15MB), so the product was NOT saved. Please choose a smaller photo.'];
    }
    $ext = $byMime[$mime];
    if (!is_dir(PRODUCT_IMAGES_DIR) && !mkdir(PRODUCT_IMAGES_DIR, 0755, true)) {
        return ['ok' => false, 'error' => 'Could not create the images/products folder.'];
    }

    // Shrink on the server too, in case the browser didn't (old browser,
    // direct upload). Falls back to storing the original if GD is missing
    // or the image can't be processed.
    // Unique name per upload ({id}-{timestamp}.ext): images are cached by
    // browsers for 7 days, so reusing "{id}.jpg" would keep showing the old
    // photo after a replacement. The previous file is deleted below.
    $base = $id . '-' . time();
    $shrunk = shrink_product_image($file['tmp_name'], $mime, (int)$info[0], (int)$info[1], (int)$file['size']);
    if ($shrunk !== null) {
        $filename = $base . '.jpg';
        if (file_put_contents(PRODUCT_IMAGES_DIR . $filename, $shrunk) === false) {
            return ['ok' => false, 'error' => 'Could not save the uploaded photo.'];
        }
    } else {
        $filename = $base . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], PRODUCT_IMAGES_DIR . $filename)) {
            return ['ok' => false, 'error' => 'Could not save the uploaded photo.'];
        }
    }
    if ($oldImage !== '' && $oldImage !== PRODUCT_IMAGES_URL . $filename) {
        delete_product_image_file($oldImage);
    }
    return ['ok' => true, 'path' => PRODUCT_IMAGES_URL . $filename];
}

// Returns resized JPEG bytes (max 1600px, quality 85) when the photo is larger
// than needed, or null when it's already small or can't be processed.
function shrink_product_image($path, $mime, $w, $h, $bytes) {
    $maxDim = 1600;
    if ($w <= 0 || $h <= 0) return null;
    if (max($w, $h) <= $maxDim && $bytes <= 1024 * 1024) return null;
    if (!function_exists('imagecreatetruecolor')) return null;

    $loaders = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];
    $load = $loaders[$mime] ?? null;
    if (!$load || !function_exists($load)) return null;
    $src = @$load($path);
    if (!$src) return null;

    $scale = min(1, $maxDim / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // white behind transparent PNGs
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    ob_start();
    $ok = imagejpeg($dst, null, 85);
    $data = ob_get_clean();
    imagedestroy($dst);
    return ($ok && $data !== '' && strlen($data) < $bytes) ? $data : null;
}

function delete_product_image_file($imagePath) {
    if ($imagePath === '' || $imagePath === null) return;
    // Only ever delete files directly inside images/products/.
    if (!preg_match('#^images/products/[A-Za-z0-9._-]+$#', $imagePath) || strpos($imagePath, '..') !== false) return;
    $full = __DIR__ . '/../' . $imagePath;
    if (is_file($full)) {
        unlink($full);
    }
}

// Turns a free-typed category name into a stable URL/JS-safe key, e.g. "Spare Parts!" -> "spare-parts".
function slugify($str) {
    $slug = strtolower(trim((string)$str));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

function next_product_id(array $products) {
    $max = 0;
    foreach ($products as $p) {
        if (isset($p['id']) && (int)$p['id'] > $max) $max = (int)$p['id'];
    }
    return $max + 1;
}

function find_product(array $products, $id) {
    foreach ($products as $p) {
        if ((int)$p['id'] === (int)$id) return $p;
    }
    return null;
}

function load_credentials() {
    if (!file_exists(CREDENTIALS_FILE)) return null;
    $data = json_decode(file_get_contents(CREDENTIALS_FILE), true);
    return is_array($data) ? $data : null;
}

function save_credentials($username, $password) {
    $data = [
        'username'      => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ];
    return file_put_contents(CREDENTIALS_FILE, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// UTF-8 safe truncation without depending on the mbstring extension
// (uses PCRE's built-in unicode mode instead, which is always available).
function truncate_str($str, $len) {
    if (preg_match('/^.{0,' . (int)$len . '}/us', (string)$str, $m) && $m[0] !== $str) {
        return $m[0] . '…';
    }
    return $str;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// When a request body is bigger than PHP's post_max_size, PHP silently drops
// the whole form ($_POST and $_FILES are empty). Detect that so we can show a
// clear "photo too large" message instead of a confusing CSRF failure.
function post_too_large() {
    return $_SERVER['REQUEST_METHOD'] === 'POST'
        && empty($_POST) && empty($_FILES)
        && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

function upload_error_message($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'That photo is too large for the server. Please choose a smaller photo.';
        case UPLOAD_ERR_PARTIAL:
            return 'The photo only partially uploaded (connection dropped). Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'The server could not store the photo (error code ' . (int)$code . '). Please contact your web developer.';
        default:
            return 'There was a problem uploading the photo. Please try again.';
    }
}

function csrf_check() {
    if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('Security check failed (your session may have expired). Please go back, refresh the page, and try again.');
    }
}
