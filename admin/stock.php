<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/functions.php';
require_role('admin');

$message = '';
$error = '';
$page = max(1, (int)($_GET['page'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page = max(1, (int)($_POST['page'] ?? 1)); // preserve page on POST
    $product_id = (int) ($_POST['product_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $qty = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $reason = trim($_POST['reason'] ?? '');

    if ($product_id <= 0 || !in_array($type, ['add', 'remove'], true) || $qty === false || $reason === '') {
        $error = 'Choose a product, a type, a quantity of 1 or more, and give a reason.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT product_name, stock_qty FROM products WHERE product_id = ? FOR UPDATE");
            $stmt->execute([$product_id]);
            $p = $stmt->fetch();
            if (!$p) throw new Exception('Product not found.');

            $new = ($type === 'add') ? $p['stock_qty'] + $qty : $p['stock_qty'] - $qty;
            if ($new < 0) {
                throw new Exception('Cannot remove more than the current stock (' . $p['stock_qty'] . ').');
            }

            $pdo->prepare("UPDATE products SET stock_qty = ? WHERE product_id = ?")
                ->execute([$new, $product_id]);

            log_action(
                $pdo,
                'STOCK_ADJUST',
                $p['product_name'] . ': ' . $p['stock_qty'] . ' -> ' . $new . ' (' . mb_substr($reason, 0, 100) . ')'
            );

            $pdo->commit();
            $_SESSION['flash_success'] = $p['product_name'] . ' stock is now ' . $new . '.';
            header("Location: stock.php?page=$page");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Database error. Nothing was changed.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$message = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$products = $pdo->query(
    "SELECT product_id, product_name, stock_qty, low_stock_threshold, barcode
     FROM products WHERE is_active = 1 ORDER BY product_name"
)->fetchAll();

// Pagination logic for Recent Adjustments
$limit = 10;
$offset = ($page - 1) * $limit;
$total_items = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'STOCK_ADJUST'")->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare(
    "SELECT l.details, l.logged_at, u.full_name
     FROM audit_logs l JOIN users u ON l.user_id = u.user_id
     WHERE l.action = 'STOCK_ADJUST'
     ORDER BY l.logged_at DESC, l.log_id DESC LIMIT $limit OFFSET $offset"
);
$stmt->execute();
$recent = $stmt->fetchAll();

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

$page_title = 'Stock Adjustment';
include '../includes/header.php';
?>
<h1>Stock Adjustment</h1>

<?php if ($message): ?>
    <div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!$products): ?>
    <div class="error">No active products to adjust. Add products first.</div>
<?php else: ?>

    <form method="post" class="form-box">
        <input type="hidden" name="page" value="<?= $page ?>">
        <label>Search product <small>(filter by name or code)</small></label>
        <input type="text" id="productSearch" autocomplete="off" placeholder="e.g. Yonex, RAC0007, shuttlecock…">
        <small id="matchInfo"></small>

        <label>Product to adjust</label>
        <select name="product_id" id="productSelect" required>
            <option value="">-- choose --</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= (int) $p['product_id'] ?>"
                    data-search="<?= htmlspecialchars(strtolower($p['product_name'] . ' ' . ($p['barcode'] ?? ''))) ?>"
                    data-stock="<?= (int) $p['stock_qty'] ?>">
                    <?= htmlspecialchars($p['product_name']) ?>
                    <?= $p['barcode'] ? '(' . htmlspecialchars($p['barcode']) . ')' : '' ?>
                    — stock: <?= (int) $p['stock_qty'] ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small id="currentStock"></small>

        <label>Type</label>
        <select name="type" required>
            <option value="add">Add stock (restock)</option>
            <option value="remove">Remove stock (damaged, lost, correction)</option>
        </select>

        <label>Quantity</label>
        <input type="number" name="quantity" min="1" required>

        <label>Reason</label>
        <input type="text" name="reason" maxlength="100" placeholder="e.g. New delivery, damaged, recount" required>

        <button type="submit">Apply</button>
    </form>

    <script>
        (function() {
            var search = document.getElementById('productSearch');
            var select = document.getElementById('productSelect');
            var info = document.getElementById('matchInfo');
            var stockLbl = document.getElementById('currentStock');
            var options = Array.prototype.slice.call(select.options);

            function filter() {
                var term = search.value.toLowerCase().trim();
                var matched = [];

                options.forEach(function(opt, idx) {
                    if (idx === 0) return; // keep placeholder
                    var hit = term === '' || (opt.dataset.search || '').indexOf(term) !== -1;
                    opt.hidden = !hit;
                    opt.style.display = hit ? '' : 'none';
                    if (hit) matched.push(opt);
                });

                var sel = select.options[select.selectedIndex];
                if (sel && sel.hidden) select.selectedIndex = 0;

                info.textContent = term === '' ?
                    (options.length - 1) + ' active products available' :
                    (matched.length === 0 ?
                        'No matching products.' :
                        matched.length + ' matching product' + (matched.length === 1 ? '' : 's'));

                updateStock();
            }

            function updateStock() {
                var opt = select.options[select.selectedIndex];
                if (opt && opt.value) {
                    stockLbl.textContent = 'Current stock: ' + (opt.dataset.stock || '?');
                } else {
                    stockLbl.textContent = '';
                }
            }

            search.addEventListener('input', filter);
            search.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                var matched = options.filter(function(opt, i) {
                    return i > 0 && !opt.hidden;
                });
                if (matched.length === 1) {
                    select.value = matched[0].value;
                    updateStock();
                }
            });
            select.addEventListener('change', updateStock);
            filter(); // initial state
        })();
    </script>
<?php endif; ?>

<h2>Recent adjustments</h2>
<table>
    <thead>
        <tr>
            <th style="width: 20%;">When</th>
            <th style="width: 20%;">By</th>
            <th style="width: 60%;">Details</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['logged_at']) ?></td>
                <td><?= htmlspecialchars($r['full_name']) ?></td>
                <td><?= htmlspecialchars($r['details']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?>
            <tr>
                <td colspan="3" style="text-align: center; padding: 32px; color: var(--muted);">No adjustments yet.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<div class="pagination-wrap">
    <div class="pagination-info">
        Showing <strong><?= count($recent) ?></strong> of <strong><?= $total_items ?></strong> adjustments
    </div>
    <?php if ($total_pages > 1): ?>
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
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>