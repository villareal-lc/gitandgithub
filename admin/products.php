<?php
require '../config/db.php';
require '../includes/auth.php';
require_role('admin');

// --- 1. Capture Filters & Pagination ---
$q            = trim($_GET['q'] ?? '');
$category_id  = (int)($_GET['category'] ?? 0);
$status       = $_GET['status'] ?? '';
$stock_status = $_GET['stock_status'] ?? '';
$min_price    = $_GET['min_price'] ?? '';
$max_price    = $_GET['max_price'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 10;
$offset       = ($page - 1) * $limit;

// --- 2. Build Dynamic Query ---
$where = [];
$params = [];

if ($q !== '') {
  $where[] = "(p.product_name LIKE ? OR p.barcode LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}
if ($category_id > 0) {
  $where[] = "p.category_id = ?";
  $params[] = $category_id;
}
if ($status !== '') {
  $where[] = "p.is_active = ?";
  $params[] = ($status === 'active') ? 1 : 0;
}
if ($stock_status !== '') {
  if ($stock_status === 'in') {
    $where[] = "p.stock_qty > p.low_stock_threshold";
  } elseif ($stock_status === 'low') {
    $where[] = "p.stock_qty > 0 AND p.stock_qty <= p.low_stock_threshold";
  } elseif ($stock_status === 'out') {
    $where[] = "p.stock_qty = 0";
  }
}
if ($min_price !== '' && is_numeric($min_price)) {
  $where[] = "p.price >= ?";
  $params[] = (float)$min_price;
}
if ($max_price !== '' && is_numeric($max_price)) {
  $where[] = "p.price <= ?";
  $params[] = (float)$max_price;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// --- 3. Get Total Count for Pagination ---
$countSql = "SELECT COUNT(*) FROM products p JOIN categories c ON p.category_id = c.category_id $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));

// Ensure page doesn't exceed total pages
if ($page > $total_pages) {
  $page = $total_pages;
  $offset = ($page - 1) * $limit;
}

// --- 4. Fetch Paginated Data ---
$sql = "SELECT p.*, c.category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.category_id 
        $whereSql 
        ORDER BY p.product_name ASC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// --- 5. Fetch Categories for Filter Dropdown ---
$categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();

// Helper to preserve filters in pagination links
function buildPageUrl($p)
{
  $params = $_GET;
  $params['page'] = $p;
  return '?' . http_build_query($params);
}

$page_title = 'Products';
include '../includes/header.php';
?>

<div class="page-head">
  <h1>Products</h1>
  <a class="btn" href="product_form.php">+ Add Product</a>
</div>

<!-- Filter Bar (Grid Layout) -->
<form method="get" class="filter">
  <label>Search
    <input type="text" name="q" placeholder="Name or barcode…" value="<?= htmlspecialchars($q) ?>">
  </label>

  <label>Category
    <select name="category">
      <option value="0">All Categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['category_id'] ?>" <?= $category_id === (int)$c['category_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['category_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Stock Status
    <select name="stock_status">
      <option value="">Any Status</option>
      <option value="in" <?= $stock_status === 'in' ? 'selected' : '' ?>>In Stock</option>
      <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?>>Low Stock</option>
      <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
    </select>
  </label>

  <label>Account Status
    <select name="status">
      <option value="">Any</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
  </label>

  <label>Min Price
    <input type="number" name="min_price" step="0.01" min="0" value="<?= htmlspecialchars($min_price) ?>" placeholder="₱0">
  </label>

  <label>Max Price
    <input type="number" name="max_price" step="0.01" min="0" value="<?= htmlspecialchars($max_price) ?>" placeholder="₱9999">
  </label>

  <div class="filter-actions">
    <button type="submit">Filter</button>
    <a href="products.php" class="reset-btn">Reset</a>
  </div>
</form>

<!-- Products Table -->
<table>
  <thead>
    <tr>
      <th style="width: 10%;">Code</th>
      <th style="width: 28%;">Name</th>
      <th style="width: 14%;">Category</th>
      <th style="width: 10%;">Price</th>
      <th style="width: 8%;">Stock</th>
      <th style="width: 10%;">Threshold</th>
      <th style="width: 10%;">Status</th>
      <th style="width: 10%;">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($products as $p):
      $qty = (int)$p['stock_qty'];
      $thr = (int)$p['low_stock_threshold'];
    ?>
      <tr>
        <td><code><?= htmlspecialchars($p['barcode'] ?: '—') ?></code></td>
        <td><?= htmlspecialchars($p['product_name']) ?></td>
        <td><?= htmlspecialchars($p['category_name']) ?></td>
        <td>₱<?= number_format($p['price'], 2) ?></td>
        <td><?= $qty ?></td>
        <td><?= $thr ?></td>
        <td>
          <?php if ($qty <= 0): ?>
            <span class="status out-of-stock">Out of Stock</span>
          <?php elseif ($qty <= $thr): ?>
            <span class="status low-stock">Low Stock</span>
          <?php else: ?>
            <span class="status in-stock">In Stock</span>
          <?php endif; ?>

          <?php if (!$p['is_active']): ?>
            <span class="badge" style="margin-left:6px; background:#FBE7E5; color:#B3261E;">Inactive</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="table-actions">
            <a href="product_form.php?id=<?= (int)$p['product_id'] ?>">Edit</a>
            <span class="divider">|</span>
            <form method="post" action="product_toggle.php" class="inline">
              <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
              <button type="submit" class="link-btn <?= $p['is_active'] ? 'btn-text-danger' : '' ?>" style="<?= !$p['is_active'] ? 'color: var(--green);' : '' ?>">
                <?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?>
      <tr>
        <td colspan="8" style="text-align: center; padding: 32px; color: var(--muted);">
          No products match your current filters.
        </td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- Pagination -->
<div class="pagination-wrap">
  <div class="pagination-info">
    Showing <strong><?= count($products) ?></strong> of <strong><?= $total_items ?></strong> products
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <!-- Previous Button -->
      <a href="<?= $page > 1 ? buildPageUrl($page - 1) : '#' ?>"
        class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>

      <?php
      // Show a maximum of 5 page numbers (sliding window)
      $start = max(1, $page - 2);
      $end = min($total_pages, $start + 4);
      if ($end - $start < 4) $start = max(1, $end - 4);

      for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= buildPageUrl($i) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>

      <!-- Next Button -->
      <a href="<?= $page < $total_pages ? buildPageUrl($page + 1) : '#' ?>"
        class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
      </a>
    </div>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>