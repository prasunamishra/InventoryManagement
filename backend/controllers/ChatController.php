<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/openai.php'; // loads GROQ_API_KEY and GROQ_MODEL constants

class ChatController
{
    /**
     * This is the main function that gets called for every chat message.
     * It tries to handle the message locally first (faster, no API cost),
     * and only calls the Groq AI if we couldn't figure out what to do ourselves.
     */
    public static function handleMessage($message)
    {
        global $pdo;
        $lowerMsg = strtolower(trim($message));

        // make sure we have a session and a chat history array to work with
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        if (!isset($_SESSION['chat_history']))
            $_SESSION['chat_history'] = [];

        // if the AI previously suggested restocking a product and the user said "Proceed",
        // redirect them straight to the stock update page for that product
        if ($lowerMsg === 'proceed' && isset($_SESSION['last_suggestion'])) {
            $last = $_SESSION['last_suggestion'];
            return [
                'reply'      => "Redirecting to stock update for **{$last['name']}**...",
                'action'     => 'redirect_update_stock',
                'product_id' => $last['id'],
                'qty'        => $last['qty'] ?? 0
            ];
        }

        // if we previously showed the user a list of low-stock products to choose from,
        // check if their message is the name of one of those products
        if (!empty($_SESSION['restock_candidates'])) {
            $match = self::findProductMatch($lowerMsg, $_SESSION['restock_candidates']);
            if ($match) {
                $_SESSION['last_suggestion'] = $match;
                unset($_SESSION['restock_candidates']); // clear the list once they've picked one
                return [
                    'reply'      => "Redirecting to stock update for **{$match['name']}**...",
                    'action'     => 'redirect_update_stock',
                    'product_id' => $match['id'],
                    'qty'        => 0
                ];
            }
        }

        // try to handle the message locally (low stock, greeting, summary, etc.)
        // if detectIntent() returns something, we use that and skip the AI entirely
        $localResponse = self::detectIntent($lowerMsg);
        if ($localResponse)
            return $localResponse;

        // couldn't handle it locally, so we build some inventory context and ask Groq
        $ctx = self::getInventoryContext();
        $aiResp = self::callGroq($message, $ctx);

        if ($aiResp) {
            // save this exchange to session so the AI has context in future messages
            $_SESSION['chat_history'][] = ['role' => 'user',      'content' => $message];
            $_SESSION['chat_history'][] = ['role' => 'assistant',  'content' => $aiResp['reply']];

            // only keep the last 10 messages (5 back-and-forths) to keep the context manageable
            if (count($_SESSION['chat_history']) > 10) {
                $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -10);
            }

            // check if the AI mentioned restocking a specific product - save it for the "Proceed" flow
            self::detectRestockSuggestion($aiResp['reply']);
            return $aiResp;
        }

        // something went wrong with the API - give a helpful fallback response
        return [
            'reply'       => "I'm having trouble connecting right now. Try a quick command:",
            'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  INTENT DETECTION - figures out what the user is asking for locally
    // ═══════════════════════════════════════════════════════════════════════

    private static function detectIntent($msg)
    {
        // IMPORTANT: more specific intents go FIRST
        // if we put greetings first, "which" in "which product" would accidentally match "hi"

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

        // greetings go LAST so short words like "hi" don't accidentally trigger inside longer words
        if (self::isGreeting($msg))
            return self::greeting();

        // none of our local intents matched - return null so we fall back to the AI
        return null;
    }

    /**
     * Checks if any of the given keywords appear in the message.
     * We only trigger local intents for short messages (<= 5 words),
     * so longer more complex questions get passed to the AI instead.
     */
    private static function matches($msg, $keywords)
    {
        $wordCount = str_word_count($msg);

        foreach ($keywords as $kw) {
            // exact match always works no matter the length
            if ($msg === $kw) {
                return true;
            }

            // for short commands, use word-boundary matching
            // \b prevents "restocking" from matching the "restock" keyword
            if ($wordCount <= 5) {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $msg)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks if the message is a greeting.
     * We do this separately so we can use stricter matching
     * and avoid false positives inside longer messages.
     */
    private static function isGreeting($msg)
    {
        // single-word greetings like "hi", "hey", "yo"
        if (preg_match('/^(hi|hey|hello|yo)$/i', $msg))
            return true;

        // longer greetings like "good morning" or "greetings"
        if (preg_match('/\b(hello|good morning|good afternoon|good evening|howdy|greetings)\b/i', $msg))
            return true;

        return false;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  INTENT HANDLERS - each one queries the DB and formats a response
    // ═══════════════════════════════════════════════════════════════════════

    private static function greeting()
    {
        // just a friendly welcome with a list of things the bot can do
        return [
            'reply'       => "👋 Hello! I'm your **Inventory Assistant**.\n\nI can help you with:\n• Check **low stock** products\n• View **high demand** items\n• Get a full **inventory summary**\n• Find **out of stock** products\n• View **supplier details**\n\nWhat would you like to check?",
            'suggestions' => ['Low stock', 'High demand products', 'Inventory summary', 'Out of stock', 'Supplier details']
        ];
    }

    private static function lowStock()
    {
        global $pdo;

        // grab all products with 10 or fewer units in stock, sorted worst-first
        $items = $pdo->query(
            "SELECT p.id, p.name, p.supplier,
                COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END),0) AS stock,
                10 AS min_stock
             FROM products p LEFT JOIN stock_ledger sl ON sl.product_id = p.id
             WHERE p.approval_status = 'approved'
             GROUP BY p.id, p.name, p.supplier
             HAVING stock <= 10
             ORDER BY stock ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply'       => "✅ All products currently have sufficient stock. No low stock alerts!",
                'suggestions' => ['High demand products', 'Inventory summary', 'Out of stock']
            ];
        }

        // save these products to session so the user can pick one by name to restock
        $_SESSION['restock_candidates'] = $items;

        // figure out the severity status for each product
        $products = [];
        foreach ($items as $p) {
            $status = 'Low';
            if ((int) $p['stock'] === 0)
                $status = 'Out of Stock';
            elseif ((int) $p['stock'] <= floor((int) $p['min_stock'] / 3))
                $status = 'Critical'; // less than 1/3 of min_stock = critical
            $products[] = [
                'id'        => (int) $p['id'],
                'name'      => $p['name'],
                'stock'     => (int) $p['stock'],
                'min_stock' => (int) $p['min_stock'],
                'status'    => $status,
                'supplier'  => $p['supplier'] ?: '—' // show a dash if no supplier is set
            ];
        }

        return [
            'reply'       => "🔴 **Low Stock Alerts** — " . count($products) . " product(s) need attention:",
            'type'        => 'low_stock',
            'products'    => $products,
            'suggestions' => ['Which product to restock?', 'Show critical items only', 'Sort by lowest stock first', 'Supplier details', 'High demand products']
        ];
    }

    private static function criticalOnly()
    {
        global $pdo;

        // critical = stock is 3 or less (roughly bottom third of the 10-unit threshold)
        $items = $pdo->query(
            "SELECT p.id, p.name, p.supplier,
                COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END),0) AS stock,
                10 AS min_stock
             FROM products p LEFT JOIN stock_ledger sl ON sl.product_id = p.id
             WHERE p.approval_status = 'approved'
             GROUP BY p.id, p.name, p.supplier
             HAVING stock <= 3
             ORDER BY stock ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply'       => "✅ No critical stock items right now. All products are within safe levels.",
                'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
            ];
        }

        $_SESSION['restock_candidates'] = $items;

        $products = [];
        foreach ($items as $p) {
            // at this level it's either completely out or critically low
            $status = ((int) $p['stock'] === 0) ? 'Out of Stock' : 'Critical';
            $products[] = [
                'id'        => (int) $p['id'],
                'name'      => $p['name'],
                'stock'     => (int) $p['stock'],
                'min_stock' => (int) $p['min_stock'],
                'status'    => $status,
                'supplier'  => $p['supplier'] ?: '—'
            ];
        }

        return [
            'reply'       => "🚨 **Critical Stock Items** — " . count($products) . " product(s) need urgent restock:",
            'type'        => 'low_stock',
            'products'    => $products,
            'suggestions' => ['Which product to restock?', 'Show all low stock', 'Supplier details', 'Inventory summary']
        ];
    }

    private static function outOfStock()
    {
        global $pdo;

        // only products where stock <= 0 (completely empty)
        $items = $pdo->query(
            "SELECT p.id, p.name, p.supplier,
                COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END),0) AS stock,
                10 AS min_stock
             FROM products p LEFT JOIN stock_ledger sl ON sl.product_id = p.id
             WHERE p.approval_status = 'approved'
             GROUP BY p.id, p.name, p.supplier
             HAVING stock <= 0"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply'       => "✅ No out-of-stock products. All items have available inventory.",
                'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
            ];
        }

        $_SESSION['restock_candidates'] = $items;

        $products = [];
        foreach ($items as $p) {
            $products[] = [
                'id'        => (int) $p['id'],
                'name'      => $p['name'],
                'stock'     => 0, // we know it's 0, no need to read from DB
                'min_stock' => (int) $p['min_stock'],
                'status'    => 'Out of Stock',
                'supplier'  => $p['supplier'] ?: '—'
            ];
        }

        return [
            'reply'       => "⛔ **Out of Stock** — " . count($products) . " product(s) have zero inventory:",
            'type'        => 'low_stock',
            'products'    => $products,
            'suggestions' => ['Restock a product', 'Low stock', 'Supplier details', 'Inventory summary']
        ];
    }

    private static function lowStockSorted()
    {
        global $pdo;

        // show all products sorted by stock level ascending (worst first)
        // this is different from lowStock() which only shows products BELOW the threshold
        $items = $pdo->query(
            "SELECT p.id, p.name, p.supplier,
                COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END),0) AS stock,
                10 AS min_stock
             FROM products p LEFT JOIN stock_ledger sl ON sl.product_id = p.id
             WHERE p.approval_status = 'approved'
             GROUP BY p.id, p.name, p.supplier
             ORDER BY stock ASC LIMIT 15"
        )->fetchAll(PDO::FETCH_ASSOC);

        $_SESSION['restock_candidates'] = $items;

        $products = [];
        foreach ($items as $p) {
            // calculate status for every product in the list, not just low ones
            $status = 'OK';
            if ((int) $p['stock'] === 0)
                $status = 'Out of Stock';
            elseif ((int) $p['stock'] <= floor((int) $p['min_stock'] / 3))
                $status = 'Critical';
            elseif ((int) $p['stock'] <= (int) $p['min_stock'])
                $status = 'Low';
            $products[] = [
                'id'        => (int) $p['id'],
                'name'      => $p['name'],
                'stock'     => (int) $p['stock'],
                'min_stock' => (int) $p['min_stock'],
                'status'    => $status,
                'supplier'  => $p['supplier'] ?: '—'
            ];
        }

        return [
            'reply'       => "📊 **Products Sorted by Lowest Stock First:**",
            'type'        => 'low_stock',
            'products'    => $products,
            'suggestions' => ['Which product to restock?', 'Show critical items only', 'High demand products', 'Inventory summary']
        ];
    }

    private static function highDemand()
    {
        global $pdo;

        // join order_items to count how many times each product has been ordered
        // products with 0 orders are excluded (HAVING sales_count > 0)
        $items = $pdo->query(
            "SELECT p.id, p.name, COUNT(oi.id) as sales_count,
                COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END),0) AS stock,
                10 AS min_stock
             FROM products p
             LEFT JOIN order_items oi ON oi.product_id = p.id
             LEFT JOIN stock_ledger sl ON sl.product_id = p.id
             WHERE p.approval_status = 'approved'
             GROUP BY p.id, p.name
             HAVING sales_count > 0
             ORDER BY sales_count DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply'       => "📊 No high demand products detected right now.",
                'suggestions' => ['Low stock', 'Inventory summary', 'Out of stock']
            ];
        }

        // label each product's demand level based on how many times it's been ordered
        $products = [];
        foreach ($items as $p) {
            $sc     = (int) $p['sales_count'];
            $demand = ($sc >= 5) ? 'High' : (($sc >= 3) ? 'Medium' : 'Moderate');
            $products[] = [
                'id'           => (int) $p['id'],
                'name'         => $p['name'],
                'sales_count'  => $sc,
                'demand_level' => $demand,
                'stock'        => (int) $p['stock']
            ];
        }

        return [
            'reply'       => "🔥 **High Demand Products** — " . count($products) . " product(s):",
            'type'        => 'high_demand',
            'products'    => $products,
            'suggestions' => ['Restock high demand product', 'Low stock', 'Inventory summary', 'Show critical items only']
        ];
    }

    private static function inventorySummary()
    {
        global $pdo;

        // overall product and stock counts
        $total      = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE approval_status='approved'")->fetchColumn();
        $totalStock = (int) $pdo->query(
            "SELECT COALESCE(SUM(CASE WHEN type='IN' THEN quantity ELSE 0 END),0)
                  - COALESCE(SUM(CASE WHEN type='OUT' THEN quantity ELSE 0 END),0) FROM stock_ledger"
        )->fetchColumn();
        $orders = (int) $pdo->query("SELECT COUNT(*) FROM logistics")->fetchColumn();

        // low stock items (same query as lowStock() above)
        $lowItems = $pdo->query(
            "SELECT p.id, p.name, p.supplier,
                COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END),0) AS stock,
                10 AS min_stock
             FROM products p LEFT JOIN stock_ledger sl ON sl.product_id = p.id
             WHERE p.approval_status='approved'
             GROUP BY p.id, p.name, p.supplier
             HAVING stock <= 10 ORDER BY stock ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $lowCount = count($lowItems);

        // count how many of the low-stock items are completely out
        $outCount = count(array_filter($lowItems, fn($p) => (int) $p['stock'] <= 0));

        // format the low stock list with status labels
        $lowProducts = [];
        foreach ($lowItems as $p) {
            $status = 'Low';
            if ((int) $p['stock'] === 0)
                $status = 'Out of Stock';
            elseif ((int) $p['stock'] <= floor((int) $p['min_stock'] / 3))
                $status = 'Critical';
            $lowProducts[] = [
                'id'        => (int) $p['id'],
                'name'      => $p['name'],
                'stock'     => (int) $p['stock'],
                'min_stock' => (int) $p['min_stock'],
                'status'    => $status,
                'supplier'  => $p['supplier'] ?: '—'
            ];
        }

        // top 5 most-ordered products to show as the "high demand" section of the summary
        $demandItems = $pdo->query(
            "SELECT p.id, p.name, COUNT(oi.id) as sales_count,
                COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END),0) AS stock
             FROM products p
             LEFT JOIN order_items oi ON oi.product_id = p.id
             LEFT JOIN stock_ledger sl ON sl.product_id = p.id
             WHERE p.approval_status='approved'
             GROUP BY p.id, p.name HAVING sales_count > 0
             ORDER BY sales_count DESC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        $demandProducts = [];
        foreach ($demandItems as $p) {
            $sc = (int) $p['sales_count'];
            $demandProducts[] = [
                'id'           => (int) $p['id'],
                'name'         => $p['name'],
                'sales_count'  => $sc,
                'demand_level' => ($sc >= 5) ? 'High' : (($sc >= 3) ? 'Medium' : 'Moderate'),
                'stock'        => (int) $p['stock']
            ];
        }

        // pre-populate restock candidates in case the user wants to act on something from the summary
        if (!empty($lowItems))
            $_SESSION['restock_candidates'] = $lowItems;

        return [
            'reply'               => "📋 **Inventory Summary**",
            'type'                => 'inventory_summary',
            'summary'             => [
                'total_products'    => $total,
                'total_stock'       => $totalStock,
                'low_stock_count'   => $lowCount,
                'out_of_stock_count'=> $outCount,
                'total_orders'      => $orders
            ],
            'low_stock_products'  => $lowProducts,
            'high_demand_products'=> $demandProducts,
            'suggestions'         => ['Low stock', 'High demand products', 'Show critical items only', 'Sort by lowest stock first', 'Out of stock']
        ];
    }

    private static function supplierDetails()
    {
        global $pdo;

        // group by supplier name so we get a summary per supplier rather than per product
        // LEFT JOIN suppliers to get their phone/email (if they exist in the suppliers table)
        $items = $pdo->query(
            "SELECT p.supplier, MAX(s.phone) as phone, MAX(s.email) as email, COUNT(DISTINCT p.id) as product_count,
                COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END),0) AS total_stock
             FROM products p
             LEFT JOIN suppliers s ON p.supplier = s.name
             LEFT JOIN stock_ledger sl ON sl.product_id = p.id
             WHERE p.approval_status='approved' AND p.supplier != ''
             GROUP BY p.supplier ORDER BY p.supplier"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'reply'       => "📦 No supplier information available for current products.",
                'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
            ];
        }

        $suppliers = [];
        foreach ($items as $s) {
            $suppliers[] = [
                'name'          => $s['supplier'],
                'phone'         => $s['phone'] ?: 'N/A',  // show N/A if no phone on record
                'email'         => $s['email'] ?: 'N/A',
                'product_count' => (int) $s['product_count'],
                'total_stock'   => (int) $s['total_stock']
            ];
        }

        return [
            'reply'       => "🏭 **Supplier Overview** — " . count($suppliers) . " supplier(s):",
            'type'        => 'supplier_info',
            'suppliers'   => $suppliers,
            'suggestions' => ['Low stock', 'High demand products', 'Inventory summary']
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Tries to match the user's message to one of the product names from the candidate list.
     * First tries a full name match, then falls back to matching individual words.
     * Used when the user picks a product to restock from a list the bot showed them.
     */
    private static function findProductMatch($msg, $candidates)
    {
        // try matching the full product name first
        foreach ($candidates as $p) {
            if (strpos($msg, strtolower($p['name'])) !== false) {
                return ['id' => (int) $p['id'], 'name' => $p['name'], 'qty' => 0];
            }
        }

        // if no full match, try matching any single word from the product name
        // only bother with words longer than 3 chars to avoid matching "the", "and", etc.
        foreach ($candidates as $p) {
            $words = explode(' ', strtolower($p['name']));
            foreach ($words as $word) {
                if (strlen($word) > 3 && strpos($msg, $word) !== false) {
                    return ['id' => (int) $p['id'], 'name' => $p['name'], 'qty' => 0];
                }
            }
        }
        return null; // no match found
    }

    /**
     * Builds a text summary of the current inventory state to send as context to Groq.
     * This is how the AI knows about our actual products, stock levels, and suppliers
     * without having direct database access.
     */
    private static function getInventoryContext()
    {
        global $pdo;

        // get all products with low or zero stock
        $lowStock = $pdo->query(
            "SELECT p.id, p.name, p.supplier,
                COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END),0)
              - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END),0) AS stock,
                10 AS min_stock
             FROM products p LEFT JOIN stock_ledger sl ON sl.product_id = p.id
             WHERE p.approval_status = 'approved'
             GROUP BY p.id, p.name, p.supplier HAVING stock <= 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        // count items that are completely out of stock
        $outCount = count(array_filter($lowStock, fn($p) => (int) $p['stock'] <= 0));

        // top 10 most-ordered products for demand context
        $demand = $pdo->query(
            "SELECT p.name, COUNT(oi.id) as sales_freq
             FROM products p
             LEFT JOIN order_items oi ON oi.product_id = p.id
             WHERE p.approval_status = 'approved'
             GROUP BY p.id, p.name ORDER BY sales_freq DESC LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        // build the context string that gets injected into the AI's system prompt
        $ctx  = "REAL-TIME INVENTORY DATA:\n";
        $ctx .= "- Out-of-Stock Items: {$outCount}\n";
        $ctx .= "- Low Stock Products:\n";
        foreach ($lowStock as $p) {
            $ctx .= "  * {$p['name']} (ID:{$p['id']}): Stock={$p['stock']}, Min={$p['min_stock']}, Supplier={$p['supplier']}\n";
        }
        $ctx .= "\nDEMAND TRENDS:\n";
        foreach ($demand as $d) {
            $ctx .= "  * {$d['name']}: {$d['sales_freq']} shipments\n";
        }

        // also include supplier contact info so the AI can suggest who to call
        $suppliers = $pdo->query("SELECT name, phone, email FROM suppliers")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($suppliers)) {
            $ctx .= "\nSUPPLIER CONTACTS:\n";
            foreach ($suppliers as $s) {
                $ctx .= "  * {$s['name']}: Phone={$s['phone']}, Email={$s['email']}\n";
            }
        }

        return $ctx;
    }

    /**
     * Sends the user's message + inventory context to the Groq API and returns the reply.
     * We use the GROQ_API_KEY and GROQ_MODEL constants loaded from openai.php.
     */
    private static function callGroq($userMessage, $inventoryContext)
    {
        $apiKey = GROQ_API_KEY;
        $model  = GROQ_MODEL;

        // if the key is missing we can't do anything - log it and bail
        if (empty($apiKey)) {
            self::logError("Groq API Key missing.");
            return null;
        }

        if (session_status() === PHP_SESSION_NONE) session_start();

        // personalize the AI's responses using the logged-in user's name and role
        $userName = $_SESSION['name']     ?? 'User';
        $userRole = $_SESSION['job_role'] ?: ($_SESSION['role'] ?? 'Staff');

        // build the messages array - system prompt first, then history, then the new message
        $messages = [
            [
                'role'    => 'system',
                'content' => "You are 'GroceryFlow AI', an advanced inventory management assistant.
You are currently talking to {$userName}, who is logged in as a(n) {$userRole}.
CONTEXT: {$inventoryContext}
RULES:
- Always show ALL matching products, never truncate.
- Use markdown formatting with bold, bullets, tables.
- After every response, suggest 3-5 smart follow-up questions.
- When showing product lists, include name, stock, and status.
- Be conversational, professional, and helpful. Address the user by name occasionally.
- Tailor your responses based on their role ({$userRole}). Admins/Supervisors can approve requests and manage users, while Staff can submit pending requests.
- Guide users toward restocking and inventory decisions.
- If user mentions a product name, suggest restocking it."
            ]
        ];

        // append the conversation history so the AI remembers what was said before
        if (isset($_SESSION['chat_history'])) {
            foreach ($_SESSION['chat_history'] as $h) {
                $messages[] = ['role' => $h['role'], 'content' => $h['content']];
            }
        }

        // finally, add the user's current message
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // set up the cURL request to Groq's OpenAI-compatible endpoint
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => 0.7 // a bit of creativity but not too random
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_SSL_VERIFYPEER => false // might want to turn this on in production
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // handle curl-level errors (network issues, timeouts, etc.)
        if (curl_errno($ch)) {
            self::logError("CURL: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        $result = json_decode($response, true);
        $reply  = null;

        // if the API returned 200, extract the reply text from the response
        if ($httpCode === 200) {
            if (isset($result['choices'][0]['message']['content'])) {
                $reply = $result['choices'][0]['message']['content'];
            }
        }

        if ($reply) {
            // extract any follow-up suggestions the AI wrote (lines ending in ?)
            $suggestions = self::extractSuggestions($reply);
            return ['reply' => $reply, 'suggestions' => $suggestions];
        } else {
            // something went wrong - log it and return a friendly error message
            $err = $result['error']['message'] ?? 'Unknown Error';
            self::logError("Groq ($httpCode): $err | Response: " . $response);

            // give specific messages for common API errors
            if ($httpCode === 401) return ['reply' => "Error: Invalid API Key."];
            if ($httpCode === 403) return ['reply' => "Error: Model Access Denied ($model)."];
            if ($httpCode === 404) return ['reply' => "Error: Model '$model' not found."];
            if ($httpCode === 429) return ['reply' => "Error: API rate limit exceeded."];
        }
        return null;
    }

    /**
     * Checks the AI's reply to see if it mentioned restocking a specific product.
     * If it did, we save that product to the session so the user can say "Proceed"
     * and we'll redirect them to the stock update page for that product.
     */
    private static function detectRestockSuggestion($reply)
    {
        global $pdo;
        $products = $pdo->query("SELECT id, name FROM products WHERE approval_status='approved'")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as $p) {
            // check if the AI's reply mentioned this product by name
            if (stripos($reply, $p['name']) !== false) {
                // and if it also mentioned a specific quantity to restock
                if (preg_match('/(?:restock|add|order)\s+(\d+)/i', $reply, $m)) {
                    $_SESSION['last_suggestion'] = ['id' => $p['id'], 'name' => $p['name'], 'qty' => (int) $m[1]];
                    return; // stop after the first match
                }
            }
        }
    }

    /**
     * Pulls out follow-up question suggestions from the AI's reply.
     * The AI is prompted to write suggestions as bullet lines ending in "?",
     * so we parse those out and remove them from the main reply text.
     * 
     * This way the frontend can show them as clickable buttons rather than
     * just leaving them in the middle of the text.
     */
    private static function extractSuggestions(&$reply)
    {
        $suggestions = [];
        $lines       = explode("\n", $reply);
        $newLines    = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            // look for lines like "* Should I restock Product X?"
            if (preg_match('/^\* (.*?\?)/', $trimmed, $m)) {
                $suggestions[] = rtrim($m[1], '?'); // strip the trailing ? from the button label
            } else {
                $newLines[] = $line; // keep non-suggestion lines in the reply
            }
        }

        // if the AI didn't include any suggestions, use these defaults
        if (empty($suggestions)) {
            $suggestions = ['Low stock', 'High demand products', 'Inventory summary'];
        }

        // if there's a pending restock suggestion from a previous message, add "Proceed" as a button
        if (isset($_SESSION['last_suggestion'])) {
            array_unshift($suggestions, 'Proceed');
        }

        // update the reply in-place to remove the suggestion lines (passed by reference)
        $reply = trim(implode("\n", $newLines));

        // cap at 5 suggestions and remove duplicates
        return array_unique(array_slice($suggestions, 0, 5));
    }

    /**
     * Writes error messages to a log file in /logs/chat_error.log.
     * Creates the logs directory automatically if it doesn't exist yet.
     */
    private static function logError($message)
    {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir))
            mkdir($dir, 0777, true);
        file_put_contents($dir . '/chat_error.log', "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
    }
}
