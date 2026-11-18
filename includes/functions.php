<?php
function log_action($pdo, $action, $details = '') {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)"
    );
    $stmt->execute([$_SESSION['user_id'], $action, $details]);
}

/**
 * Generate a short, unique, human-typeable code for a product.
 * Example: Rackets category + product_id 7 → "RAC0007"
 * Falls back to "GEN" if the category name has no letters.
 * Guarantees uniqueness (appends -1, -2 ... only if a manual code already claimed it).
 */
function generate_item_code(PDO $pdo, int $product_id, string $category_name): string {
    $prefix = strtoupper(preg_replace('/[^A-Za-z]/', '', $category_name));
    $prefix = substr($prefix, 0, 3);
    if (strlen($prefix) < 3) $prefix = str_pad($prefix, 3, 'X');

    $base = $prefix . str_pad((string)$product_id, 4, '0', STR_PAD_LEFT);
    $code = $base;
    $n    = 0;
    $check = $pdo->prepare("SELECT 1 FROM products WHERE barcode = ? AND product_id <> ?");
    while (true) {
        $check->execute([$code, $product_id]);
        if (!$check->fetch()) return $code;
        $n++;
        $code = $base . '-' . $n;
    }
}