<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['product_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE products SET is_active = 1 - is_active WHERE product_id = ?");
    $stmt->execute([$id]);
    log_action($pdo, 'PRODUCT_TOGGLE', "Toggled active status of product #$id");
}
header('Location: products.php');
exit;