<?php
// db.php
$host = 'localhost';
$db   = 'qkenmfnt_yusuf';
$user = 'qkenmfnt_yusuf';
$pass = ',p+$zuWw7juEIYb6';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Database connection failed: ' . $e->getMessage());
}

// ====== PRODUCT FUNCTIONS ======
function getAllProducts(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getProductById(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Get product from any table (regular or black market)
// Replace the getProductFromAnyTable function in db.php with this:
function getProductFromAnyTable(PDO $pdo, $id) {
    try {
        // Debug logging
        error_log("getProductFromAnyTable called with ID: $id");
        
        // Try regular products first
        $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ?");
        if (!$stmt) {
            error_log("Failed to prepare regular products query");
            return null;
        }
        
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $product['source'] = 'regular';
            error_log("Found product in regular table: " . json_encode($product));
            return $product;
        }
        
        error_log("Product not found in regular products table, checking black market...");
        
        // Try black market products
        $stmt = $pdo->prepare("SELECT id, name, price FROM black_market_products WHERE id = ? AND status = 'active'");
        if (!$stmt) {
            error_log("Failed to prepare black market products query");
            return null;
        }
        
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $product['source'] = 'black_market';
            error_log("Found product in black market table: " . json_encode($product));
            return $product;
        }
        
        error_log("Product not found in any table for ID: $id");
        return null;
        
    } catch (PDOException $e) {
        error_log("getProductFromAnyTable PDO error for ID $id: " . $e->getMessage());
        return null;
    } catch (Exception $e) {
        error_log("getProductFromAnyTable general error for ID $id: " . $e->getMessage());
        return null;
    }
}
// Get black market product specifically
function getBlackMarketProductById(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM black_market_products WHERE id = ? AND status = 'active'");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// ====== ORDER FUNCTIONS ======
function createOrder(PDO $pdo, $userId, $name, $phone, $address, $notes, $totalAmount) {
    // Check if created_at column exists in orders table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'created_at'");
    $stmt->execute();
    $hasCreatedAt = $stmt->fetch();
    
    if ($hasCreatedAt) {
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, name, phone, address, notes, total_amount, payment_status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, name, phone, address, notes, total_amount, payment_status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
    }
    $stmt->execute([$userId, $name, $phone, $address, $notes, $totalAmount]);
    return $pdo->lastInsertId();
}

// FIXED: Now includes source parameter without created_at
function createOrderItem(PDO $pdo, $orderId, $productId, $productName, $productPrice, $quantity, $source = 'regular') {
    // Check if created_at column exists in order_items table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM order_items LIKE 'created_at'");
    $stmt->execute();
    $hasCreatedAt = $stmt->fetch();
    
    if ($hasCreatedAt) {
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, source, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, source) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
    }
    
    if ($hasCreatedAt) {
        $stmt->execute([$orderId, $productId, $productName, $productPrice, $quantity, $source]);
    } else {
        $stmt->execute([$orderId, $productId, $productName, $productPrice, $quantity, $source]);
    }
    
    return $pdo->lastInsertId();
}

function getOrderItems(PDO $pdo, $orderId) {
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function getOrderById(PDO $pdo, $orderId) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetch();
}

function updateOrderStatus(PDO $pdo, $orderId, $status, $mpesaReceiptNumber = null) {
    if ($mpesaReceiptNumber) {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, mpesa_receipt = ? WHERE id = ?");
        $stmt->execute([$status, $mpesaReceiptNumber, $orderId]);
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $stmt->execute([$status, $orderId]);
    }
}

// ====== TRANSACTION FUNCTIONS ======
function saveTransaction(PDO $pdo, $orderId, $checkoutRequestId, $merchantRequestId, $phoneNumber, $amount, $userId = null) {
    // Check if transactions table has created_at and user_id columns
    $stmt = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'created_at'");
    $stmt->execute();
    $hasCreatedAt = $stmt->fetch();
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'user_id'");
    $stmt->execute();
    $hasUserId = $stmt->fetch();
    
    if ($hasUserId && $hasCreatedAt && $userId) {
        $stmt = $pdo->prepare("
            INSERT INTO transactions (order_id, checkout_request_id, merchant_request_id, phone_number, amount, user_id, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$orderId, $checkoutRequestId, $merchantRequestId, $phoneNumber, $amount, $userId]);
    } elseif ($hasCreatedAt) {
        $stmt = $pdo->prepare("
            INSERT INTO transactions (order_id, checkout_request_id, merchant_request_id, phone_number, amount, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$orderId, $checkoutRequestId, $merchantRequestId, $phoneNumber, $amount]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO transactions (order_id, checkout_request_id, merchant_request_id, phone_number, amount, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$orderId, $checkoutRequestId, $merchantRequestId, $phoneNumber, $amount]);
    }
    return $pdo->lastInsertId();
}

function updateTransactionStatus(PDO $pdo, $checkoutRequestId, $status, $mpesaReceiptNumber = null) {
    if ($mpesaReceiptNumber) {
        $stmt = $pdo->prepare("UPDATE transactions SET status = ?, mpesa_receipt_number = ? WHERE checkout_request_id = ?");
        $stmt->execute([$status, $mpesaReceiptNumber, $checkoutRequestId]);
    } else {
        $stmt = $pdo->prepare("UPDATE transactions SET status = ? WHERE checkout_request_id = ?");
        $stmt->execute([$status, $checkoutRequestId]);
    }
}

function getTransactionByCheckoutId(PDO $pdo, $checkoutRequestId) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE checkout_request_id = ?");
    $stmt->execute([$checkoutRequestId]);
    return $stmt->fetch();
}

// ====== USER FUNCTIONS ======
function getUserById(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function updateWalletBalance(PDO $pdo, $userId, $newBalance) {
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt->execute([$newBalance, $userId]);
}

// ====== REFERRAL FUNCTIONS ======
function logReferralEarning(PDO $pdo, $orderId, $fromUserId, $toUserId, $amount, $level) {
    $stmt = $pdo->prepare("
        INSERT INTO referral_earnings (order_id, from_user_id, to_user_id, amount, level) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$orderId, $fromUserId, $toUserId, $amount, $level]);
    return $pdo->lastInsertId();
}

function setHasCompletedRefill(PDO $pdo, $userId, $flag) {
    $stmt = $pdo->prepare("UPDATE users SET has_completed_refill = ? WHERE id = ?");
    $stmt->execute([$flag, $userId]);
}

// ====== B2C FUNCTIONS ======
function updateB2CTransaction($pdo, $b2cTransactionId, $conversationId, $originatorConversationId) {
    $stmt = $pdo->prepare("UPDATE b2c_transactions SET conversation_id = ?, originator_conversation_id = ? WHERE id = ?");
    $stmt->execute([$conversationId, $originatorConversationId, $b2cTransactionId]);
}

function updateB2CTransactionStatus($pdo, $conversationId, $status, $receipt = null) {
    if ($receipt) {
        $stmt = $pdo->prepare("UPDATE b2c_transactions SET status = ?, receipt = ? WHERE conversation_id = ?");
        $stmt->execute([$status, $receipt, $conversationId]);
    } else {
        $stmt = $pdo->prepare("UPDATE b2c_transactions SET status = ? WHERE conversation_id = ?");
        $stmt->execute([$status, $conversationId]);
    }
    return $stmt->rowCount();
}

function isAdmin($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user && $user['role'] === 'admin';
}

function createMissingB2CTransaction($conversationId, $resultCode, $receipt, $amount, $phone) {
    global $pdo;
    
    try {
        $status = ($resultCode == 0) ? 'completed' : 'failed';
        
        $stmt = $pdo->prepare("
            INSERT INTO b2c_transactions (phone, amount, status, conversation_id, receipt) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$phone, $amount, $status, $conversationId, $receipt]);
        
        file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " - Created missing B2C transaction: CID=$conversationId\n", FILE_APPEND);
        
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/logs/errors.log', date('Y-m-d H:i:s') . " - Error creating missing transaction: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// ====== BLACK MARKET FUNCTIONS ======
function getAllBlackMarketProducts(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT bmp.*, 
               (SELECT image_url FROM product_images 
                WHERE product_id = bmp.id AND is_primary = 1 
                LIMIT 1) as primary_image
        FROM black_market_products bmp
        WHERE bmp.status = 'active'
        ORDER BY bmp.featured DESC, bmp.created_at DESC
    ");
    return $stmt->fetchAll();
}

function getFeaturedBlackMarketProducts(PDO $pdo, $limit = 8) {
    $stmt = $pdo->prepare("
        SELECT bmp.*, 
               (SELECT image_url FROM product_images 
                WHERE product_id = bmp.id AND is_primary = 1 
                LIMIT 1) as primary_image
        FROM black_market_products bmp
        WHERE bmp.status = 'active' AND bmp.featured = 1
        ORDER BY bmp.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getBlackMarketCategories(PDO $pdo) {
    $stmt = $pdo->query("
        SELECT * FROM black_market_categories 
        WHERE is_active = 1 
        ORDER BY display_order, name
    ");
    return $stmt->fetchAll();
}

// ====== CART HELPER FUNCTIONS ======
function validateCartItems(PDO $pdo, $cartItems) {
    $validatedItems = [];
    $total = 0;
    
    foreach ($cartItems as $item) {
        $product = getProductFromAnyTable($pdo, $item['id']);
        if (!$product) {
            throw new Exception("Product not found: " . $item['name']);
        }
        
        // Check price matches (allow small floating point differences)
        if (abs((float)$product['price'] - (float)$item['price']) > 0.01) {
            throw new Exception("Price mismatch for product: " . $item['name']);
        }
        
        // Add source to item
        $item['source'] = $product['source'];
        $validatedItems[] = $item;
        $total += $item['quantity'] * $item['price'];
    }
    
    return [
        'items' => $validatedItems,
        'total' => $total
    ];
}

// ====== SIMPLIFIED DATABASE CHECK ======
function checkDatabaseStructure(PDO $pdo) {
    try {
        // Check order_items table structure
        $stmt = $pdo->query("SHOW COLUMNS FROM order_items");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('source', $columns)) {
            try {
                $pdo->exec("ALTER TABLE order_items ADD COLUMN source VARCHAR(20) DEFAULT 'regular' AFTER quantity");
                error_log("Added source column to order_items table");
            } catch (PDOException $e) {
                error_log("Could not add source column: " . $e->getMessage());
            }
        }
        
    } catch (PDOException $e) {
        error_log("Database structure check error: " . $e->getMessage());
    }
}

// Run database check
checkDatabaseStructure($pdo);
?>