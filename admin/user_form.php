<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/functions.php';
require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$editing = $id > 0;

$user = [
    'full_name' => '',
    'username' => '',
    'role' => 'cashier',
    'is_active' => 1,
];

if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: users.php');
        exit;
    }
    $user = $row;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    // keep what the admin typed if validation fails
    $user['full_name'] = $name;
    $user['username'] = $username;
    if (in_array($role, ['admin', 'cashier'], true))
        $user['role'] = $role;

    if ($name === '' || $username === '' || !in_array($role, ['admin', 'cashier'], true)) {
        $error = 'Name, username, and role are required.';
    } elseif (!$editing && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($editing && $password !== '' && strlen($password) < 6) {
        $error = 'New password must be at least 6 characters (or leave blank to keep current).';
    } else {
        // Safety: don't allow demoting the last active admin
        if ($editing && $user['role'] !== 'admin' && $row['role'] === 'admin') {
            $adminCount = (int) $pdo->query(
                "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1"
            )->fetchColumn();
            if ($adminCount <= 1) {
                $error = 'Cannot demote the only active admin account. Promote another admin first.';
            }
        }

        // Friendly duplicate-username check
        if ($error === '') {
            if ($editing) {
                $dup = $pdo->prepare("SELECT 1 FROM users WHERE username = ? AND user_id <> ?");
                $dup->execute([$username, $id]);
            } else {
                $dup = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
                $dup->execute([$username]);
            }
            if ($dup->fetch())
                $error = 'That username is already taken.';
        }

        if ($error === '') {
            try {
                if ($editing) {
                    $pdo->prepare("UPDATE users SET full_name = ?, username = ?, role = ? WHERE user_id = ?")
                        ->execute([$name, $username, $role, $id]);
                    log_action($pdo, 'USER_EDIT', "Edited user #$id ($username)");

                    if ($password !== '') {
                        $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
                            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                        log_action($pdo, 'USER_PASSWORD_RESET', "Reset password of user #$id");
                    }

                    // Keep nav bar name fresh if editing yourself
                    if ($id === (int) $_SESSION['user_id']) {
                        $_SESSION['full_name'] = $name;
                    }

                    $_SESSION['flash_success'] = 'Account updated.';
                } else {
                    $pdo->prepare(
                        "INSERT INTO users (full_name, username, password_hash, role) VALUES (?, ?, ?, ?)"
                    )->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
                    log_action($pdo, 'USER_ADD', "Added $role account: $username");
                    $_SESSION['flash_success'] = "Account '$username' created.";
                }

                header('Location: users.php');
                exit;
            } catch (PDOException $e) {
                $error = ($e->getCode() === '23000')
                    ? 'That username is already taken.'
                    : 'Database error. Nothing was saved.';
            }
        }
    }
}

$page_title = $editing ? 'Edit User' : 'Add User';
include '../includes/header.php';
?>
<h1><?= htmlspecialchars($page_title) ?></h1>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="post" class="form-box">
    <label>Full name</label>
    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>

    <label>Username</label>
    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

    <label>Role</label>
    <select name="role" required>
        <option value="cashier" <?= $user['role'] === 'cashier' ? 'selected' : '' ?>>Cashier</option>
        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
    </select>

    <label>
        <?= $editing ? 'New password' : 'Password' ?>
        <?php if ($editing): ?><small>(leave blank to keep current)</small><?php endif; ?>
    </label>
    <input type="password" name="password" <?= $editing ? '' : 'required' ?>
        placeholder="<?= $editing ? 'Leave blank to keep current' : '' ?>">
    <small>Minimum 6 characters.</small>

    <?php if ($editing): ?>
        <label>Status</label>
        <input type="text" value="<?= $user['is_active'] ? 'Active' : 'Inactive' ?>" disabled>
        <small>Use Activate / Deactivate on the Users list to change status.</small>
    <?php endif; ?>

    <button type="submit"><?= $editing ? 'Save Changes' : 'Create Account' ?></button>
    <a href="users.php">Cancel</a>
</form>
<?php include '../includes/footer.php'; ?>