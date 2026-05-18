<?php
require_once __DIR__ . '/../config/db.php';        // database connection load garne
require_once __DIR__ . '/../config/helpers.php';  // helper functions load garne

// Yo function le stock ledger (sabai stock movement) fetch garxa
function getStockLedger($filters = []) {
    global $pdo;

    $where  = [];   // condition store garne array
    $params = [];   // query ko values store garne

    // product_id filter xa vane add garne
    if (!empty($filters['product_id'])) {
        $where[]  = "sl.product_id = ?";
        $params[] = (int)$filters['product_id'];
    }

    // type filter (IN ya OUT matra allow)
    if (!empty($filters['type']) && in_array($filters['type'], ['IN', 'OUT'])) {
        $where[]  = "sl.type = ?";
        $params[] = $filters['type'];
    }

    // start date filter
    if (!empty($filters['date_from'])) {
        $where[]  = "DATE(sl.created_at) >= ?";
        $params[] = $filters['date_from'];
    }

    // end date filter
    if (!empty($filters['date_to'])) {
        $where[]  = "DATE(sl.created_at) <= ?";
        $params[] = $filters['date_to'];
    }

    // main query (ledger + product category join)
    $sql = "SELECT sl.*, p.category
            FROM stock_ledger sl
            LEFT JOIN products p ON p.id = sl.product_id"
         . ($where ? " WHERE " . implode(" AND ", $where) : "") // condition xa vane add garne
         . " ORDER BY sl.created_at DESC"; // latest first

    $stmt = $pdo->prepare($sql); // query prepare garne
    $stmt->execute($params);     // execute with params
    $ledger = $stmt->fetchAll(PDO::FETCH_ASSOC); // data fetch garne

    return ["success" => true, "ledger" => $ledger]; // result return
}

// Yo function le current stock calculate garxa (same name product merge garera)
function getCurrentStock($productId = null) {
    global $pdo;

    $where  = [];
    $params = [];

    // specific product_id aaye pani same name ko sabai merge hunxa
    if ($productId) {
        $where[]  = "p.name = (SELECT name FROM products WHERE id = ?)";
        $params[] = (int)$productId;
    }

    // main query (stock_in - stock_out = current stock)
    $sql = "SELECT
                MIN(p.id) AS id,   // sabai ma sabai vanda sano id
                p.name,
                CASE WHEN COUNT(DISTINCT p.category) > 1 
                     THEN 'Multiple Categories' 
                     ELSE MIN(p.category) END AS category, // category mix vaye label change
                MIN(p.sku) AS sku,
                MAX(p.price) AS price,
                MIN(p.supplier) AS supplier,
                CASE WHEN COUNT(DISTINCT p.status) > 1 
                     THEN 'mixed' 
                     ELSE MIN(p.status) END AS status,
                COUNT(p.id) AS merged_count, // kati ota product merge vayo
                GROUP_CONCAT(p.id) AS aggregated_ids, // sabai id list
                COALESCE(SUM(moves.stock_in), 0) AS stock_in,
                COALESCE(SUM(moves.stock_out), 0) AS stock_out,
                COALESCE(SUM(moves.stock_in), 0) - COALESCE(SUM(moves.stock_out), 0) AS current_stock
            FROM products p
            LEFT JOIN (
                // yo subquery le IN ra OUT calculate garxa
                SELECT
                    product_id,
                    SUM(CASE WHEN type='IN'  THEN quantity ELSE 0 END) AS stock_in,
                    SUM(CASE WHEN type='OUT' THEN quantity ELSE 0 END) AS stock_out
                FROM stock_ledger
                GROUP BY product_id
            ) moves ON moves.product_id = p.id
            WHERE p.approval_status = 'approved'" // approved product matra
         . ($where ? " AND " . implode(" AND ", $where) : "")
         . " GROUP BY LOWER(TRIM(p.name)) // same name lai group garne
             ORDER BY p.name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ["success" => true, "stock" => $rows];
}

// Yo function le single product ko current stock dinxa (order form ma use hunxa)
function getProductStock($productId) {
    global $pdo;

    $productId = (int)$productId; // int ma convert
    if (!$productId) {
        return ["success" => false, "message" => "Product ID required.", "_code" => 400];
    }

    // IN - OUT = current stock calculate
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

    // product nai vayena vane error
    if (!$row) {
        return ["success" => false, "message" => "Product not found.", "_code" => 404];
    }

    // final result return
    return [
        "success" => true,
        "product_id" => $row['id'],
        "name" => $row['name'],
        "current_stock" => (int)$row['current_stock']
    ];
}