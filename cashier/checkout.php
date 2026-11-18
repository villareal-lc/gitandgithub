<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/functions.php';
require_role('cashier');

$cart = $_SESSION['cart'] ?? [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$cart) {
    header('Location: pos.php');
    exit;
}

$cash = $_POST['cash_received'] ?? '';

try {
    if (!is_numeric($cash)) {
        throw new Exception('Enter a valid cash amount.');
    }

    $pdo->beginTransaction();

    // lock the cart's products so nobody else can sell them mid-checkout
    $ids = array_keys($cart);
    sort($ids);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT product_id, product_name, price, stock_qty
         FROM products WHERE product_id IN ($in) AND is_active = 1 FOR UPDATE"
    );
    $stmt->execute($ids);
    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[$r['product_id']] = $r;
    }

    // re-check everything against the database, not the browser
    $total = 0;
    foreach ($cart as $pid => $qty) {
        if (!isset($rows[$pid])) {
            throw new Exception('An item in the cart is no longer available. Clear the cart and try again.');
        }
        if ($qty > $rows[$pid]['stock_qty']) {
            throw new Exception('Not enough stock for ' . $rows[$pid]['product_name']
                . ' (only ' . $rows[$pid]['stock_qty'] . ' left).');
        }
        $total += $rows[$pid]['price'] * $qty;
    }
    $total = round($total, 2);

    if ($cash < $total) {
        throw new Exception('Cash received is less than the total.');
    }
    $change = round($cash - $total, 2);

    // 1) the sale
    $pdo->prepare(
        "INSERT INTO sales (user_id, total_amount, cash_received, change_amount) VALUES (?, ?, ?, ?)"
    )->execute([$_SESSION['user_id'], $total, $cash, $change]);
    $sale_id = $pdo->lastInsertId();

    // 2) the items + 3) stock deduction
    $addItem = $pdo->prepare(
        "INSERT INTO sales_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)"
    );
    $deduct = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ?");

    foreach ($cart as $pid => $qty) {
        $price = $rows[$pid]['price'];
        $addItem->execute([$sale_id, $pid, $qty, $price, $price * $qty]);
        $deduct->execute([$qty, $pid]);
    }

    log_action($pdo, 'SALE', 'Sale #' . $sale_id . ' total ' . number_format($total, 2));

    $pdo->commit();   // everything above is saved together, or not at all
    $_SESSION['cart'] = [];
    header('Location: receipt.php?id=' . $sale_id);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = 'Database error. The sale was not saved.';
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = $e->getMessage();
}
header('Location: pos.php');
exit;