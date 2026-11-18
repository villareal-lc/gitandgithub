<?php
$role       = $_SESSION['role'] ?? '';
$page_title = $page_title ?? 'ShuttleCore';
$current    = basename($_SERVER['PHP_SELF']);

if (!function_exists('sc_icon')) {
  function sc_icon(string $name): string
  {
    $icons = [
      'shuttlecock' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2c-3 0-7 4-8 10l8 2 8-2c-1-6-5-10-8-10Z" opacity=".9"/><path d="M8 14l4 9 4-9Z"/></svg>',
      'dashboard'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
      'box'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3.3 7.5L12 12l8.7-4.5"/><path d="M12 22V12"/></svg>',
      'folder'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
      'sliders'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>',
      'tag'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4L12 22 2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.4" fill="currentColor"/></svg>',
      'receipt'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2l-3 2-3-2-3 2-3-2-3 2z"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="13" y2="14"/></svg>',
      'users'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
      'history'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
      'cart'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M3 3h2l2.4 12.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6"/></svg>',
      'logout'      => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
      'menu'        => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
    ];
    return $icons[$name] ?? '';
  }
}
if (!function_exists('sc_nav_item')) {
  function sc_nav_item(string $label, string $href, string $icon, string $current): string
  {
    $isActive = basename($href) === $current;
    return '<a href="' . htmlspecialchars($href) . '" class="nav-item' . ($isActive ? ' active' : '') . '">'
      . '<span class="nav-icon">' . sc_icon($icon) . '</span>'
      . '<span class="nav-label">' . htmlspecialchars($label) . '</span>'
      . '</a>';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title) ?> — ShuttleCore</title>
  <link rel="stylesheet" href="/shuttlecore/assets/style.css">
</head>

<body>
  <div class="app-shell">

    <aside class="sidebar" id="sidebar">
      <a class="sidebar-brand" href="<?= htmlspecialchars(home_for($role)) ?>" aria-label="ShuttleCore home">
        <span class="brand-mark"><?= sc_icon('shuttlecock') ?></span>
        <span class="brand-text">
          <span class="brand-name">SHUTTLECORE</span>
          <span class="brand-sub">Inventory &amp; POS</span>
        </span>
      </a>

      <nav class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
          <?= sc_nav_item('Dashboard', '/shuttlecore/admin/dashboard.php', 'dashboard', $current) ?>

          <div class="nav-group-title">Inventory</div>
          <?= sc_nav_item('Products',         '/shuttlecore/admin/products.php',  'box',     $current) ?>
          <?= sc_nav_item('Categories',       '/shuttlecore/admin/categories.php', 'folder',  $current) ?>
          <?= sc_nav_item('Stock Adjustments', '/shuttlecore/admin/stock.php',     'sliders', $current) ?>
          <?= sc_nav_item('Print Labels',     '/shuttlecore/admin/labels.php',    'tag',     $current) ?>

          <div class="nav-group-title">Operations</div>
          <?= sc_nav_item('Sales',     '/shuttlecore/admin/sales.php', 'receipt', $current) ?>
          <?= sc_nav_item('Users',     '/shuttlecore/admin/users.php', 'users',   $current) ?>
          <?= sc_nav_item('Audit Log', '/shuttlecore/admin/logs.php',  'history', $current) ?>
        <?php elseif ($role === 'cashier'): ?>
          <?= sc_nav_item('POS Checkout', '/shuttlecore/cashier/pos.php', 'cart', $current) ?>
        <?php endif; ?>
      </nav>

      <div class="sidebar-foot">
        <div class="user-chip">
          <div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? '?', 0, 1))) ?></div>
          <div class="user-meta">
            <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></div>
            <div class="user-role"><?= htmlspecialchars(ucfirst($role)) ?></div>
          </div>
        </div>
        <a href="/shuttlecore/logout.php" class="logout-link"><?= sc_icon('logout') ?> Sign out</a>
      </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="app-main">
      <header class="topbar">
        <button class="menu-btn" id="menuBtn" aria-label="Toggle menu"><?= sc_icon('menu') ?></button>
        <div class="topbar-title"><?= htmlspecialchars($page_title) ?></div>
      </header>
      <main class="container">