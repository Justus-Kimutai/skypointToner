<?php
define('PRODUCTS_FILE', __DIR__ . '/../products.json');
define('CREDENTIALS_FILE', __DIR__ . '/credentials.json');
define('PRODUCT_IMAGES_DIR', __DIR__ . '/../images/products/');
define('PRODUCT_IMAGES_URL', 'images/products/');
define('PRODUCT_IMAGE_MAX_BYTES', 4 * 1024 * 1024);

$GLOBALS['DEFAULT_CATEGORIES'] = [
    'toner'   => 'Toner Cartridges',
    'copier'  => 'Photocopiers',
    'printer' => 'All-in-One Printers',
    'laptop'  => 'Laptops & Desktops',
    'other'   => 'Other',
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
    $allowed = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        return ['ok' => false, 'error' => 'Photo must be a JPG, PNG, or WEBP file.'];
    }
    if ($file['size'] > PRODUCT_IMAGE_MAX_BYTES) {
        return ['ok' => false, 'error' => 'Photo is too large (max 4MB).'];
    }
    if (!is_dir(PRODUCT_IMAGES_DIR) && !mkdir(PRODUCT_IMAGES_DIR, 0755, true)) {
        return ['ok' => false, 'error' => 'Could not create the images/products folder.'];
    }
    $filename = $id . '.' . $allowed[$ext];
    if (!move_uploaded_file($file['tmp_name'], PRODUCT_IMAGES_DIR . $filename)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded photo.'];
    }
    if ($oldImage !== '' && $oldImage !== PRODUCT_IMAGES_URL . $filename) {
        delete_product_image_file($oldImage);
    }
    return ['ok' => true, 'path' => PRODUCT_IMAGES_URL . $filename];
}

function delete_product_image_file($imagePath) {
    if ($imagePath === '' || $imagePath === null) return;
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

function csrf_check() {
    if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('Security check failed (your session may have expired). Please go back, refresh the page, and try again.');
    }
}
