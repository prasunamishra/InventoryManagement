<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

function getDashboardData() {
    global $pdo;
    
    $totalProducts  = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE approval_status = 'approved'")->fetchColumn();
    $totalStock     = (int) ($pdo->query("SELECT SUM(stock) FROM products WHERE approval_status = 'approved'")->fetchColumn() ?? 0);
    $lowStockCount  = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10 AND approval_status = 'approved'")->fetchColumn();
    $totalLogistics = (int) $pdo->query("SELECT COUNT(*) FROM logistics")->fetchColumn();
    $totalPending   = (int) $pdo->query("SELECT COUNT(*) FROM logistics WHERE status = 'Pending'")->fetchColumn();
    $totalStaff     = (int) $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();

    $lowStockItems = $pdo->query("
        SELECT id, name, category, stock, price, cost, supplier, storage
        FROM products
        WHERE stock < 10 AND approval_status = 'approved'
        ORDER BY stock ASC
        LIMIT 5
    ")->fetchAll();

    $recentLogistics = $pdo->query("
        SELECT *
        FROM logistics
        ORDER BY created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Enrich each logistics item with a product category if possible
    $prodCategories = $pdo->query("SELECT name, category FROM products")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recentLogistics as &$logItem) {
        $logItem['category'] = '';  // empty = fallback to customer on frontend
        $productStr = strtolower($logItem['product']);

        // First try: match by product name
        foreach ($prodCategories as $prod) {
            if (strpos($productStr, strtolower($prod['name'])) !== false) {
                $logItem['category'] = $prod['category'];
                break;
            }
        }

        // Second try: match by category keyword in the product string
        if (!$logItem['category']) {
            $seenCategories = [];
            foreach ($prodCategories as $prod) {
                $catLower = strtolower($prod['category']);
                if (!isset($seenCategories[$catLower]) && strpos($productStr, $catLower) !== false) {
                    $logItem['category'] = $prod['category'];
                    $seenCategories[$catLower] = true;
                    break;
                }
            }
        }
    }
    unset($logItem);

    $allProducts = $pdo->query("
        SELECT id, sku, name, category, stock, price, supplier
        FROM products
        WHERE approval_status = 'approved'
        ORDER BY name ASC
    ")->fetchAll();

    return [
        "success"         => true,
        "totalProducts"   => $totalProducts,
        "totalStock"      => $totalStock,
        "lowStockCount"   => $lowStockCount,
        "totalLogistics"  => $totalLogistics,
        "totalPending"    => $totalPending,
        "totalStaff"      => $totalStaff,
        "lowStockItems"   => $lowStockItems,
        "recentLogistics" => $recentLogistics,
        "allProducts"     => $allProducts,
    ];
}
