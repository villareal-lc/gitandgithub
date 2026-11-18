<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/functions.php';
require_role('admin');

// Preserve current filter and pagination query string for form action + redirects
$current_qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';

// ---- toggle action (kept inline; only mutating action on this page) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = (int) ($_POST['user_id'] ?? 0);
    if ($id === (int) $_SESSION['user_id']) {
        $_SESSION['flash_error'] = 'You cannot deactivate your own account.';
    } else {
        try {
            $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE user_id = ?")->execute([$id]);
            log_action($pdo, 'USER_TOGGLE', "Toggled active status of user #$id");
            $_SESSION['flash_success'] = 'Account status updated.';
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Database error.';
        }
    }
    header('Location: users.php' . $current_qs);
    exit;
}

$message = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// ---- filters & pagination ----
$q = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

if (!in_array($roleFilter, ['admin', 'cashier'], true)) $roleFilter = '';
if (!in_array($statusFilter, ['active', 'inactive'], true)) $statusFilter = '';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(full_name LIKE ? OR username LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($roleFilter !== '') {
    $where[] = 'role = ?';
    $params[] = $roleFilter;
}
if ($statusFilter === 'active') $where[] = 'is_active = 1';
if ($statusFilter === 'inactive') $where[] = 'is_active = 0';

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Get Total Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users" . $whereSql);
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$total_pages = max(1, ceil($totalUsers / $limit));

// Ensure page is within bounds
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// Fetch Paginated Data
$sql = "SELECT * FROM users" . $whereSql . " ORDER BY role, full_name LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$showing = count($users);

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

$page_title = 'Users';
include '../includes/header.php';
?>
<div class="page-head">
    <h1>Users</h1>
    <a class="btn" href="user_form.php">+ Add User</a>
</div>

<?php if ($message): ?>
    <div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="get" class="filter">
    <label>Search
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Name or username">
    </label>
    <label>Role
        <select name="role">
            <option value="">All</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="cashier" <?= $roleFilter === 'cashier' ? 'selected' : '' ?>>Cashier</option>
        </select>
    </label>
    <label>Status
        <select name="status">
            <option value="">All</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </label>

    <div class="filter-actions">
        <button type="submit">Filter</button>
        <a href="users.php" class="reset-btn">Reset</a>
    </div>
</form>

<table>
    <thead>
        <tr>
            <th style="width: 30%;">Name</th>
            <th style="width: 25%;">Username</th>
            <th style="width: 15%;">Role</th>
            <th style="width: 15%;">Status</th>
            <th style="width: 15%;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td><?= $u['is_active'] ? 'Active' : 'Inactive' ?></td>
                <td>
                    <div class="table-actions">
                        <a href="user_form.php?id=<?= (int) $u['user_id'] ?>">Edit</a>
                        <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                            <span class="divider">|</span>
                            <form method="post" action="users.php<?= htmlspecialchars($current_qs) ?>" class="inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                                <button type="submit" class="link-btn <?= $u['is_active'] ? 'btn-text-danger' : '' ?>" style="<?= !$u['is_active'] ? 'color: var(--green);' : '' ?>">
                                    <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color: var(--muted); font-size: 12px;">(you)</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?>
            <tr>
                <td colspan="5" style="text-align: center; padding: 32px; color: var(--muted);">No accounts match your filters.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<div class="pagination-wrap">
    <div class="pagination-info">
        Showing <strong><?= $showing ?></strong> of <strong><?= $totalUsers ?></strong> account(s)
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