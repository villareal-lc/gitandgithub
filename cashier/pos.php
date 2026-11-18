<?php
require '../config/db.php';
require '../includes/auth.php';
require_role('cashier');

if (!isset($_SESSION['cart'])) {
  $_SESSION['cart'] = [];
}

// ---- handle cart actions, then redirect back ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $pid = (int) ($_POST['product_id'] ?? 0);

  if ($action === 'add') {
    $qty = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $stmt = $pdo->prepare("SELECT product_name, stock_qty FROM products WHERE product_id = ? AND is_active = 1");
    $stmt->execute([$pid]);
    $p = $stmt->fetch();

    if (!$p || $qty === false) {
      $_SESSION['flash_error'] = 'Invalid product or quantity.';
    } else {
      $newQty = ($_SESSION['cart'][$pid] ?? 0) + $qty;
      if ($newQty > $p['stock_qty']) {
        $_SESSION['flash_error'] = 'Only ' . $p['stock_qty'] . ' of ' . $p['product_name'] . ' in stock.';
      } else {
        $_SESSION['cart'][$pid] = $newQty;
        $_SESSION['flash_success'] = 'Added: ' . $p['product_name'];
      }
    }
  } elseif ($action === 'decrement') {
    if (isset($_SESSION['cart'][$pid])) {
      $_SESSION['cart'][$pid]--;
      if ($_SESSION['cart'][$pid] <= 0)
        unset($_SESSION['cart'][$pid]);
    }
  } elseif ($action === 'scan') {
    $code = strtoupper(trim($_POST['barcode'] ?? ''));
    if ($code !== '') {
      $stmt = $pdo->prepare(
        "SELECT product_id, product_name, stock_qty
                 FROM products WHERE barcode = ? AND is_active = 1"
      );
      $stmt->execute([$code]);
      $p = $stmt->fetch();

      if (!$p) {
        $_SESSION['flash_error'] = 'No active product has barcode ' . $code . '.';
      } else {
        $newQty = ($_SESSION['cart'][$p['product_id']] ?? 0) + 1;
        if ($newQty > $p['stock_qty']) {
          $_SESSION['flash_error'] = 'Only ' . $p['stock_qty'] . ' of ' . $p['product_name'] . ' in stock.';
        } else {
          $_SESSION['cart'][$p['product_id']] = $newQty;
          $_SESSION['flash_success'] = 'Added: ' . $p['product_name'];
        }
      }
    }
  } elseif ($action === 'remove') {
    unset($_SESSION['cart'][$pid]);
  } elseif ($action === 'clear') {
    $_SESSION['cart'] = [];
  }
  header('Location: pos.php');
  exit;
}

$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// ---- load ALL active products (client-side filter below) ----
$products = $pdo->query(
  "SELECT p.product_id, p.product_name, p.barcode, p.price, p.stock_qty, p.category_id, p.low_stock_threshold, c.category_name
     FROM products p JOIN categories c ON p.category_id = c.category_id
     WHERE p.is_active = 1
     ORDER BY c.category_name, p.product_name"
)->fetchAll();

$categories = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();

// ---- cart details ----
$items = [];
$total = 0;
if ($_SESSION['cart']) {
  $ids = array_keys($_SESSION['cart']);
  $in = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("SELECT product_id, product_name, price, stock_qty FROM products WHERE product_id IN ($in)");
  $stmt->execute($ids);
  foreach ($stmt->fetchAll() as $r) {
    $r['qty'] = $_SESSION['cart'][$r['product_id']];
    $r['subtotal'] = $r['price'] * $r['qty'];
    $total += $r['subtotal'];
    $items[] = $r;
  }
}
$cartCount = array_sum($_SESSION['cart']);
$page_title = 'Checkout';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> — ShuttleCore</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'system-ui', 'sans-serif'],
            head: ['Poppins', 'system-ui', 'sans-serif'],
          },
          colors: {
            brand: {
              50: '#fbf3f1',
              100: '#f3e1dd',
              200: '#e5c8c2',
              500: '#7a2a24',
              600: '#5F1814',
              700: '#42100D',
            },
            cream: {
              50: '#F8F3F0',
              100: '#FFFDFB',
              200: '#E7E1DE',
            },
            ink: {
              DEFAULT: '#292525',
              muted: '#77716F',
            },
          },
        },
      },
    };
  </script>
  <style>
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    ::-webkit-scrollbar-track {
      background: #F8F3F0;
    }

    ::-webkit-scrollbar-thumb {
      background: #E7E1DE;
      border-radius: 9999px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #d3c9c4;
    }

    .no-scrollbar::-webkit-scrollbar {
      display: none;
    }

    .no-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }

    kbd {
      font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
      font-size: 10px;
      line-height: 1;
      padding: 2px 5px;
    }

    /* Custom Pagination Styles for POS */
    .page-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 32px;
      height: 32px;
      padding: 0 8px;
      border: 1px solid #E7E1DE;
      background: #FFFDFB;
      color: #292525;
      font-size: 12px;
      font-weight: 500;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.15s;
    }

    .page-btn:hover:not(.disabled) {
      background: #F8F3F0;
      border-color: #77716F;
    }

    .page-btn.active {
      background: #5F1814;
      color: #fff;
      border-color: #5F1814;
    }

    .page-btn.disabled {
      opacity: 0.4;
      cursor: not-allowed;
      background: #F8F3F0;
    }
  </style>
</head>

<body class="h-full font-sans antialiased text-ink flex flex-col min-h-screen bg-cream-50">

  <!-- ===================== Top bar ===================== -->
  <header class="bg-brand-700 text-white shadow-md sticky top-0 z-30">
    <div class="px-4 py-2.5 flex items-center justify-between max-w-[1700px] mx-auto gap-4">
      <div class="flex items-center gap-3 min-w-0">
        <div class="bg-brand-600 text-white p-2 rounded-lg flex items-center justify-center ring-1 ring-white/10 shrink-0">
          <i class="fa-solid fa-shuttlecock text-xl"></i>
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-head font-bold text-lg tracking-tight text-white">ShuttleCore</span>
            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 font-semibold border border-emerald-500/25">
              <i class="fa-solid fa-circle text-[7px] mr-1 text-emerald-400"></i> Checkout Terminal
            </span>
          </div>
          <p class="text-[11px] text-white/60 hidden sm:block">POS &amp; Inventory — Cashier</p>
        </div>
      </div>

      <div class="hidden lg:flex items-center gap-3 text-[11px] text-white/70 bg-white/5 px-3 py-1.5 rounded-lg border border-white/10">
        <span class="flex items-center gap-1.5"><kbd class="bg-white/10 text-white/90 rounded">F2</kbd> Scan</span>
        <span class="text-white/25">|</span>
        <span class="flex items-center gap-1.5"><kbd class="bg-white/10 text-white/90 rounded">F4</kbd> Cash</span>
      </div>

      <div class="flex items-center gap-3 shrink-0">
        <div class="text-right hidden md:block">
          <p class="text-xs font-semibold text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></p>
          <p class="text-[10px] uppercase tracking-wider text-white/50"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></p>
        </div>
        <a href="/shuttlecore/logout.php" title="Logout"
          class="h-8 w-8 rounded-full bg-brand-600 hover:bg-brand-500 text-white flex items-center justify-center text-xs ring-2 ring-white/20 transition">
          <i class="fa-solid fa-right-from-bracket"></i>
        </a>
      </div>
    </div>
  </header>

  <!-- ===================== Main ===================== -->
  <main class="flex-1 p-3 md:p-5 max-w-[1700px] mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-5">

    <!-- -------- LEFT: catalog -------- -->
    <section class="lg:col-span-7 xl:col-span-8 flex flex-col space-y-4">

      <!-- Manual Barcode Entry -->
      <div class="bg-cream-100 p-4 rounded-xl border border-cream-200 shadow-sm">
        <div class="flex items-baseline justify-between mb-2 gap-2">
          <label for="barcodeInput"
            class="font-head text-[11px] font-semibold uppercase tracking-wider text-brand-600 flex items-center gap-1.5">
            <i class="fa-solid fa-barcode"></i> Manual Barcode Entry
          </label>
          <span class="hidden sm:inline text-[11px] text-ink-muted">
            or scan with a reader · <kbd class="bg-cream-200 text-brand-700 rounded">F2</kbd>
          </span>
        </div>
        <form method="post" class="flex gap-2">
          <input type="hidden" name="action" value="scan">
          <input type="text" name="barcode" id="barcodeInput" autofocus autocomplete="off"
            class="flex-1 px-3 py-2.5 bg-white border border-cream-200 rounded-lg text-ink placeholder:text-ink-muted/60 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-sm font-medium transition"
            placeholder="Enter barcode, e.g. RAC0003">
          <button type="submit"
            class="bg-brand-600 hover:bg-brand-700 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors flex items-center gap-1.5 text-sm shadow-sm active:scale-95">
            <i class="fa-solid fa-plus text-xs"></i><span>Add</span>
          </button>
        </form>
        <p class="mt-2 text-[11px] text-ink-muted">Accepts scanner input and manual typing.</p>
      </div>

      <!-- Product catalog -->
      <div class="bg-cream-100 rounded-xl border border-cream-200 shadow-sm flex-1 flex flex-col overflow-hidden">
        <div class="p-4 border-b border-cream-200 space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h2 class="font-head text-lg font-bold text-ink flex items-center">
                <i class="fa-solid fa-boxes-stacked text-brand-600 mr-2"></i> Product Inventory
              </h2>
              <p class="text-xs text-ink-muted">Select items to add to current checkout</p>
            </div>
            <div class="relative w-full sm:w-64">
              <input type="text" id="catalogSearch"
                class="w-full pl-9 pr-3 py-2 text-xs bg-cream-50 border border-cream-200 rounded-lg text-ink placeholder:text-ink-muted/60 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:border-brand-600 focus:bg-white transition"
                placeholder="Filter by name or code…">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-muted text-xs">
                <i class="fa-solid fa-magnifying-glass"></i>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-1.5 overflow-x-auto pb-1 pt-1 no-scrollbar text-xs" id="categoryPills">
            <button data-category="0"
              class="cat-pill px-3 py-1.5 rounded-lg font-medium whitespace-nowrap transition bg-brand-600 text-white shadow-sm">
              All Products
            </button>
            <?php foreach ($categories as $c): ?>
              <button data-category="<?= (int) $c['category_id'] ?>"
                class="cat-pill px-3 py-1.5 rounded-lg font-medium whitespace-nowrap transition bg-cream-50 text-ink-muted border border-cream-200 hover:bg-cream-100 hover:text-brand-700">
                <?= htmlspecialchars($c['category_name']) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="overflow-x-auto flex-1 min-h-[350px]">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-cream-50 text-[11px] font-semibold text-ink-muted uppercase border-b border-cream-200 tracking-wider">
                <th class="py-3 px-4">Code</th>
                <th class="py-3 px-4">Product Name</th>
                <th class="py-3 px-4">Category</th>
                <th class="py-3 px-4 text-right">Price</th>
                <th class="py-3 px-4 text-center">Status</th>
                <th class="py-3 px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody id="productTableBody" class="divide-y divide-cream-200 text-xs">
              <?php foreach ($products as $p):
                $isOut = (int) $p['stock_qty'] <= 0;
                $isLow = !$isOut && (int) $p['stock_qty'] <= (int) $p['low_stock_threshold'];
              ?>
                <tr class="hover:bg-cream-50 transition-colors product-row <?= $isOut ? 'opacity-70' : '' ?>"
                  data-name="<?= htmlspecialchars(strtolower($p['product_name'])) ?>"
                  data-code="<?= htmlspecialchars(strtolower($p['barcode'] ?? '')) ?>"
                  data-category="<?= (int) $p['category_id'] ?>">
                  <td class="py-3 px-4 font-mono font-semibold text-ink-muted">
                    <?= htmlspecialchars($p['barcode'] ?: '—') ?>
                  </td>
                  <td class="py-3 px-4 font-medium text-ink"><?= htmlspecialchars($p['product_name']) ?></td>
                  <td class="py-3 px-4 text-ink-muted"><?= htmlspecialchars($p['category_name']) ?></td>
                  <td class="py-3 px-4 text-right font-semibold text-ink whitespace-nowrap">₱<?= number_format($p['price'], 2) ?></td>
                  <td class="py-3 px-4 text-center">
                    <?php if ($isOut): ?>
                      <span class="inline-flex items-center gap-1.5 bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded-full font-semibold text-[10px] whitespace-nowrap">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span> Out of Stock
                      </span>
                    <?php elseif ($isLow): ?>
                      <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full font-semibold text-[10px] whitespace-nowrap">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span> Low: <?= (int) $p['stock_qty'] ?>
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full font-semibold text-[10px] whitespace-nowrap">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> <?= (int) $p['stock_qty'] ?> in stock
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="py-3 px-4 text-right">
                    <?php if (!$isOut): ?>
                      <form method="post" class="inline">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit"
                          class="bg-brand-600 hover:bg-brand-700 text-white active:scale-95 px-3 py-1.5 rounded-lg font-medium text-xs transition inline-flex items-center gap-1">
                          <i class="fa-solid fa-plus text-[10px]"></i><span>Add</span>
                        </button>
                      </form>
                    <?php else: ?>
                      <button disabled
                        class="bg-cream-50 text-ink-muted/50 border border-cream-200 cursor-not-allowed px-3 py-1.5 rounded-lg font-medium text-xs inline-flex items-center gap-1">
                        <i class="fa-solid fa-plus text-[10px]"></i><span>Add</span>
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$products): ?>
                <tr>
                  <td colspan="6" class="py-10 text-center text-ink-muted">
                    <i class="fa-solid fa-box-open text-2xl mb-2 block opacity-50"></i> No products found.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination Container -->
        <div class="p-3 border-t border-cream-200 bg-cream-50 flex justify-between items-center">
          <div id="paginationInfo" class="text-xs text-ink-muted"></div>
          <div id="paginationControls" class="flex gap-1"></div>
        </div>
      </div>
    </section>

    <!-- -------- RIGHT: cart -------- -->
    <section class="lg:col-span-5 xl:col-span-4 flex flex-col">
      <div class="bg-cream-100 rounded-xl shadow-md border border-cream-200 flex flex-col sticky top-20 overflow-hidden max-h-[calc(100vh-6rem)]">

        <!-- Cart header -->
        <div class="p-4 bg-brand-700 text-white flex items-center justify-between border-b border-brand-700 shrink-0">
          <div class="flex items-center gap-2">
            <i class="fa-solid fa-cart-shopping text-brand-200"></i>
            <h2 class="font-head font-bold text-base tracking-wide">Current Order</h2>
            <span class="bg-white/15 text-white text-[11px] font-bold px-2 py-0.5 rounded-full border border-white/15">
              <?= (int) $cartCount ?> item<?= $cartCount === 1 ? '' : 's' ?>
            </span>
          </div>
          <?php if ($cartCount > 0): ?>
            <form method="post" class="inline">
              <input type="hidden" name="action" value="clear">
              <button type="submit"
                class="text-[11px] text-white/60 hover:text-rose-300 transition inline-flex items-center gap-1">
                <i class="fa-solid fa-rotate-left text-[10px]"></i><span>Clear</span>
              </button>
            </form>
          <?php endif; ?>
        </div>

        <!-- Cart items -->
        <div class="flex-1 overflow-y-auto p-3 space-y-2 bg-cream-50 min-h-[160px]">
          <?php if (!$items): ?>
            <div class="flex flex-col items-center justify-center h-full text-ink-muted py-10 gap-2">
              <i class="fa-solid fa-basket-shopping text-3xl opacity-40"></i>
              <p class="text-xs font-medium text-ink">Cart is empty</p>
              <p class="text-[11px] text-center opacity-75">Scan a barcode or click "+ Add" on an item</p>
            </div>
          <?php else: ?>
            <?php foreach ($items as $i): ?>
              <div class="bg-cream-100 p-2.5 rounded-lg border border-cream-200 shadow-sm flex items-center justify-between gap-2">
                <div class="flex-1 min-w-0">
                  <h4 class="font-semibold text-xs text-ink truncate"><?= htmlspecialchars($i['product_name']) ?></h4>
                  <p class="text-[11px] text-ink-muted">₱<?= number_format($i['price'], 2) ?> each</p>
                </div>

                <form method="post" class="flex items-center gap-1 bg-cream-50 rounded-md p-0.5 border border-cream-200">
                  <input type="hidden" name="product_id" value="<?= (int) $i['product_id'] ?>">
                  <button type="submit" name="action" value="decrement"
                    class="w-6 h-6 bg-white text-ink-muted rounded border border-cream-200 flex items-center justify-center text-sm font-bold hover:bg-cream-50 hover:text-brand-700 transition">
                    −
                  </button>
                  <span class="w-7 text-center text-xs font-bold text-ink"><?= (int) $i['qty'] ?></span>
                  <button type="submit" name="action" value="add"
                    class="w-6 h-6 bg-white text-ink-muted rounded border border-cream-200 flex items-center justify-center text-sm font-bold hover:bg-cream-50 hover:text-brand-700 transition <?= $i['qty'] >= $i['stock_qty'] ? 'opacity-40 cursor-not-allowed' : '' ?>"
                    <?= $i['qty'] >= $i['stock_qty'] ? 'disabled' : '' ?>>+</button>
                </form>

                <div class="text-right min-w-[72px]">
                  <p class="font-bold text-xs text-ink">₱<?= number_format($i['subtotal'], 2) ?></p>
                  <form method="post" class="inline">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= (int) $i['product_id'] ?>">
                    <button type="submit"
                      class="text-[10px] text-ink-muted hover:text-rose-600 transition inline-flex items-center gap-1">
                      <i class="fa-solid fa-trash-can"></i> Remove
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Totals + payment -->
        <div class="border-t border-cream-200 shrink-0">
          <div class="p-4 bg-cream-100 space-y-2 text-xs">
            <div class="flex justify-between text-ink-muted">
              <span>Items</span>
              <span class="font-medium text-ink"><?= (int) $cartCount ?></span>
            </div>
            <div class="flex justify-between text-ink-muted">
              <span>Subtotal</span>
              <span class="font-medium text-ink">₱<?= number_format($total, 2) ?></span>
            </div>
            <div class="pt-3 border-t border-cream-200">
              <div class="flex justify-between items-baseline gap-2">
                <span class="font-head text-sm font-bold text-ink uppercase tracking-wide">Total</span>
                <span class="font-head text-3xl font-extrabold text-brand-700 tracking-tight">
                  ₱<?= number_format($total, 2) ?>
                </span>
              </div>
            </div>
          </div>

          <div class="p-4 bg-cream-50 border-t border-cream-200 space-y-3">
            <form method="post" action="checkout.php">
              <div>
                <div class="flex justify-between items-baseline text-xs mb-1.5 font-semibold text-ink">
                  <label for="cashReceivedInput">Cash Received (₱)</label>
                  <span class="text-ink-muted font-medium">Change: <strong id="changeDisplay" class="text-ink font-bold">₱0.00</strong></span>
                </div>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-muted font-bold text-sm">₱</div>
                  <input type="number" name="cash_received" id="cashReceivedInput" step="0.01" min="0"
                    placeholder="0.00" required
                    class="w-full pl-8 pr-3 py-2.5 text-lg font-bold bg-white border border-cream-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-600 focus:border-brand-600 text-ink transition">
                </div>
              </div>

              <div class="grid grid-cols-4 gap-1.5 mt-3">
                <button type="button"
                  class="quick-cash-btn py-1.5 px-1 text-[11px] font-semibold bg-cream-100 border border-cream-200 rounded text-ink-muted hover:border-brand-200 hover:bg-white hover:text-brand-700 transition"
                  data-amount="exact">Exact</button>
                <button type="button"
                  class="quick-cash-btn py-1.5 px-1 text-[11px] font-semibold bg-cream-100 border border-cream-200 rounded text-ink-muted hover:border-brand-200 hover:bg-white hover:text-brand-700 transition"
                  data-amount="100">+₱100</button>
                <button type="button"
                  class="quick-cash-btn py-1.5 px-1 text-[11px] font-semibold bg-cream-100 border border-cream-200 rounded text-ink-muted hover:border-brand-200 hover:bg-white hover:text-brand-700 transition"
                  data-amount="500">+₱500</button>
                <button type="button"
                  class="quick-cash-btn py-1.5 px-1 text-[11px] font-semibold bg-cream-100 border border-cream-200 rounded text-ink-muted hover:border-brand-200 hover:bg-white hover:text-brand-700 transition"
                  data-amount="1000">+₱1K</button>
              </div>

              <button type="submit" id="completeSaleBtn" <?= $cartCount === 0 ? 'disabled' : '' ?>
                class="w-full mt-3 <?= $cartCount === 0 ? 'bg-cream-200 text-ink-muted/60 cursor-not-allowed' : 'bg-brand-600 hover:bg-brand-700 text-white active:scale-[0.99] shadow-lg' ?> font-head font-bold py-3 px-4 rounded-xl transition inline-flex items-center justify-center gap-2 text-base tracking-wide">
                <i class="fa-solid fa-circle-check"></i><span>Complete Sale</span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- ===================== Toast ===================== -->
  <?php if ($error || $success): ?>
    <div id="toast"
      class="fixed bottom-5 left-1/2 -translate-x-1/2 <?= $error ? 'bg-rose-700' : 'bg-brand-700' ?> text-white px-4 py-2.5 rounded-lg text-xs shadow-lg flex items-center gap-2 z-50 transition-all duration-300 ring-1 ring-white/10">
      <i class="fa-solid <?= $error ? 'fa-circle-exclamation text-rose-200' : 'fa-circle-info text-brand-200' ?>"></i>
      <span><?= htmlspecialchars($error ?: $success) ?></span>
    </div>
  <?php endif; ?>

  <script>
    // --- Client-Side Pagination & Filtering ---
    const pills = document.querySelectorAll('.cat-pill');
    const allRows = Array.from(document.querySelectorAll('.product-row'));
    const catalogSearch = document.getElementById('catalogSearch');
    const paginationContainer = document.getElementById('paginationControls');
    const paginationInfo = document.getElementById('paginationInfo');

    let activeCategory = 0;
    let currentPage = 1;
    const itemsPerPage = 10;
    let filteredRows = [...allRows];

    function renderPagination() {
      const totalPages = Math.ceil(filteredRows.length / itemsPerPage) || 1;
      if (currentPage > totalPages) currentPage = totalPages;

      // Hide all rows first
      allRows.forEach(row => row.style.display = 'none');

      // Show only rows for current page
      const start = (currentPage - 1) * itemsPerPage;
      const end = Math.min(start + itemsPerPage, filteredRows.length);

      for (let i = start; i < end; i++) {
        filteredRows[i].style.display = ''; // table-row
      }

      // Info text
      if (filteredRows.length === 0) {
        paginationInfo.textContent = "No products found";
        paginationContainer.innerHTML = '';
        return;
      }
      paginationInfo.textContent = `Showing ${start + 1}-${end} of ${filteredRows.length}`;

      // Generate Buttons
      let buttonsHTML = '';

      // Prev button
      buttonsHTML += `<button type="button" onclick="changePage(${currentPage - 1})" class="page-btn ${currentPage === 1 ? 'disabled' : ''}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>`;

      // Page numbers (sliding window)
      let startPage = Math.max(1, currentPage - 2);
      let endPage = Math.min(totalPages, startPage + 4);
      if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
      }

      for (let i = startPage; i <= endPage; i++) {
        buttonsHTML += `<button type="button" onclick="changePage(${i})" class="page-btn ${i === currentPage ? 'active' : ''}">${i}</button>`;
      }

      // Next button
      buttonsHTML += `<button type="button" onclick="changePage(${currentPage + 1})" class="page-btn ${currentPage === totalPages ? 'disabled' : ''}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>`;

      paginationContainer.innerHTML = buttonsHTML;
    }

    window.changePage = function(page) {
      const totalPages = Math.ceil(filteredRows.length / itemsPerPage) || 1;
      if (page < 1 || page > totalPages) return;
      currentPage = page;
      renderPagination();
    }

    function applyFilters() {
      const term = catalogSearch.value.toLowerCase().trim();

      filteredRows = allRows.filter(row => {
        const name = row.dataset.name || '';
        const code = row.dataset.code || '';
        const cat = parseInt(row.dataset.category || '0', 10);
        const matchCat = (activeCategory === 0 || cat === activeCategory);
        const matchTerm = term === '' || name.includes(term) || code.includes(term);
        return matchCat && matchTerm;
      });

      currentPage = 1; // Reset to first page when filters change
      renderPagination();
    }

    // Category Click Listeners
    pills.forEach(p => p.addEventListener('click', () => {
      pills.forEach(x => {
        x.classList.remove('bg-brand-600', 'text-white', 'shadow-sm', 'border-brand-600');
        x.classList.add('bg-cream-50', 'text-ink-muted', 'border', 'border-cream-200');
      });
      p.classList.remove('bg-cream-50', 'text-ink-muted', 'border', 'border-cream-200');
      p.classList.add('bg-brand-600', 'text-white', 'shadow-sm', 'border-brand-600');
      activeCategory = parseInt(p.dataset.category, 10);
      applyFilters();
    }));

    // Search Input Listener
    catalogSearch.addEventListener('input', applyFilters);

    // Initial render on load
    applyFilters();

    // --- Cash change display ---
    const total = <?= json_encode((float) $total) ?>;
    const cashInput = document.getElementById('cashReceivedInput');
    const changeDisplay = document.getElementById('changeDisplay');

    function updateChange() {
      const cash = parseFloat(cashInput.value) || 0;
      const change = cash - total;
      if (cash === 0) {
        changeDisplay.textContent = '₱0.00';
        changeDisplay.className = 'text-ink font-bold';
      } else if (change >= 0) {
        changeDisplay.textContent = '₱' + change.toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
        changeDisplay.className = 'text-emerald-700 font-bold';
      } else {
        changeDisplay.textContent = 'Needs +₱' + Math.abs(change).toFixed(2);
        changeDisplay.className = 'text-rose-700 font-bold';
      }
    }
    cashInput.addEventListener('input', updateChange);

    // --- Quick cash presets ---
    document.querySelectorAll('.quick-cash-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const type = btn.dataset.amount;
        const current = parseFloat(cashInput.value) || 0;
        if (type === 'exact') {
          cashInput.value = total.toFixed(2);
        } else {
          cashInput.value = (current + parseFloat(type)).toFixed(2);
        }
        updateChange();
      });
    });

    // --- Keyboard shortcuts ---
    window.addEventListener('keydown', (e) => {
      if (e.key === 'F2') {
        e.preventDefault();
        document.getElementById('barcodeInput').focus();
      } else if (e.key === 'F4') {
        e.preventDefault();
        cashInput.focus();
      }
    });

    // --- Auto-dismiss toast ---
    const toast = document.getElementById('toast');
    if (toast) {
      setTimeout(() => {
        toast.classList.add('opacity-0', 'pointer-events-none');
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }
  </script>
</body>

</html>