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

function getAllProducts(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getProductById(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function createOrder(PDO $pdo, $userId, $name, $phone, $address, $notes, $totalAmount) {
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, name, phone, address, notes, total_amount, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$userId, $name, $phone, $address, $notes, $totalAmount]);
    return $pdo->lastInsertId();
}

function createOrderItem(PDO $pdo, $orderId, $productId, $productName, $productPrice, $quantity) {
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$orderId, $productId, $productName, $productPrice, $quantity]);
}

function saveTransaction(PDO $pdo, $orderId, $checkoutRequestId, $merchantRequestId, $phoneNumber, $amount) {
    $stmt = $pdo->prepare("INSERT INTO transactions (order_id, checkout_request_id, merchant_request_id, phone_number, amount, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$orderId, $checkoutRequestId, $merchantRequestId, $phoneNumber, $amount]);
}

function updateTransactionStatus(PDO $pdo, $checkoutRequestId, $status, $mpesaReceiptNumber = null) {
    $stmt = $pdo->prepare("UPDATE transactions SET status = ?, mpesa_receipt_number = ? WHERE checkout_request_id = ?");
    $stmt->execute([$status, $mpesaReceiptNumber, $checkoutRequestId]);
}

function updateOrderStatus(PDO $pdo, $orderId, $status, $mpesaReceiptNumber = null) {
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, mpesa_receipt = ? WHERE id = ?");
    $stmt->execute([$status, $mpesaReceiptNumber, $orderId]);
}

function getOrderById(PDO $pdo, $orderId) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetch();
}

function getUserById(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function updateWalletBalance(PDO $pdo, $userId, $newBalance) {
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt->execute([$newBalance, $userId]);
}

function logReferralEarning(PDO $pdo, $orderId, $fromUserId, $toUserId, $amount, $level) {
    $stmt = $pdo->prepare("INSERT INTO referral_earnings (order_id, from_user_id, to_user_id, amount, level) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$orderId, $fromUserId, $toUserId, $amount, $level]);
}

function setHasCompletedRefill(PDO $pdo, $userId, $flag) {
    $stmt = $pdo->prepare("UPDATE users SET has_completed_refill = ? WHERE id = ?");
    $stmt->execute([$flag, $userId]);
}
?>