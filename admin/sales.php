<?php
require '../config/db.php';
require '../includes/auth.php';
require_role('admin');

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$no   = trim($_GET['no'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// only accept properly formatted dates / numbers
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to = '';
if (!ctype_digit($no)) $no = '';

$where = [];
$params = [];
if ($from !== '') {
  $where[] = 'DATE(s.sale_date) >= ?';
  $params[] = $from;
}
if ($to   !== '') {
  $where[] = 'DATE(s.sale_date) <= ?';
  $params[] = $to;
}
if ($no   !== '') {
  $where[] = 's.sale_id = ?';
  $params[] = $no;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Get Total Count and Total Sum of ALL filtered items
$statsSql = "SELECT COUNT(*) as cnt, COALESCE(SUM(s.total_amount), 0) as total FROM sales s" . $whereSql;
$statsStmt = $pdo->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch();
$total_items = $stats['cnt'];
$grand_total = $stats['total'];
$total_pages = max(1, ceil($total_items / $limit));

// Ensure page is within bounds
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// Fetch Paginated Data
$sql = "SELECT s.sale_id, s.sale_date, s.total_amount, s.cash_received, s.change_amount, u.full_name
        FROM sales s JOIN users u ON s.user_id = u.user_id"
  . $whereSql
  . " ORDER BY s.sale_date DESC, s.sale_id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Sliding Window Math
$max_pages_to_show = 5;
$start = max(1, $page - floor($max_pages_to_show / 2));
$end = min($total_pages, $start + $max_pages_to_show - 1);
if ($end - $start + 1 < $max_pages_to_show) {
  $start = max(1, $end - $max_pages_to_show + 1);
}

// Helper to preserve filters in pagination links
function buildPageUrl($p)
{
  $params = $_GET;
  $params['page'] = $p;
  return '?' . http_build_query($params);
}

$page_title = 'Sales History';
include '../includes/header.php';
?>
<h1>Sales History</h1>

<form method="get" class="filter">
  <label>From
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
  </label>
  <label>To
    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
  </label>
  <label>Receipt #
    <input type="text" name="no" value="<?= htmlspecialchars($no) ?>" placeholder="e.g. 1024">
  </label>

  <div class="filter-actions">
    <button type="submit">Filter</button>
    <a href="sales.php" class="reset-btn">Reset</a>
  </div>
</form>

<p style="margin-bottom: 16px; color: var(--muted);">
  Found <strong><?= $total_items ?></strong> sale(s) with a grand total of <strong>₱<?= number_format($grand_total, 2) ?></strong>
</p>

<table>
  <thead>
    <tr>
      <th style="width: 10%;">Receipt #</th>
      <th style="width: 25%;">Date</th>
      <th style="width: 20%;">Cashier</th>
      <th style="width: 12%;">Total</th>
      <th style="width: 12%;">Cash</th>
      <th style="width: 12%;">Change</th>
      <th style="width: 9%;">Action</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($sales as $s): ?>
      <tr>
        <td><strong>#<?= (int)$s['sale_id'] ?></strong></td>
        <td><?= htmlspecialchars($s['sale_date']) ?></td>
        <td><?= htmlspecialchars($s['full_name']) ?></td>
        <td>₱<?= number_format($s['total_amount'], 2) ?></td>
        <td>₱<?= number_format($s['cash_received'], 2) ?></td>
        <td>₱<?= number_format($s['change_amount'], 2) ?></td>
        <td>
          <div class="table-actions">
            <a href="../cashier/receipt.php?id=<?= (int)$s['sale_id'] ?>&from=sales">View</a>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$sales): ?>
      <tr>
        <td colspan="7" style="text-align: center; padding: 32px; color: var(--muted);">
          No sales found matching your criteria.
        </td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- Pagination -->
<div class="pagination-wrap">
  <div class="pagination-info">
    Showing <strong><?= count($sales) ?></strong> of <strong><?= $total_items ?></strong> sales
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <a href="<?= $page > 1 ? buildPageUrl($page - 1) : '#' ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </a>

      <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= buildPageUrl($i) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>

      <a href="<?= $page < $total_pages ? buildPageUrl($page + 1) : '#' ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
      </a>
    </div>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>