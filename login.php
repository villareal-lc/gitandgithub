<?php
require 'config/db.php';
require 'includes/auth.php';
require 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
  header('Location: ' . home_for($_SESSION['role']));
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
  $stmt->execute([$username]);
  $user = $stmt->fetch();

  if ($user && password_verify($password, $user['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
    log_action($pdo, 'LOGIN', 'User logged in: ' . $user['username']);
    header('Location: ' . home_for($user['role']));
    exit;
  }
  $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in — ShuttleCore</title>
  <link rel="stylesheet" href="assets/style.css">
</head>

<body>
  <div class="login-page">

    <section class="login-form-side">
      <div class="login-brand-row">
        <span class="brand-mark">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
            <path d="M12 2c-3 0-7 4-8 10l8 2 8-2c-1-6-5-10-8-10Z" opacity=".9" />
            <path d="M8 14l4 9 4-9Z" />
          </svg>
        </span>
        <span class="brand-text">
          <span class="brand-name" style="color:var(--burgundy);">SHUTTLECORE</span>
          <span class="brand-sub" style="color:var(--muted);">Inventory &amp; POS</span>
        </span>
      </div>

      <h1>Welcome back</h1>
      <p class="sub">Sign in to continue to your workspace.</p>

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <div>
          <label for="username">Username</label>
          <input id="username" type="text" name="username" required autofocus>
        </div>
        <div>
          <label for="password">Password</label>
          <input id="password" type="password" name="password" required>
        </div>
        <button type="submit">Sign in</button>
      </form>
    </section>

    <section class="login-visual">
      <div class="visual-mark">
        <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor" aria-hidden="true">
          <path d="M12 2c-3 0-7 4-8 10l8 2 8-2c-1-6-5-10-8-10Z" opacity=".9" />
          <path d="M8 14l4 9 4-9Z" />
        </svg>
      </div>
      <h2>ShuttleCore</h2>
      <p>Inventory &amp; Point-of-Sale for your badminton store — fast, accurate, and always in sync.</p>
    </section>

  </div>
</body>

</html>