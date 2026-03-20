<?php
ob_start();
session_start();

// Enable error display temporarily for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

// ====== Bootstrap / Env ======
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);

// Simple trace id for correlating logs across steps
$traceId = bin2hex(random_bytes(8));
function log_line($file, $msg) {
    global $traceId;
    file_put_contents(__DIR__ . "/logs/$file", date('Y-m-d H:i:s') . " [$traceId] $msg\n", FILE_APPEND);
}

// ====== Guard rails ======
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_line('debug.log', "Invalid request: user_id=" . ($_SESSION['user_id'] ?? 'none') . ", method=" . $_SERVER['REQUEST_METHOD']);
    header('Location: checkout.php?error=' . urlencode('Invalid request.') . '&t=' . time());
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    log_line('debug.log', "CSRF mismatch: POST=" . ($_POST['csrf_token'] ?? 'none') . ", SESSION=" . ($_SESSION['csrf_token'] ?? 'none'));
    header('Location: checkout.php?error=' . urlencode('Invalid CSRF token.') . '&t=' . time());
    exit;
}

// ====== Inputs ======
$userId  = (int) $_SESSION['user_id'];
$name    = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$phone   = preg_replace('/\s+/', '', (string)($_POST['phone'] ?? ''));
$address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$notes   = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$amount  = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$cart    = json_decode($_POST['cart'] ?? '[]', true);

log_line('debug.log', "=== STARTING STK PUSH PROCESS ===");
log_line('debug.log', "User ID: $userId, Name: $name, Phone: $phone");
log_line('debug.log', "Cart received: " . json_encode($cart));

// Phone: allow any Kenyan MSISDN in international format (11 digits after 254)
if (!preg_match('/^254\d{9}$/', $phone)) {
    log_line('debug.log', "Phone invalid: $phone");
    header('Location: checkout.php?error=' . urlencode('Invalid phone number.') . '&t=' . time());
    exit;
}

if (!$name || !$address || !$amount || $amount <= 0 || !is_array($cart) || empty($cart)) {
    log_line('debug.log', "Invalid inputs: name=$name, address=$address, amount=$amount, cart empty=" . empty($cart));
    header('Location: checkout.php?error=' . urlencode('Invalid input data or empty cart.') . '&t=' . time());
    exit;
}

// ====== Server-side cart validation for both product types ======
$total = 0.0;
$cartWithSource = []; // Store cart items with source for later use

log_line('debug.log', "Validating " . count($cart) . " cart items");
foreach ($cart as $index => $item) {
    $pid = (int)($item['id'] ?? 0);
    $qty = (int)($item['quantity'] ?? 0);
    $price = (float)($item['price'] ?? 0);
    $productName = (string)($item['name'] ?? 'Unknown Product');

    if ($pid <= 0 || $qty <= 0 || $price <= 0) {
        log_line('debug.log', "Bad cart line at index $index: " . json_encode($item));
        header('Location: checkout.php?error=' . urlencode('Invalid cart item: ' . $productName) . '&t=' . time());
        exit;
    }

    // Get product from either table
    $product = getProductFromAnyTable($pdo, $pid);
    if (!$product) {
        log_line('debug.log', "Product not found in any table id=$pid, name=$productName");
        header('Location: checkout.php?error=' . urlencode('Product not found: ' . $productName) . '&t=' . time());
        exit;
    }
    
    // Allow small floating point differences (0.01 tolerance)
    if (abs((float)$product['price'] - $price) > 0.01) {
        log_line('debug.log', "Product price mismatch id=$pid, posted_price=$price, db_price=" . ($product['price'] ?? 'N/A') . ", name=$productName");
        header('Location: checkout.php?error=' . urlencode('Product price mismatch: ' . $productName) . '&t=' . time());
        exit;
    }
    
    // Store item with source for order creation
    $item['source'] = $product['source'] ?? 'regular';
    $cartWithSource[] = $item;
    
    $total += $qty * $price;
    log_line('debug.log', "Item $index validated: {$item['name']} - Price: $price, Qty: $qty, Source: {$item['source']}");
}

if (abs($total - $amount) > 0.01) {
    log_line('debug.log', "Cart total mismatch: calc=$total, posted=$amount");
    header('Location: checkout.php?error=' . urlencode('Cart total mismatch. Calculated: ' . $total . ', Submitted: ' . $amount) . '&t=' . time());
    exit;
}

log_line('debug.log', "Cart validation successful. Total: $total, Items: " . count($cartWithSource));

// ====== Env config ======
$consumerKey    = $_ENV['CONSUMER_KEY']    ?? '';
$consumerSecret = $_ENV['CONSUMER_SECRET'] ?? '';
$shortCode      = $_ENV['SHORT_CODE']      ?? ''; // Till number
$passkey        = $_ENV['PASSKEY']         ?? '';
$callbackUrl    = $_ENV['STK_CALLBACK_URL'] ?? 'https://yusaibrand.co.ke/transactions/callback.php';
$baseUrl        = 'https://api.safaricom.co.ke'; // Production

foreach (['CONSUMER_KEY','CONSUMER_SECRET','SHORT_CODE','PASSKEY','STK_CALLBACK_URL'] as $k) {
    if (empty($_ENV[$k])) {
        log_line('errors.log', "Missing env: $k");
        header('Location: checkout.php?error=' . urlencode('Payment configuration error.') . '&t=' . time());
        exit;
    }
}

// ====== Create order (pending) + items ======
log_line('debug.log', "=== STARTING ORDER CREATION ===");
try {
    $pdo->beginTransaction();

    // Optional idempotency guard
    $reuseStmt = $pdo->prepare("
        SELECT id FROM orders 
        WHERE user_id = ? AND payment_status = 'pending' 
          AND ABS(total_amount - ?) < 0.01
        ORDER BY id DESC LIMIT 1
    ");
    $reuseStmt->execute([$userId, $amount]);
    $existingOrderId = (int)($reuseStmt->fetchColumn() ?: 0);
    log_line('debug.log', "Existing pending order check: $existingOrderId");

    if ($existingOrderId > 0) {
        $orderId = $existingOrderId;
        log_line('debug.log', "Reusing pending order $orderId");
        // Clean old items and re-add
        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
        log_line('debug.log', "Cleared old items from order $orderId");
    } else {
        log_line('debug.log', "Creating new order with amount: $amount");
        try {
            $orderId = createOrder($pdo, $userId, $name, $phone, $address, $notes, $amount);
            log_line('debug.log', "New order created with ID: $orderId");
        } catch (Exception $e) {
            log_line('errors.log', "createOrder function failed: " . $e->getMessage());
            throw $e;
        }
    }

    // Create order items with source information
    log_line('debug.log', "Creating " . count($cartWithSource) . " order items");
    $itemCount = 0;
    foreach ($cartWithSource as $item) {
        $itemCount++;
        log_line('debug.log', "Creating item $itemCount: ID={$item['id']}, Name={$item['name']}, Price={$item['price']}, Qty={$item['quantity']}, Source={$item['source']}");
        
        try {
            createOrderItem(
                $pdo,
                $orderId,
                (int)$item['id'],
                (string)$item['name'],
                (float)$item['price'],
                (int)$item['quantity'],
                $item['source'] ?? 'regular'
            );
            log_line('debug.log', "Item $itemCount created successfully");
        } catch (Exception $e) {
            log_line('errors.log', "Failed to create order item $itemCount: " . $e->getMessage());
            throw $e;
        }
    }

    $pdo->commit();
    log_line('debug.log', "=== ORDER $orderId SUCCESSFULLY CREATED ===");
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        log_line('debug.log', "Transaction rolled back due to error");
    }
    
    // Log detailed error
    log_line('errors.log', "ORDER CREATION FAILED:");
    log_line('errors.log', "Error: " . $e->getMessage());
    log_line('errors.log', "File: " . $e->getFile() . ":" . $e->getLine());
    log_line('errors.log', "Trace: " . $e->getTraceAsString());
    log_line('errors.log', "User ID: $userId");
    log_line('errors.log', "Cart data: " . json_encode($cartWithSource));
    
    header('Location: checkout.php?error=' . urlencode('Failed to process order: ' . $e->getMessage()) . '&t=' . time());
    exit;
}

// ====== HTTP client ======
$http = new Client([
    'verify'  => true,   // IMPORTANT in prod
    'timeout' => 20,     // keep it tight
]);

// ====== Helpers with retries ======
function with_retry(callable $fn, $max=3, $baseSleep=1) {
    $attempt = 0;
    start:
    try { return $fn($attempt); }
    catch (Throwable $e) {
        $attempt++;
        if ($attempt >= $max) throw $e;
        sleep($baseSleep * (2 ** ($attempt - 1))); // 1s,2s,4s
        goto start;
    }
}

function getAccessToken(Client $http, $consumerKey, $consumerSecret, $baseUrl) {
    $auth = base64_encode("$consumerKey:$consumerSecret");
    return with_retry(function($attempt) use ($http, $auth, $baseUrl) {
        $res = $http->get("$baseUrl/oauth/v1/generate?grant_type=client_credentials", [
            'headers' => [
                'Authorization' => "Basic $auth",
                'Cache-Control' => 'no-cache',
            ],
        ]);
        $data = json_decode((string)$res->getBody(), true);
        if (empty($data['access_token'])) {
            throw new RuntimeException('No access_token in response');
        }
        return $data['access_token'];
    });
}

function postStkPush(Client $http, $baseUrl, $accessToken, array $payload) {
    return with_retry(function($attempt) use ($http, $baseUrl, $accessToken, $payload) {
        $res = $http->post("$baseUrl/mpesa/stkpush/v1/processrequest", [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Content-Type'  => 'application/json',
                'Cache-Control' => 'no-cache',
            ],
            'json' => $payload,
        ]);
        $data = json_decode((string)$res->getBody(), true);
        if (!isset($data['ResponseCode']) || $data['ResponseCode'] !== '0') {
            $msg = $data['errorMessage'] ?? $data['ResponseDescription'] ?? 'STK Push non-zero response';
            throw new RuntimeException($msg);
        }
        return $data;
    });
}

// ====== Build STK payload ======
log_line('debug.log', "=== STARTING M-PESA STK PUSH ===");
log_line('debug.log', "Order ID: $orderId, Amount: $amount, Phone: $phone");

try {
    $accessToken = getAccessToken($http, $consumerKey, $consumerSecret, $baseUrl);
    log_line('debug.log', "Access token obtained successfully");
} catch (Throwable $e) {
    log_line('errors.log', "STK Auth Error: " . $e->getMessage());
    // Leave order pending so customer can retry
    header('Location: checkout.php?error=' . urlencode('Could not reach M-Pesa. Please try again.') . '&t=' . time());
    exit;
}

$timestamp = date('YmdHis');
$password  = base64_encode($shortCode . $passkey . $timestamp);

// ====== CRITICAL CHANGE FOR TILL NUMBER ======
$payload = [
    'BusinessShortCode' => $shortCode,
    'Password'          => $password,
    'Timestamp'         => $timestamp,
    'TransactionType'   => 'CustomerBuyGoodsOnline',   // ✅ CHANGED: For Till numbers
    'Amount'            => (int)round($amount),
    'PartyA'            => $phone,
    'PartyB'            => 6311802,
    'PhoneNumber'       => $phone,
    'CallBackURL'       => $callbackUrl,
    'AccountReference'  => 'YUSAI' . $orderId,
    'TransactionDesc'   => 'Payment for YUSAI order #' . $orderId,
];

log_line('debug.log', "STK Payload prepared for amount: " . (int)round($amount));

// ====== Prevent duplicate STK for same order ======
$dupStmt = $pdo->prepare("SELECT checkout_request_id FROM transactions WHERE order_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
$dupStmt->execute([$orderId]);
$existingCheckout = $dupStmt->fetchColumn();
if ($existingCheckout) {
    log_line('debug.log', "Duplicate STK avoided. Order $orderId already has pending CheckoutRequestID=$existingCheckout");
    header('Location: checkout.php?success=' . urlencode('Payment request already sent. Please complete on your phone. CheckoutRequestID: ' . $existingCheckout) . '&t=' . time());
    exit;
}

// ====== Call STK push with retries ======
try {
    log_line('debug.log', "Sending STK push request...");
    $result = postStkPush($http, $baseUrl, $accessToken, $payload);
    log_line('stk_push.log', "STK Push Response (order:$orderId): " . json_encode($result));

    $checkoutRequestId  = $result['CheckoutRequestID']      ?? null;
    $merchantRequestId  = $result['MerchantRequestID']      ?? null;

    if (!$checkoutRequestId || !$merchantRequestId) {
        throw new RuntimeException('Missing CheckoutRequestID or MerchantRequestID');
    }

    log_line('debug.log', "STK push successful. CheckoutRequestID: $checkoutRequestId, MerchantRequestID: $merchantRequestId");

    // Save transaction as pending
    try {
        $pdo->beginTransaction();
        saveTransaction($pdo, $orderId, $checkoutRequestId, $merchantRequestId, $phone, $amount, $userId);
        $pdo->commit();
        log_line('debug.log', "Transaction saved successfully");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        log_line('errors.log', "Transaction save error: " . $e->getMessage());
        header('Location: checkout.php?error=' . urlencode('Failed to save transaction.') . '&t=' . time());
        exit;
    }

    // ✅ FIXED: Include CheckoutRequestID in the success message
    log_line('debug.log', "=== STK PUSH COMPLETED SUCCESSFULLY ===");
    header('Location: checkout.php?success=' . urlencode('Payment request sent. Check your phone and enter your M-Pesa PIN. CheckoutRequestID: ' . $checkoutRequestId) . '&t=' . time());
    exit;

} catch (Throwable $e) {
    // Do NOT mark order failed here; keep it pending so the user can retry
    log_line('errors.log', "STK Push Error (order:$orderId): " . $e->getMessage());
    log_line('errors.log', "STK Push Error Trace: " . $e->getTraceAsString());
    header('Location: checkout.php?error=' . urlencode('Failed to initiate payment. Please try again.') . '&t=' . time());
    exit;
}
ob_end_flush();