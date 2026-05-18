<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Get stock ledger (all movements)
function getStockLedger($filters = []) {
    global $pdo;

    $where  = [];
    $params = [];

    if (!empty($filters['product_id'])) {
        $where[]  = "sl.product_id = ?";
        $params[] = (int)$filters['product_id'];
    }
    if (!empty($filters['type']) && in_array($filters['type'], ['IN', 'OUT'])) {
        $where[]  = "sl.type = ?";
        $params[] = $filters['type'];
    }
    if (!empty($filters['date_from'])) {
        $where[]  = "DATE(sl.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[]  = "DATE(sl.created_at) <= ?";
        $params[] = $filters['date_to'];
    }

    $sql = "SELECT sl.*, p.category
            FROM stock_ledger sl
            LEFT JOIN products p ON p.id = sl.product_id"
         . ($where ? " WHERE " . implode(" AND ", $where) : "")
         . " ORDER BY sl.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ledger = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ["success" => true, "ledger" => $ledger];
}

// Get current stock per product (aggregated by name)
function getCurrentStock($productId = null) {
    global $pdo;

    $where  = [];
    $params = [];

    // If a specific ID is passed, we still group by name of THAT product
    if ($productId) {
        $where[]  = "p.name = (SELECT name FROM products WHERE id = ?)";
        $params[] = (int)$productId;
    }

    $sql = "SELECT
                MIN(p.id) AS id,
                p.name,
                CASE WHEN COUNT(DISTINCT p.category) > 1 THEN 'Multiple Categories' ELSE MIN(p.category) END AS category,
                MIN(p.sku) AS sku,
                MAX(p.price) AS price,
                MIN(p.supplier) AS supplier,
                CASE WHEN COUNT(DISTINCT p.status) > 1 THEN 'mixed' ELSE MIN(p.status) END AS status,
                COUNT(p.id) AS merged_count,
                GROUP_CONCAT(p.id) AS aggregated_ids,
                COALESCE(SUM(moves.stock_in), 0) AS stock_in,
                COALESCE(SUM(moves.stock_out), 0) AS stock_out,
                COALESCE(SUM(moves.stock_in), 0) - COALESCE(SUM(moves.stock_out), 0) AS current_stock
            FROM products p
            LEFT JOIN (
                SELECT
                    product_id,
                    SUM(CASE WHEN type='IN'  THEN quantity ELSE 0 END) AS stock_in,
                    SUM(CASE WHEN type='OUT' THEN quantity ELSE 0 END) AS stock_out
                FROM stock_ledger
                GROUP BY product_id
            ) moves ON moves.product_id = p.id
            WHERE p.approval_status = 'approved'"
         . ($where ? " AND " . implode(" AND ", $where) : "")
         . " GROUP BY LOWER(TRIM(p.name))
             ORDER BY p.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ["success" => true, "stock" => $rows];
}

// Get stock level for a single product (used by order forms)
function getProductStock($productId) {
    global $pdo;

    $productId = (int)$productId;
    if (!$productId) {
        return ["success" => false, "message" => "Product ID required.", "_code" => 400];
    }

    $stmt = $pdo->prepare(
        "SELECT
            p.id, p.name,
            COALESCE(SUM(CASE WHEN sl.type='IN'  THEN sl.quantity ELSE 0 END), 0)
          - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END), 0) AS current_stock
         FROM products p
         LEFT JOIN stock_ledger sl ON sl.product_id = p.id
         WHERE p.id = ?
         GROUP BY p.id, p.name"
    );
    $stmt->execute([$productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ["success" => false, "message" => "Product not found.", "_code" => 404];
    }

    return ["success" => true, "product_id" => $row['id'], "name" => $row['name'], "current_stock" => (int)$row['current_stock']];
}
