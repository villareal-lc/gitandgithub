<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/functions.php';
require_role('admin');

$message = '';
$error = '';
$edit_id = 0;
$edit_name = '';
$page = max(1, (int)($_GET['page'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $page = max(1, (int)($_POST['page'] ?? 1)); // Preserve page on POST

    // ---------- Save (add or edit) ----------
    if ($action === 'save') {
        $edit_id = (int) ($_POST['category_id'] ?? 0);
        $edit_name = trim($_POST['category_name'] ?? '');

        if ($edit_name === '') {
            $error = 'Category name is required.';
        } elseif (mb_strlen($edit_name) > 100) {
            $error = 'Category name must be 100 characters or fewer.';
        } else {
            try {
                $pdo->beginTransaction();

                if ($edit_id > 0) {
                    $dup = $pdo->prepare(
                        "SELECT category_id FROM categories
                         WHERE category_name = ? AND category_id <> ?"
                    );
                    $dup->execute([$edit_name, $edit_id]);
                } else {
                    $dup = $pdo->prepare(
                        "SELECT category_id FROM categories WHERE category_name = ?"
                    );
                    $dup->execute([$edit_name]);
                }
                if ($dup->fetch()) {
                    throw new Exception('A category with that name already exists.');
                }

                if ($edit_id > 0) {
                    $pdo->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?")
                        ->execute([$edit_name, $edit_id]);
                    log_action($pdo, 'CATEGORY_EDIT', "Renamed category #$edit_id to $edit_name");
                    $pdo->commit();
                    $message = 'Category updated.';
                } else {
                    $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)")
                        ->execute([$edit_name]);
                    $newId = (int) $pdo->lastInsertId();
                    log_action($pdo, 'CATEGORY_ADD', "Added category #$newId ($edit_name)");
                    $pdo->commit();
                    $message = "Category '$edit_name' created.";
                }

                $edit_id = 0;
                $edit_name = '';
                // Redirect to prevent form resubmission and keep page
                $_SESSION['flash_success'] = $message;
                header("Location: categories.php?page=$page");
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = ($e->getCode() === '23000')
                    ? 'A category with that name already exists.'
                    : 'Database error. Nothing was changed.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e->getMessage();
            }
        }

        // ---------- Delete ----------
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['category_id'] ?? 0);
        if ($id <= 0) {
            $error = 'Invalid category.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "SELECT category_name FROM categories WHERE category_id = ? FOR UPDATE"
                );
                $stmt->execute([$id]);
                $name = $stmt->fetchColumn();

                if ($name === false) {
                    throw new Exception('Category not found.');
                }

                $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                $cntStmt->execute([$id]);
                $cnt = (int) $cntStmt->fetchColumn();
                if ($cnt > 0) {
                    throw new Exception(
                        "Cannot delete: $cnt product(s) still belong to this category. "
                            . "Move or remove them first."
                    );
                }

                $pdo->prepare("DELETE FROM categories WHERE category_id = ?")->execute([$id]);
                log_action($pdo, 'CATEGORY_DELETE', "Deleted category #$id ($name)");
                $pdo->commit();

                $_SESSION['flash_success'] = 'Category deleted.';
                header("Location: categories.php?page=$page");
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
}

$message = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// If we landed here to edit (?edit=N), preload that row into the form
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    if ($edit_id > 0) {
        $stmt = $pdo->prepare("SELECT category_name FROM categories WHERE category_id = ?");
        $stmt->execute([$edit_id]);
        $row = $stmt->fetch();
        if ($row) {
            $edit_name = $row['category_name'];
        } else {
            $edit_id = 0;
        }
    }
}

// Pagination logic
$limit = 10;
$offset = ($page - 1) * $limit;
$total_items = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$total_pages = max(1, ceil($total_items / $limit));
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare(
    "SELECT c.category_id, c.category_name, COUNT(p.product_id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.category_id
     GROUP BY c.category_id, c.category_name
     ORDER BY c.category_name
     LIMIT $limit OFFSET $offset"
);
$stmt->execute();
$categories = $stmt->fetchAll();

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

$page_title = 'Categories';
include '../includes/header.php';
?>
<h1>Categories</h1>

<?php if ($message): ?>
    <div class="success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<h2><?= $edit_id ? 'Edit category' : 'Add category' ?></h2>
<form method="post" class="filter" style="align-items: flex-end;">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="page" value="<?= $page ?>">
    <?php if ($edit_id): ?>
        <input type="hidden" name="category_id" value="<?= $edit_id ?>">
    <?php endif; ?>

    <label style="flex: 1; max-width: 400px;">Name
        <input type="text" name="category_name" maxlength="100" value="<?= htmlspecialchars($edit_name) ?>" required>
    </label>

    <div class="filter-actions">
        <button type="submit"><?= $edit_id ? 'Update' : 'Add' ?></button>
        <?php if ($edit_id): ?>
            <a href="categories.php?page=<?= $page ?>" class="reset-btn">Cancel</a>
        <?php endif; ?>
    </div>
</form>

<h2>All categories</h2>
<table>
    <thead>
        <tr>
            <th style="width: 60%;">Name</th>
            <th style="width: 20%;">Products</th>
            <th style="width: 20%;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['category_name']) ?></td>
                <td><?= (int) $c['product_count'] ?></td>
                <td>
                    <div class="table-actions">
                        <a href="categories.php?edit=<?= (int) $c['category_id'] ?>&page=<?= $page ?>">Edit</a>
                        <span class="divider">|</span>
                        <form method="post" class="inline"
                            onsubmit="return confirm(<?= htmlspecialchars(json_encode('Delete category "' . $c['category_name'] . '"?')) ?>);">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="page" value="<?= $page ?>">
                            <input type="hidden" name="category_id" value="<?= (int) $c['category_id'] ?>">
                            <button type="submit" class="link-btn btn-text-danger">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?>
            <tr>
                <td colspan="3" style="text-align: center; padding: 32px; color: var(--muted);">No categories found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Pagination -->
<div class="pagination-wrap">
    <div class="pagination-info">
        Showing <strong><?= count($categories) ?></strong> of <strong><?= $total_items ?></strong> categories
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