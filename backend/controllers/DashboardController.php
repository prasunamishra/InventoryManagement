<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

function getDashboardData()
{
    global $pdo;

    // count distinct product names (lowercased + trimmed) so duplicates with slightly
    // different casing don't inflate the number
    $totalProducts = (int) $pdo->query(
        "SELECT COUNT(DISTINCT LOWER(TRIM(name))) FROM products WHERE approval_status = 'approved' AND status = 'active'"
    )->fetchColumn();

    // total stock = all IN movements minus all OUT movements across the entire ledger
    // COALESCE(..., 0) prevents NULL if the table is empty
    $totalStock = (int) ($pdo->query(
        "SELECT COALESCE(SUM(CASE WHEN type='IN' THEN quantity ELSE 0 END), 0)
              - COALESCE(SUM(CASE WHEN type='OUT' THEN quantity ELSE 0 END), 0)
         FROM stock_ledger"
    )->fetchColumn() ?? 0);

    // grab up to 5 products with less than 10 units in stock
    // grouped by name so variants of the same product don't show separately
    $lowStockItems = $pdo->query(
        "SELECT MIN(p.id) as id, MIN(p.sku) as sku, p.name, 
                CASE WHEN COUNT(DISTINCT p.category) > 1 THEN 'Multiple' ELSE MIN(p.category) END as category,
                MAX(p.price) as price, MAX(p.cost) as cost, MIN(p.supplier) as supplier, MIN(p.storage) as storage,
                COALESCE(SUM(moves.stock_in), 0) - COALESCE(SUM(moves.stock_out), 0) AS stock
         FROM products p
         LEFT JOIN (
            SELECT product_id, 
                   SUM(CASE WHEN type='IN' THEN quantity ELSE 0 END) as stock_in,
                   SUM(CASE WHEN type='OUT' THEN quantity ELSE 0 END) as stock_out
            FROM stock_ledger GROUP BY product_id
         ) moves ON moves.product_id = p.id
         WHERE p.approval_status = 'approved' AND p.status = 'active'
         GROUP BY LOWER(TRIM(p.name))
         HAVING stock < 10
         ORDER BY stock ASC
         LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

    // get the total COUNT of low stock products (not just the 5 shown above)
    // we use a subquery because MySQL doesn't allow HAVING in COUNT directly
    $lowStockCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM (
            SELECT p.name,
                COALESCE(SUM(CASE WHEN sl.type='IN'  THEN sl.quantity ELSE 0 END), 0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END), 0) AS stock
            FROM products p
            LEFT JOIN stock_ledger sl ON sl.product_id = p.id
            WHERE p.approval_status = 'approved' AND p.status = 'active'
            GROUP BY LOWER(TRIM(p.name))
            HAVING stock < 10
         ) sub"
    )->fetchColumn();

    // logistics = our orders/shipments table
    $totalLogistics = (int) $pdo->query("SELECT COUNT(*) FROM logistics")->fetchColumn();
    $totalPending   = (int) $pdo->query("SELECT COUNT(*) FROM logistics WHERE status = 'Pending'")->fetchColumn();

    // just the staff headcount (not counting admins)
    $totalStaff   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
    $totalReturns = (int) ($pdo->query("SELECT COUNT(*) FROM purchase_returns")->fetchColumn() ?? 0);

    // grab the 5 most recent orders and concatenate their product names into one string
    // using GROUP_CONCAT so we don't have to do N+1 queries
    $recentLogistics = $pdo->query(
        "SELECT l.*, GROUP_CONCAT(oi.product_name ORDER BY oi.id SEPARATOR ', ') AS product
         FROM logistics l
         LEFT JOIN order_items oi ON oi.order_id = l.id
         GROUP BY l.id
         ORDER BY l.created_at DESC
         LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

    // attach the product category to each recent order
    // we look up the first product in the order and grab its category
    // not perfect but good enough for the dashboard display
    foreach ($recentLogistics as &$logItem) {
        $logItem['category'] = '';
        if (!empty($logItem['product'])) {
            // take just the first product name from the comma-separated list
            $firstProduct = explode(', ', $logItem['product'])[0];

            // strip the "x2", "x10" quantity suffix that gets appended to product names
            $firstProduct = preg_replace('/\s*x\d+\s*$/', '', $firstProduct);

            $catStmt = $pdo->prepare(
                "SELECT category FROM products WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1"
            );
            $catStmt->execute([$firstProduct]);
            $logItem['category'] = $catStmt->fetchColumn() ?: '';
        }
    }
    unset($logItem); // always unset after foreach-by-reference to avoid weird bugs

    // all approved products with their current stock - used by the chatbot for context
    $allProducts = $pdo->query(
        "SELECT MIN(p.id) as id, MIN(p.sku) as sku, p.name, 
                CASE WHEN COUNT(DISTINCT p.category) > 1 THEN 'Multiple' ELSE MIN(p.category) END as category,
                MAX(p.price) as price, MIN(p.supplier) as supplier,
                COALESCE(SUM(moves.stock_in), 0) - COALESCE(SUM(moves.stock_out), 0) AS stock
         FROM products p
         LEFT JOIN (
            SELECT product_id, 
                   SUM(CASE WHEN type='IN' THEN quantity ELSE 0 END) as stock_in,
                   SUM(CASE WHEN type='OUT' THEN quantity ELSE 0 END) as stock_out
            FROM stock_ledger GROUP BY product_id
         ) moves ON moves.product_id = p.id
         WHERE p.approval_status = 'approved' AND p.status = 'active'
         GROUP BY LOWER(TRIM(p.name))
         ORDER BY p.name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    // bundle everything up and send it back
    return [
        "success"         => true,
        "totalProducts"   => $totalProducts,
        "totalStock"      => $totalStock,
        "lowStockCount"   => $lowStockCount,
        "totalLogistics"  => $totalLogistics,
        "totalPending"    => $totalPending,
        "totalStaff"      => $totalStaff,
        "totalReturns"    => $totalReturns,
        "lowStockItems"   => $lowStockItems,
        "recentLogistics" => $recentLogistics,
        "allProducts"     => $allProducts,
    ];
}
