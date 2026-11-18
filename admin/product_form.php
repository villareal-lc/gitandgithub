<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/functions.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;
$product = [
    'category_id' => '', 'product_name' => '', 'barcode' => '', 'price' => '',
    'stock_qty' => 0, 'low_stock_threshold' => 5,
];

if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        header('Location: products.php');
        exit;
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['product_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price       = $_POST['price'] ?? '';
    $threshold   = $_POST['low_stock_threshold'] ?? '';
    $stock       = $_POST['stock_qty'] ?? 0;
    $barcode     = strtoupper(trim($_POST['barcode'] ?? ''));
    $barcodeVal  = ($barcode === '') ? null : $barcode;

    // keep what they typed if validation fails
    $product['product_name']        = $name;
    $product['category_id']         = $category_id;
    $product['barcode']             = $barcode;
    $product['price']               = $price;
    $product['low_stock_threshold'] = $threshold;
    if (!$editing) $product['stock_qty'] = $stock;

    if ($name === '' || $category_id <= 0) {
        $error = 'Product name and category are required.';
    } elseif (!is_numeric($price) || $price < 0) {
        $error = 'Price must be a number, 0 or higher.';
    } elseif (filter_var($threshold, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
        $error = 'Low-stock threshold must be a whole number, 0 or higher.';
    } elseif (!$editing && filter_var($stock, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
        $error = 'Starting stock must be a whole number, 0 or higher.';
    } elseif ($barcodeVal !== null && mb_strlen($barcodeVal) > 64) {
        $error = 'Barcode must be 64 characters or fewer.';
    } else {
        try {
            if ($editing) {
                $stmt = $pdo->prepare(
                    "UPDATE products
                     SET category_id=?, product_name=?, barcode=?, price=?, low_stock_threshold=?
                     WHERE product_id=?"
                );
                $stmt->execute([$category_id, $name, $barcodeVal, $price, $threshold, $id]);
                log_action($pdo, 'PRODUCT_EDIT', "Edited product #$id ($name)");
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO products
                       (category_id, product_name, barcode, price, stock_qty, low_stock_threshold)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$category_id, $name, $barcodeVal, $price, $stock, $threshold]);
                $newId = (int)$pdo->lastInsertId();

                if ($barcodeVal === null) {
                    $catStmt = $pdo->prepare("SELECT category_name FROM categories WHERE category_id = ?");
                    $catStmt->execute([$category_id]);
                    $catName = (string)($catStmt->fetchColumn() ?: 'GEN');
                    $barcodeVal = generate_item_code($pdo, $newId, $catName);
                    $pdo->prepare("UPDATE products SET barcode = ? WHERE product_id = ?")
                        ->execute([$barcodeVal, $newId]);
                }

                $pdo->commit();
                log_action($pdo, 'PRODUCT_ADD', "Added product $name (stock $stock, code $barcodeVal)");
            }
            header('Location: products.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = ($e->getCode() === '23000')
                ? 'That barcode is already assigned to another product.'
                : 'Database error. Nothing was saved.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$page_title = $editing ? 'Edit Product' : 'Add Product';
include '../includes/header.php';
?>
<h1><?= $page_title ?></h1>

<?php if ($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" class="form-box">
  <label>Product name</label>
  <input type="text" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>" required>

  <label>Item code <small>(optional — leave blank to auto-generate, e.g. RAC0007)</small></label>
  <input type="text" name="barcode" maxlength="64"
         value="<?= htmlspecialchars($product['barcode'] ?? '') ?>"
         placeholder="Leave blank for auto-code, or type your own">

  <label>Category</label>
  <select name="category_id" required>
    <option value="">-- choose --</option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= $c['category_id'] ?>" <?= $c['category_id'] == $product['category_id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['category_name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label>Price (₱)</label>
  <input type="number" name="price" step="0.01" min="0" value="<?= htmlspecialchars($product['price']) ?>" required>

  <label>Low-stock threshold</label>
  <input type="number" name="low_stock_threshold" min="0" value="<?= htmlspecialchars($product['low_stock_threshold']) ?>" required>

  <?php if ($editing): ?>
    <label>Current stock</label>
    <input type="text" value="<?= (int)$product['stock_qty'] ?>" disabled>
    <small>Stock changes go through Stock Adjustment (next step), so every change is logged.</small>
  <?php else: ?>
    <label>Starting stock</label>
    <input type="number" name="stock_qty" min="0" value="<?= htmlspecialchars($product['stock_qty']) ?>" required>
  <?php endif; ?>

  <button type="submit">Save</button>
  <a href="products.php">Cancel</a>
</form>
<?php include '../includes/footer.php'; ?>