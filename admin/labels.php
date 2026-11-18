<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/functions.php';
require '../includes/code39.php';
require_role('admin');

// 1. Capture Filters
$catFilter = (int) ($_GET['category'] ?? 0);
$q         = trim($_GET['q'] ?? '');
$addedFrom = $_GET['added_from'] ?? '';
$sortBy    = $_GET['sort_by'] ?? 'name'; // default sort
$printAll  = isset($_GET['print_all']) && $_GET['print_all'] == '1';
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = 10;
$offset    = ($page - 1) * $limit;

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $addedFrom)) $addedFrom = '';

// 2. Build Dynamic Query
$where = ["p.is_active = 1"];
$params = [];

if ($catFilter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $catFilter;
}
if ($q !== '') {
    $where[] = "(p.product_name LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($addedFrom !== '') {
    $where[] = "DATE(p.created_at) >= ?";
    $params[] = $addedFrom;
}

$whereSql = " WHERE " . implode(" AND ", $where);

// 3. Get Total Count for Pagination
$countSql = "SELECT COUNT(*) FROM products p" . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_items = $countStmt->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));

// Ensure page is within bounds
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// 4. Fetch Data
$orderSql = "ORDER BY c.category_name, p.product_name ASC";
if ($sortBy === 'newest') {
    $orderSql = "ORDER BY p.product_id DESC";
}

$sql = "SELECT p.product_id, p.product_name, p.barcode, p.price, c.category_name
        FROM products p JOIN categories c ON p.category_id = c.category_id"
    . $whereSql
    . " " . $orderSql;

if (!$printAll) {
    $sql .= " LIMIT $limit OFFSET $offset";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();

// Sliding Window Math
$max_pages_to_show = 5;
$start = max(1, $page - floor($max_pages_to_show / 2));
$end = min($total_pages, $start + $max_pages_to_show - 1);
if ($end - $start + 1 < $max_pages_to_show) {
    $start = max(1, $end - $max_pages_to_show + 1);
}

function buildPageUrl($p)
{
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

$page_title = $printAll ? 'Printing Labels' : 'Print Labels';
include '../includes/header.php';
?>

<div class="page-head no-print">
    <h1>Print Labels</h1>
</div>

<!-- Filter Bar -->
<form method="get" class="filter no-print" style="grid-template-columns: 1.5fr 1fr 1fr 1fr auto;">
    <label>Search
        <input type="text" name="q" placeholder="Name or barcode…" value="<?= htmlspecialchars($q) ?>">
    </label>

    <label>Category
        <select name="category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['category_id'] ?>" <?= $catFilter === (int)$c['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['category_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Added From (Optional)
        <input type="date" name="added_from" value="<?= htmlspecialchars($addedFrom) ?>">
    </label>

    <label>Sort By
        <select name="sort_by">
            <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Category / Name</option>
            <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First (ID)</option>
        </select>
    </label>

    <div class="filter-actions">
        <button type="submit">Filter</button>
        <a href="labels.php" class="reset-btn">Reset</a>
    </div>
</form>

<!-- Action Buttons -->
<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; background: var(--cream-soft); padding: 16px; border-radius: var(--radius-lg); border: 1px solid var(--border);">
    <div style="font-size: 13px; color: var(--muted);">
        <?php if ($printAll): ?>
            <strong style="color: var(--burgundy);">Print Mode Active:</strong> Showing all <?= count($products) ?> matching labels.
        <?php else: ?>
            Showing <strong><?= count($products) ?></strong> of <strong><?= $total_items ?></strong> matching labels.
        <?php endif; ?>
    </div>

    <div style="display: flex; gap: 8px;">
        <?php if ($printAll): ?>
            <a href="?category=<?= $catFilter ?>&q=<?= urlencode($q) ?>&added_from=<?= urlencode($addedFrom) ?>&sort_by=<?= $sortBy ?>" class="btn" style="background: var(--cream); color: var(--charcoal); border: 1px solid var(--border);">
                Exit Print Mode
            </a>
            <button type="button" onclick="window.print()" class="btn" style="background: var(--green);">
                <i class="fa-solid fa-print"></i> Print Labels Now
            </button>
        <?php else: ?>
            <button type="button" onclick="window.print()" class="btn" style="background: var(--charcoal);">
                <i class="fa-solid fa-print"></i> Print Current Page
            </button>
            <?php if ($total_items > 0): ?>
                <a href="?category=<?= $catFilter ?>&q=<?= urlencode($q) ?>&added_from=<?= urlencode($addedFrom) ?>&sort_by=<?= $sortBy ?>&print_all=1" class="btn" style="background: var(--green);">
                    <i class="fa-solid fa-print"></i> Print All (<?= $total_items ?>)
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<p class="no-print" style="color:var(--muted);font-size:13px;">
    Each label includes a scannable <strong>Code 39</strong> SVG barcode. Cut along the dashed lines and attach to shelf or product.
</p>

<style>
    .label-sheet {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 16px;
    }

    .label {
        border: 1px dashed #B9AEA8;
        background: var(--cream-soft);
        border-radius: 8px;
        padding: 10px 8px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        min-height: 150px;
        justify-content: space-between;
        break-inside: avoid;
    }

    .label-shop {
        font-family: var(--font-head);
        font-size: 10px;
        font-weight: 600;
        letter-spacing: .14em;
        color: var(--burgundy);
        text-transform: uppercase;
    }

    .label-name {
        font-family: var(--font-body);
        font-size: 11px;
        font-weight: 600;
        color: var(--charcoal);
        line-height: 1.2;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .label-barcode {
        width: 100%;
        display: flex;
        justify-content: center;
    }

    .label-barcode svg {
        max-width: 100%;
        height: auto;
        display: block;
    }

    .label-price {
        font-family: var(--font-body);
        font-weight: 700;
        font-size: 13px;
        color: var(--burgundy);
    }

    @media (max-width: 700px) {
        .label-sheet {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media print {

        .sidebar,
        .topbar,
        .no-print,
        .sidebar-backdrop {
            display: none !important;
        }

        .app-main {
            margin-left: 0 !important;
        }

        .container {
            padding: 0 !important;
            max-width: none !important;
        }

        .label-sheet {
            gap: 4mm;
        }

        .label {
            border: 1px solid #000;
            background: #fff;
            break-inside: avoid;
        }

        .label-shop {
            color: #000;
        }

        .label-price {
            color: #000;
        }
    }
</style>

<!-- Labels Grid -->
<div class="label-sheet">
    <?php foreach ($products as $p): ?>
        <div class="label">
            <div class="label-shop">SHUTTLECORE</div>
            <div class="label-name"><?= htmlspecialchars($p['product_name']) ?></div>
            <div class="label-barcode">
                <?= code39_svg($p['barcode'], [
                    'module_width' => 1.4,
                    'height'       => 42,
                    'quiet'        => 6,
                    'show_text'    => true,
                    'text'         => $p['barcode'],
                    'text_size'    => 10,
                ]) ?>
            </div>
            <div class="label-price">₱<?= number_format($p['price'], 2) ?></div>
        </div>
    <?php endforeach; ?>
    <?php if (!$products): ?>
        <p>No active products match your criteria.</p>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if (!$printAll && $total_pages > 1): ?>
    <div class="pagination-wrap no-print">
        <div class="pagination-info">
            Showing <strong><?= count($products) ?></strong> of <strong><?= $total_items ?></strong> labels
        </div>
        <div class="pagination">
            <a href="<?= $page > 1 ? buildPageUrl($page - 1) : '#' ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </a>
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= buildPageUrl($i) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= $page < $total_pages ? buildPageUrl($page + 1) : '#' ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Auto-trigger print dialog if in Print All mode -->
<?php if ($printAll): ?>
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>