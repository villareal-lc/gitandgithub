<?php
require '../config/db.php';
require '../includes/auth.php';
require_role('admin');

// 1. Fetch distinct actions for the dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// 2. Fetch all users for the dropdown
$users = $pdo->query("SELECT user_id, full_name FROM users ORDER BY full_name")->fetchAll();

// 3. Capture and Validate Filters
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';
$user_id = (int)($_GET['user_id'] ?? 0);
$filter  = $_GET['action'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 10;
$offset  = ($page - 1) * $limit;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to = '';
if (!in_array($filter, $actions, true)) $filter = '';

// 4. Build Dynamic Query
$where = [];
$params = [];

if ($filter !== '') {
  $where[] = "l.action = ?";
  $params[] = $filter;
}
if ($user_id > 0) {
  $where[] = "l.user_id = ?";
  $params[] = $user_id;
}
if ($from !== '') {
  $where[] = "DATE(l.logged_at) >= ?";
  $params[] = $from;
}
if ($to !== '') {
  $where[] = "DATE(l.logged_at) <= ?";
  $params[] = $to;
}

$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";

// 5. Get Total Count for Pagination
$countSql = "SELECT COUNT(*) FROM audit_logs l JOIN users u ON l.user_id = u.user_id" . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));

// Ensure page is strictly within valid bounds
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// Bulletproof Sliding Window
$max_pages_to_show = 5;
$start = max(1, $page - floor($max_pages_to_show / 2));
$end = min($total_pages, $start + $max_pages_to_show - 1);
if ($end - $start + 1 < $max_pages_to_show) {
  $start = max(1, $end - $max_pages_to_show + 1);
}

// 6. Fetch Paginated Data
$sql = "SELECT l.logged_at, l.action, l.details, u.full_name
        FROM audit_logs l JOIN users u ON l.user_id = u.user_id"
  . $whereSql
  . " ORDER BY l.logged_at DESC, l.log_id DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// 7. Bulletproof Sliding Window Math
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

$page_title = 'Audit Log';
include '../includes/header.php';
?>

<h1>Audit Log</h1>

<form method="get" class="filter">
  <label>From
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
  </label>

  <label>To
    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
  </label>

  <label>User
    <select name="user_id">
      <option value="0">All Users</option>
      <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['user_id'] ?>" <?= $user_id === (int)$u['user_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($u['full_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Action
    <select name="action">
      <option value="">All Actions</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= htmlspecialchars($a) ?>" <?= $a === $filter ? 'selected' : '' ?>>
          <?= htmlspecialchars($a) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>

  <div class="filter-actions">
    <button type="submit">Filter</button>
    <a href="logs.php" class="reset-btn">Reset</a>
  </div>
</form>

<table>
  <thead>
    <tr>
      <th style="width: 20%;">When</th>
      <th style="width: 20%;">User</th>
      <th style="width: 20%;">Action</th>
      <th style="width: 40%;">Details</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= htmlspecialchars($l['logged_at']) ?></td>
        <td><?= htmlspecialchars($l['full_name']) ?></td>
        <td><span class="badge"><?= htmlspecialchars($l['action']) ?></span></td>
        <td><?= htmlspecialchars($l['details']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?>
      <tr>
        <td colspan="4" style="text-align: center; padding: 32px; color: var(--muted);">
          No log entries found matching your criteria.
        </td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- Pagination -->
<div class="pagination-wrap">
  <div class="pagination-info">
    Showing <strong><?= count($logs) ?></strong> of <strong><?= $total_items ?></strong> logs
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

      <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= buildPageUrl($i) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>">
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