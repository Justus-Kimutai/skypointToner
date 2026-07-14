<?php
require_once __DIR__ . '/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_check();

$id = (int)($_POST['id'] ?? 0);
$data = load_data();
$products = $data['products'];
$target = find_product($products, $id);
$filtered = array_values(array_filter($products, function ($p) use ($id) {
    return (int)$p['id'] !== $id;
}));

if (count($filtered) !== count($products)) {
    if ($target && !empty($target['image'])) {
        delete_product_image_file($target['image']);
    }
    save_data($data['categories'], $filtered);
    header('Location: index.php?flash=deleted');
} else {
    header('Location: index.php');
}
exit;
