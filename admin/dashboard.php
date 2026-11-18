<?php
require '../config/db.php';
require '../includes/auth.php';
require_role('admin');

$today = $pdo->query(
  "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total
     FROM sales WHERE DATE(sale_date) = CURDATE()"
)->fetch();

$stats = $pdo->query(
  "SELECT COUNT(*) AS total,
            SUM(stock_qty = 0) AS out_count,
            SUM(stock_qty > 0 AND stock_qty <= low_stock_threshold) AS low_count
     FROM products WHERE is_active = 1"
)->fetch();

// Fetch all low stock items (no limit, JS will handle showing/hiding)
$lowStock = $pdo->query(
  "SELECT p.product_name, p.stock_qty, p.low_stock_threshold, c.category_name
     FROM products p JOIN categories c ON p.category_id = c.category_id
     WHERE p.is_active = 1 AND p.stock_qty <= p.low_stock_threshold
     ORDER BY p.stock_qty ASC, p.product_name"
)->fetchAll();

// Fetch top 5 best sellers
$best = $pdo->query(
  "SELECT p.product_name, SUM(si.quantity) AS sold
     FROM sales_items si JOIN products p ON si.product_id = p.product_id
     GROUP BY p.product_id, p.product_name
     ORDER BY sold DESC LIMIT 5"
)->fetchAll();

$page_title = 'Dashboard';
include '../includes/header.php';
?>

<h1>Dashboard</h1>

<div class="cards">
  <div class="card"><span>Today's Sales</span><strong>₱<?= number_format($today['total'], 2) ?></strong></div>
  <div class="card"><span>Total Products</span><strong><?= (int)$stats['total'] ?></strong></div>
  <div class="card"><span>Low Stock Items</span><strong><?= (int)$stats['low_count'] ?></strong></div>
  <div class="card <?= $stats['out_count'] > 0 ? 'alert' : '' ?>">
    <span>Out of Stock</span><strong><?= (int)$stats['out_count'] ?></strong>
  </div>
</div>

<!-- ================= LOW STOCK ALERTS ================= -->
<h2>Low-stock alerts</h2>
<table>
  <thead>
    <tr>
      <th style="width: 35%;">Product</th>
      <th style="width: 20%;">Category</th>
      <th style="width: 15%;">Stock</th>
      <th style="width: 15%;">Threshold</th>
      <th style="width: 15%;">Status</th>
    </tr>
  </thead>
  <tbody id="lowStockTableBody">
    <?php
    $count = 0;
    foreach ($lowStock as $p):
      $count++;
      // Hide rows after the first 5 using a CSS class and inline style
      $hiddenClass = ($count > 5) ? 'hidden-low-stock' : '';
      $hiddenStyle = ($count > 5) ? 'style="display: none;"' : '';
    ?>
      <tr class="low-stock-item <?= $hiddenClass ?>" <?= $hiddenStyle ?>>
        <td><?= htmlspecialchars($p['product_name']) ?></td>
        <td><?= htmlspecialchars($p['category_name']) ?></td>
        <td><?= (int)$p['stock_qty'] ?></td>
        <td><?= (int)$p['low_stock_threshold'] ?></td>
        <td>
          <?php if ((int)$p['stock_qty'] <= 0): ?>
            <span class="status out-of-stock">Out of Stock</span>
          <?php else: ?>
            <span class="status low-stock">Low Stock</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$lowStock): ?>
      <tr>
        <td colspan="5" style="text-align: center; padding: 24px;">All products are above their threshold.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- Action Row: Link on left, Buttons on right -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; margin-bottom: 32px;">
  <div>
    <a href="stock.php" style="font-weight: 500; font-size: 13px;">Go to Stock Adjustments &rarr;</a>
  </div>

  <?php if (count($lowStock) > 5): ?>
    <div style="display: flex; gap: 8px;">
      <button type="button" id="btnShowLess" class="btn" style="display: none; background: transparent; color: var(--muted); border: 1px solid transparent;">
        Show Less
      </button>
      <button type="button" id="btnShowMore" class="btn" style="background: var(--cream-soft); color: var(--burgundy); border: 1px solid var(--border);">
        Show More
      </button>
    </div>
  <?php endif; ?>
</div>

<!-- ================= BEST SELLING ================= -->
<h2>Best-selling products</h2>
<table>
  <thead>
    <tr>
      <th style="width: 80%;">Product</th>
      <th style="width: 20%;">Units Sold</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($best as $b): ?>
      <tr>
        <td><?= htmlspecialchars($b['product_name']) ?></td>
        <td><?= (int)$b['sold'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$best): ?>
      <tr>
        <td colspan="2" style="text-align: center; padding: 24px;">No sales yet.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- JavaScript for "Show More" / "Show Less" functionality -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const items = document.querySelectorAll('.low-stock-item');
    const btnShowMore = document.getElementById('btnShowMore');
    const btnShowLess = document.getElementById('btnShowLess');

    // Only run if the buttons exist (meaning there are more than 5 items)
    if (!btnShowMore || !btnShowLess) return;

    let visibleCount = 5;
    const step = 5;

    function updateVisibility() {
      items.forEach((item, index) => {
        if (index < visibleCount) {
          item.style.display = ''; // Reset to default (table-row)
        } else {
          item.style.display = 'none';
        }
      });

      // Toggle button visibility based on current state
      if (visibleCount >= items.length) {
        btnShowMore.style.display = 'none';
        btnShowLess.style.display = 'inline-flex';
      } else if (visibleCount <= 5) {
        btnShowMore.style.display = 'inline-flex';
        btnShowLess.style.display = 'none';
      } else {
        btnShowMore.style.display = 'inline-flex';
        btnShowLess.style.display = 'inline-flex';
      }
    }

    btnShowMore.addEventListener('click', function() {
      visibleCount += step;
      updateVisibility();
    });

    btnShowLess.addEventListener('click', function() {
      visibleCount = 5;
      updateVisibility();
    });
  });
</script>

<?php include '../includes/footer.php'; ?>