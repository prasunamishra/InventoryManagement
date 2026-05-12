<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/openai.php';

class ChatController
{
    /**
     * Main entry point for chatbot messages
     */
    public static function handleMessage($message)
    {
        global $pdo;
        $lowerMsg = strtolower(trim($message));

        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['chat_history'])) $_SESSION['chat_history'] = [];

        // Ensure min_stock column exists in products table
        self::ensureMinStockColumn();

        // --- Handle "Proceed" for restock redirect ---
        if ($lowerMsg === 'proceed' && isset($_SESSION['last_suggestion'])) {
            $last = $_SESSION['last_suggestion'];
            return [
                'reply' => "Redirecting to stock update for **{$last['name']}**...",
                'action' => 'redirect_update_stock',
                'product_id' => $last['id'],
                'qty' => $last['qty'] ?? 0
            ];
        }

        // --- Check if user selected a product from a previous list ---
        if (!empty($_SESSION['restock_candidates'])) {
            $match = self::findProductMatch($lowerMsg, $_SESSION['restock_candidates']);
            if ($match) {
                $_SESSION['last_suggestion'] = $match;
                unset($_SESSION['restock_candidates']);
                return [
                    'reply' => "Redirecting to stock update for **{$match['name']}**...",
                    'action' => 'redirect_update_stock',
                    'product_id' => $match['id'],
                    'qty' => 0
                ];
            }
        }

        // --- Local intent detection (no API call needed) ---
        $localResponse = self::detectIntent($lowerMsg);
        if ($localResponse) return $localResponse;

        // --- Fall back to OpenAI for general questions ---
        $ctx = self::getInventoryContext();
        $aiResp = self::callOpenAI($message, $ctx);

        if ($aiResp) {
            $_SESSION['chat_history'][] = ['role' => 'user', 'content' => $message];
            $_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $aiResp['reply']];
            if (count($_SESSION['chat_history']) > 10) {
                $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -10);
            }
            self::detectRestockSuggestion($aiResp['reply']);
            return $aiResp;
        }

        return [
            'reply' => "I'm having trouble connecting right now. Try a quick command:",
            'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  INTENT DETECTION
    // ═══════════════════════════════════════════════════════════════════════

    private static function detectIntent($msg)
    {
        // Check specific intents FIRST (before greeting, to avoid "hi" matching inside "which")

        if (self::matches($msg, ['which product to restock', 'which product', 'what to restock', 'restock which']))
            return self::lowStock();

        if (self::matches($msg, ['inventory status', 'stock overview', 'inventory report', 'inventory insights', 'inventory summary', 'show inventory']))
            return self::inventorySummary();

        if (self::matches($msg, ['critical items', 'critical stock', 'critical only', 'show critical']))
            return self::criticalOnly();

        if (self::matches($msg, ['out of stock', 'zero stock', 'no stock']))
            return self::outOfStock();

        if (self::matches($msg, ['low stock', 'running low', 'restock', 'inventory alert', 'stock alert', 'what should i restock', 'products running low']))
            return self::lowStock();

        if (self::matches($msg, ['high demand', 'fast selling', 'popular products', 'top selling', 'demand report', 'best seller', 'most sold', 'top products']))
            return self::highDemand();

        if (self::matches($msg, ['lowest stock first', 'sort by lowest', 'sort by stock']))
            return self::lowStockSorted();

        if (self::matches($msg, ['supplier info', 'supplier details', 'supplier information', 'show supplier']))
            return self::supplierDetails();

        // Greetings LAST — use word-boundary match to avoid "hi" matching inside "which"
        if (self::isGreeting($msg))
            return self::greeting();

        return null;
    }

    private static function matches($msg, $keywords)
    {
        foreach ($keywords as $kw) {
            if (strpos($msg, $kw) !== false) return true;
        }
        return false;
    }

    private static function isGreeting($msg)
    {
        // Exact short greetings (word boundary)
        if (preg_match('/^(hi|hey|hello|yo)$/i', $msg)) return true;
        if (preg_match('/\b(hello|good morning|good afternoon|good evening|howdy|greetings)\b/i', $msg)) return true;
        return false;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  INTENT HANDLERS
    // ═══════════════════════════════════════════════════════════════════════

    private static function greeting()
    {
        return [
            'reply' => "👋 Hello! I'm your **Inventory Assistant**.\n\nI can help you with:\n• Check **low stock** products\n• View **high demand** items\n• Get a full **inventory summary**\n• Find **out of stock** products\n• View **supplier details**\n\nWhat would you like to check?",
            'suggestions' => ['Low stock', 'High demand products', 'Inventory summary', 'Out of stock', 'Supplier details']
        ];
    }

    private static function lowStock()
    {
        global $pdo;
        $items = $pdo->query(
            "SELECT id, name, stock, min_stock, supplier
             FROM products
             WHERE stock <= min_stock AND approval_status = 'approved'
             ORDER BY stock ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply' => "✅ All products currently have sufficient stock. No low stock alerts!",
                'suggestions' => ['High demand products', 'Inventory summary', 'Out of stock']
            ];
        }

        $_SESSION['restock_candidates'] = $items;

        $products = [];
        foreach ($items as $p) {
            $status = 'Low';
            if ((int)$p['stock'] === 0) $status = 'Out of Stock';
            elseif ((int)$p['stock'] <= floor((int)$p['min_stock'] / 3)) $status = 'Critical';
            $products[] = [
                'id' => (int)$p['id'], 'name' => $p['name'],
                'stock' => (int)$p['stock'], 'min_stock' => (int)$p['min_stock'],
                'status' => $status, 'supplier' => $p['supplier'] ?: '—'
            ];
        }

        return [
            'reply' => "🔴 **Low Stock Alerts** — " . count($products) . " product(s) need attention:",
            'type' => 'low_stock',
            'products' => $products,
            'suggestions' => ['Which product to restock?', 'Show critical items only', 'Sort by lowest stock first', 'Supplier details', 'High demand products']
        ];
    }

    private static function criticalOnly()
    {
        global $pdo;
        $items = $pdo->query(
            "SELECT id, name, stock, min_stock, supplier
             FROM products
             WHERE stock <= FLOOR(min_stock / 3) AND approval_status = 'approved'
             ORDER BY stock ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply' => "✅ No critical stock items right now. All products are within safe levels.",
                'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
            ];
        }

        $_SESSION['restock_candidates'] = $items;

        $products = [];
        foreach ($items as $p) {
            $status = ((int)$p['stock'] === 0) ? 'Out of Stock' : 'Critical';
            $products[] = [
                'id' => (int)$p['id'], 'name' => $p['name'],
                'stock' => (int)$p['stock'], 'min_stock' => (int)$p['min_stock'],
                'status' => $status, 'supplier' => $p['supplier'] ?: '—'
            ];
        }

        return [
            'reply' => "🚨 **Critical Stock Items** — " . count($products) . " product(s) need urgent restock:",
            'type' => 'low_stock',
            'products' => $products,
            'suggestions' => ['Which product to restock?', 'Show all low stock', 'Supplier details', 'Inventory summary']
        ];
    }

    private static function outOfStock()
    {
        global $pdo;
        $items = $pdo->query(
            "SELECT id, name, stock, min_stock, supplier
             FROM products WHERE stock = 0 AND approval_status = 'approved'"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply' => "✅ No out-of-stock products. All items have available inventory.",
                'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
            ];
        }

        $_SESSION['restock_candidates'] = $items;

        $products = [];
        foreach ($items as $p) {
            $products[] = [
                'id' => (int)$p['id'], 'name' => $p['name'],
                'stock' => 0, 'min_stock' => (int)$p['min_stock'],
                'status' => 'Out of Stock', 'supplier' => $p['supplier'] ?: '—'
            ];
        }

        return [
            'reply' => "⛔ **Out of Stock** — " . count($products) . " product(s) have zero inventory:",
            'type' => 'low_stock',
            'products' => $products,
            'suggestions' => ['Restock a product', 'Low stock', 'Supplier details', 'Inventory summary']
        ];
    }

    private static function lowStockSorted()
    {
        global $pdo;
        $items = $pdo->query(
            "SELECT id, name, stock, min_stock, supplier
             FROM products WHERE approval_status = 'approved'
             ORDER BY stock ASC LIMIT 15"
        )->fetchAll(PDO::FETCH_ASSOC);

        $_SESSION['restock_candidates'] = $items;

        $products = [];
        foreach ($items as $p) {
            $status = 'OK';
            if ((int)$p['stock'] === 0) $status = 'Out of Stock';
            elseif ((int)$p['stock'] <= floor((int)$p['min_stock'] / 3)) $status = 'Critical';
            elseif ((int)$p['stock'] <= (int)$p['min_stock']) $status = 'Low';
            $products[] = [
                'id' => (int)$p['id'], 'name' => $p['name'],
                'stock' => (int)$p['stock'], 'min_stock' => (int)$p['min_stock'],
                'status' => $status, 'supplier' => $p['supplier'] ?: '—'
            ];
        }

        return [
            'reply' => "📊 **Products Sorted by Lowest Stock First:**",
            'type' => 'low_stock',
            'products' => $products,
            'suggestions' => ['Which product to restock?', 'Show critical items only', 'High demand products', 'Inventory summary']
        ];
    }

    private static function highDemand()
    {
        global $pdo;
        $items = $pdo->query(
            "SELECT p.id, p.name, p.stock, p.min_stock, COUNT(l.id) as sales_count
             FROM products p
             LEFT JOIN logistics l ON LOWER(l.product) LIKE CONCAT('%', LOWER(p.name), '%')
             WHERE p.approval_status = 'approved'
             GROUP BY p.id
             HAVING sales_count > 0
             ORDER BY sales_count DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply' => "📊 No high demand products detected right now.",
                'suggestions' => ['Low stock', 'Inventory summary', 'Out of stock']
            ];
        }

        $products = [];
        foreach ($items as $p) {
            $sc = (int)$p['sales_count'];
            $demand = ($sc >= 5) ? 'High' : (($sc >= 3) ? 'Medium' : 'Moderate');
            $products[] = [
                'id' => (int)$p['id'], 'name' => $p['name'],
                'sales_count' => $sc, 'demand_level' => $demand,
                'stock' => (int)$p['stock']
            ];
        }

        return [
            'reply' => "🔥 **High Demand Products** — " . count($products) . " product(s):",
            'type' => 'high_demand',
            'products' => $products,
            'suggestions' => ['Restock high demand product', 'Low stock', 'Inventory summary', 'Show critical items only']
        ];
    }

    private static function inventorySummary()
    {
        global $pdo;
        $total      = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE approval_status='approved'")->fetchColumn();
        $totalStock = (int)$pdo->query("SELECT COALESCE(SUM(stock),0) FROM products WHERE approval_status='approved'")->fetchColumn();
        $lowCount   = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock <= min_stock AND approval_status='approved'")->fetchColumn();
        $outCount   = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0 AND approval_status='approved'")->fetchColumn();
        $orders     = (int)$pdo->query("SELECT COUNT(*) FROM logistics")->fetchColumn();

        // Low stock items
        $lowItems = $pdo->query(
            "SELECT id, name, stock, min_stock, supplier FROM products
             WHERE stock <= min_stock AND approval_status='approved' ORDER BY stock ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $lowProducts = [];
        foreach ($lowItems as $p) {
            $status = 'Low';
            if ((int)$p['stock'] === 0) $status = 'Out of Stock';
            elseif ((int)$p['stock'] <= floor((int)$p['min_stock'] / 3)) $status = 'Critical';
            $lowProducts[] = [
                'id' => (int)$p['id'], 'name' => $p['name'],
                'stock' => (int)$p['stock'], 'min_stock' => (int)$p['min_stock'],
                'status' => $status, 'supplier' => $p['supplier'] ?: '—'
            ];
        }

        // High demand items
        $demandItems = $pdo->query(
            "SELECT p.id, p.name, p.stock, COUNT(l.id) as sales_count
             FROM products p
             LEFT JOIN logistics l ON LOWER(l.product) LIKE CONCAT('%', LOWER(p.name), '%')
             WHERE p.approval_status='approved'
             GROUP BY p.id HAVING sales_count > 0
             ORDER BY sales_count DESC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        $demandProducts = [];
        foreach ($demandItems as $p) {
            $sc = (int)$p['sales_count'];
            $demandProducts[] = [
                'id' => (int)$p['id'], 'name' => $p['name'],
                'sales_count' => $sc,
                'demand_level' => ($sc >= 5) ? 'High' : (($sc >= 3) ? 'Medium' : 'Moderate'),
                'stock' => (int)$p['stock']
            ];
        }

        if (!empty($lowItems)) $_SESSION['restock_candidates'] = $lowItems;

        return [
            'reply' => "📋 **Inventory Summary**",
            'type' => 'inventory_summary',
            'summary' => [
                'total_products' => $total,
                'total_stock' => $totalStock,
                'low_stock_count' => $lowCount,
                'out_of_stock_count' => $outCount,
                'total_orders' => $orders
            ],
            'low_stock_products' => $lowProducts,
            'high_demand_products' => $demandProducts,
            'suggestions' => ['Low stock', 'High demand products', 'Show critical items only', 'Sort by lowest stock first', 'Out of stock']
        ];
    }

    private static function supplierDetails()
    {
        global $pdo;
        $items = $pdo->query(
            "SELECT DISTINCT supplier, COUNT(*) as product_count, SUM(stock) as total_stock
             FROM products
             WHERE approval_status='approved' AND supplier != ''
             GROUP BY supplier ORDER BY supplier"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply' => "📦 No supplier information available for current products.",
                'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
            ];
        }

        $suppliers = [];
        foreach ($items as $s) {
            $suppliers[] = [
                'name' => $s['supplier'],
                'product_count' => (int)$s['product_count'],
                'total_stock' => (int)$s['total_stock']
            ];
        }

        return [
            'reply' => "🏭 **Supplier Overview** — " . count($suppliers) . " supplier(s):",
            'type' => 'supplier_info',
            'suppliers' => $suppliers,
            'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private static function ensureMinStockColumn()
    {
        global $pdo;
        try {
            $pdo->query("SELECT min_stock FROM products LIMIT 1");
        } catch (\PDOException $e) {
            $pdo->exec("ALTER TABLE products ADD COLUMN min_stock INT NOT NULL DEFAULT 10");
        }
    }

    private static function findProductMatch($msg, $candidates)
    {
        foreach ($candidates as $p) {
            if (strpos($msg, strtolower($p['name'])) !== false) {
                return ['id' => (int)$p['id'], 'name' => $p['name'], 'qty' => 0];
            }
        }
        // Also try partial match
        foreach ($candidates as $p) {
            $words = explode(' ', strtolower($p['name']));
            foreach ($words as $word) {
                if (strlen($word) > 3 && strpos($msg, $word) !== false) {
                    return ['id' => (int)$p['id'], 'name' => $p['name'], 'qty' => 0];
                }
            }
        }
        return null;
    }

    private static function getInventoryContext()
    {
        global $pdo;
        $stmt = $pdo->query("SELECT id, name, stock, min_stock, supplier FROM products WHERE stock <= min_stock AND approval_status = 'approved'");
        $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $outCount = $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();

        $demand = $pdo->query(
            "SELECT p.name, COUNT(l.id) as sales_freq
             FROM products p
             LEFT JOIN logistics l ON LOWER(l.product) LIKE CONCAT('%', LOWER(p.name), '%')
             WHERE p.approval_status = 'approved'
             GROUP BY p.id ORDER BY sales_freq DESC LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        $ctx = "REAL-TIME INVENTORY DATA:\n";
        $ctx .= "- Out-of-Stock Items: {$outCount}\n";
        $ctx .= "- Low Stock Products:\n";
        foreach ($lowStock as $p) {
            $ctx .= "  * {$p['name']} (ID:{$p['id']}): Stock={$p['stock']}, Min={$p['min_stock']}, Supplier={$p['supplier']}\n";
        }
        $ctx .= "\nDEMAND TRENDS:\n";
        foreach ($demand as $d) {
            $ctx .= "  * {$d['name']}: {$d['sales_freq']} shipments\n";
        }
        return $ctx;
    }

    private static function callOpenAI($userMessage, $inventoryContext)
    {
        $apiKey = OPENAI_API_KEY;
        $model = OPENAI_MODEL;
        if (empty($apiKey)) { self::logError("OpenAI API Key missing."); return null; }

        $systemPrompt = "You are 'GroceryFlow AI', an advanced inventory management assistant.
CONTEXT: {$inventoryContext}
RULES:
- Always show ALL matching products, never truncate.
- Use markdown formatting with bold, bullets, tables.
- After every response, suggest 3-5 smart follow-up questions.
- When showing product lists, include name, stock, and status.
- Be conversational, professional, and helpful.
- Guide users toward restocking and inventory decisions.
- If user mentions a product name, suggest restocking it.";

        $input = $systemPrompt . "\n\n";
        if (isset($_SESSION['chat_history'])) {
            foreach ($_SESSION['chat_history'] as $h) {
                $input .= ($h['role'] === 'assistant' ? "AI: " : "User: ") . $h['content'] . "\n";
            }
        }
        $input .= "User: " . $userMessage;

        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['model' => $model, 'input' => $input, 'store' => true]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) { self::logError("CURL: " . curl_error($ch)); curl_close($ch); return null; }
        curl_close($ch);

        $result = json_decode($response, true);
        $reply = null;

        if ($httpCode === 200) {
            // Responses API format: output[0].content[0].text
            if (isset($result['output'][0]['content'][0]['text'])) {
                $reply = $result['output'][0]['content'][0]['text'];
            }
            // Legacy format: output.text
            elseif (isset($result['output']['text'])) {
                $reply = $result['output']['text'];
            }
            // Chat Completions format
            elseif (isset($result['choices'][0]['message']['content'])) {
                $reply = $result['choices'][0]['message']['content'];
            }
        }

        if ($reply) {
            $suggestions = self::extractSuggestions($reply);
            return ['reply' => $reply, 'suggestions' => $suggestions];
        } else {
            $err = $result['error']['message'] ?? 'Unknown Error';
            self::logError("OpenAI ($httpCode): $err");
            if ($httpCode === 401) return ['reply' => "Error: Invalid API Key."];
            if ($httpCode === 404) return ['reply' => "Error: Model '$model' not found."];
            if ($httpCode === 429) return ['reply' => "Error: API rate limit exceeded."];
        }
        return null;
    }

    private static function detectRestockSuggestion($reply)
    {
        global $pdo;
        $products = $pdo->query("SELECT id, name FROM products WHERE approval_status='approved'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as $p) {
            if (stripos($reply, $p['name']) !== false) {
                if (preg_match('/(?:restock|add|order)\s+(\d+)/i', $reply, $m)) {
                    $_SESSION['last_suggestion'] = ['id' => $p['id'], 'name' => $p['name'], 'qty' => (int)$m[1]];
                    return;
                }
            }
        }
    }

    private static function extractSuggestions(&$reply)
    {
        $suggestions = [];
        $lines = explode("\n", $reply);
        $newLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^\* (.*?\?)/', $trimmed, $m)) {
                $suggestions[] = rtrim($m[1], '?');
            } else {
                $newLines[] = $line;
            }
        }
        if (empty($suggestions)) {
            $suggestions = ['Low stock', 'High demand products', 'Inventory summary'];
        }
        if (isset($_SESSION['last_suggestion'])) {
            array_unshift($suggestions, 'Proceed');
        }
        $reply = trim(implode("\n", $newLines));
        return array_unique(array_slice($suggestions, 0, 5));
    }

    private static function logError($message)
    {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir . '/chat_error.log', "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
    }
}
