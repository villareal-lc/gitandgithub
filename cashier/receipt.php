<?php
require '../config/db.php';
require '../includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
  "SELECT s.*, u.full_name FROM sales s JOIN users u ON s.user_id = u.user_id WHERE s.sale_id = ?"
);
$stmt->execute([$id]);
$sale = $stmt->fetch();

// cashiers can only open their own receipts
if (!$sale || ($_SESSION['role'] === 'cashier' && $sale['user_id'] != $_SESSION['user_id'])) {
  header('Location: ' . home_for($_SESSION['role']));
  exit;
}

$stmt = $pdo->prepare(
  "SELECT si.*, p.product_name FROM sales_items si
     JOIN products p ON si.product_id = p.product_id WHERE si.sale_id = ?"
);
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$itemCount = 0;
foreach ($items as $i)
  $itemCount += $i['quantity'];

$saleDate = date('M j, Y g:i A', strtotime($sale['sale_date']));

// ---------- Smart "Back" target ----------
$backUrl   = home_for($_SESSION['role']);
$backLabel = $_SESSION['role'] === 'cashier' ? 'New Transaction' : 'Back to Dashboard';

if ($_SESSION['role'] === 'admin') {
  $resolved = false;

  // 1) Prefer same-site admin referer (keeps filters/search on the source page).
  $ref = $_SERVER['HTTP_REFERER'] ?? '';
  if ($ref !== '') {
    $parts = parse_url($ref);
    $path  = $parts['path'] ?? '';
    $host  = $parts['host'] ?? '';
    if (
      $host === ($_SERVER['HTTP_HOST'] ?? '')
      && strpos($path, '/shuttlecore/admin/') === 0
      && basename($path) !== 'receipt.php'
    ) {
      // Build a relative URL — no scheme/host, so no open-redirect risk.
      $backUrl   = $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
      $backLabel = strpos($path, 'sales.php') !== false ? 'Back to Sales' : 'Back';
      $resolved  = true;
    }
  }

  // 2) Explicit ?from=sales fallback — works even if the Referer header is stripped.
  if (!$resolved && ($_GET['from'] ?? '') === 'sales') {
    $backUrl   = '/shuttlecore/admin/sales.php';
    $backLabel = 'Back to Sales';
  }
}

$page_title = 'Receipt #' . $id;
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
    @keyframes popIn {
      from {
        opacity: 0;
        transform: translateY(14px) scale(0.97);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .pop-in {
      animation: popIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }

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

    @media print {

      header,
      .no-print {
        display: none !important;
      }

      html,
      body {
        background: #fff !important;
      }

      main {
        padding: 0 !important;
        display: block !important;
      }

      .modal-card {
        box-shadow: none !important;
        border: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        max-width: 100% !important;
        border-radius: 0 !important;
      }

      .receipt-print-area {
        background: #fff !important;
        border: 0 !important;
        padding: 0 !important;
        font-size: 13px !important;
        max-height: none !important;
        overflow: visible !important;
        border-radius: 0 !important;
      }

      .pop-in {
        animation: none !important;
      }
    }
  </style>
</head>

<body class="h-full font-sans antialiased text-ink flex flex-col min-h-screen bg-cream-50">

  <!-- ===================== Top bar ===================== -->
  <header class="bg-brand-700 text-white shadow-md no-print">
    <div class="px-4 py-2.5 flex items-center justify-between max-w-[1700px] mx-auto gap-4">
      <div class="flex items-center gap-3 min-w-0">
        <div class="bg-brand-600 text-white p-2 rounded-lg flex items-center justify-center ring-1 ring-white/10 shrink-0">
          <i class="fa-solid fa-shuttlecock text-xl"></i>
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-head font-bold text-lg tracking-tight text-white">ShuttleCore</span>
            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 font-semibold border border-emerald-500/25">
              <i class="fa-solid fa-circle text-[7px] mr-1 text-emerald-400"></i> Sale Complete
            </span>
          </div>
          <p class="text-[11px] text-white/60 hidden sm:block">POS &amp; Inventory — Receipt</p>
        </div>
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

  <!-- ===================== Success card ===================== -->
  <main class="flex-1 flex items-center justify-center p-4">
    <div class="max-w-md w-full pop-in">
      <div class="bg-cream-100 rounded-2xl shadow-2xl border border-cream-200 p-6 flex flex-col gap-4 modal-card">

        <!-- Success header -->
        <div class="text-center space-y-1 no-print">
          <div class="w-14 h-14 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-full flex items-center justify-center mx-auto text-2xl mb-2">
            <i class="fa-solid fa-check"></i>
          </div>
          <h3 class="font-head text-xl font-bold text-ink">Transaction Complete</h3>
          <p class="text-xs text-ink-muted">Thank you for your purchase!</p>
        </div>

        <!-- Receipt body -->
        <div class="bg-cream-50 p-4 rounded-xl border border-dashed border-cream-200 font-mono text-xs space-y-2 text-ink receipt-print-area">

          <div class="text-center border-b border-cream-200 pb-2">
            <p class="font-head font-bold text-sm text-brand-700 tracking-wide">SHUTTLECORE</p>
            <p class="text-[10px] text-ink-muted">Patag Tejero St., Cagayan de Oro City</p>
            <p class="text-[10px] text-ink-muted mt-1.5">Receipt #<?= (int) $sale['sale_id'] ?></p>
            <p class="text-[10px] text-ink-muted"><?= htmlspecialchars($saleDate) ?></p>
            <p class="text-[10px] text-ink-muted">Cashier: <?= htmlspecialchars($sale['full_name']) ?></p>
          </div>

          <div class="space-y-1 py-1 max-h-64 overflow-y-auto">
            <?php foreach ($items as $i): ?>
              <div class="flex justify-between gap-2">
                <span class="truncate"><?= (int) $i['quantity'] ?>× <?= htmlspecialchars($i['product_name']) ?></span>
                <span class="whitespace-nowrap">₱<?= number_format($i['subtotal'], 2) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="border-t border-cream-200 pt-2 space-y-1">
            <div class="flex justify-between font-bold text-ink text-sm">
              <span>Total Paid:</span>
              <span>₱<?= number_format($sale['total_amount'], 2) ?></span>
            </div>
            <div class="flex justify-between text-ink-muted">
              <span>Cash Tendered:</span>
              <span>₱<?= number_format($sale['cash_received'], 2) ?></span>
            </div>
            <div class="flex justify-between text-ink-muted">
              <span>Change:</span>
              <span>₱<?= number_format($sale['change_amount'], 2) ?></span>
            </div>
          </div>

          <div class="text-center border-t border-cream-200 pt-2">
            <p class="text-[10px] text-ink-muted italic">
              <?= (int) $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?> · Thank you!
            </p>
          </div>

        </div>

        <!-- Actions -->
        <div class="flex gap-2 no-print">
          <button onclick="window.print()"
            class="flex-1 bg-cream-50 hover:bg-cream-100 text-ink border border-cream-200 font-semibold py-2.5 rounded-lg text-xs transition inline-flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-print"></i> Print
          </button>
          <a href="<?= htmlspecialchars($backUrl) ?>"
            class="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-semibold py-2.5 rounded-lg text-xs transition text-center inline-flex items-center justify-center gap-1.5">
            <?php if ($backLabel === 'New Transaction'): ?>
              <i class="fa-solid fa-plus"></i>
            <?php else: ?>
              <i class="fa-solid fa-arrow-left"></i>
            <?php endif; ?>
            <?= htmlspecialchars($backLabel) ?>
          </a>
        </div>

      </div>
    </div>
  </main>

</body>

</html>