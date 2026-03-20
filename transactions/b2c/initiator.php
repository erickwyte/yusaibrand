<?php
require_once '../db.php'; // Include database connection
require_once '../vendor/autoload.php'; // Include Composer autoloader

use Dotenv\Dotenv;

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log','logs/errors.log');
error_reporting(E_ALL);

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ );
$dotenv->load();

// Log directory
$logDir = __DIR__ . '/logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log the request
file_put_contents($logDir . 'b2c_initiator.log', date('Y-m-d H:i:s') . " - B2C Initiator Request: " . json_encode($_POST) . "\n", FILE_APPEND);

// Get credentials from .env (NOT hardcoded!)
$consumerKey = $_ENV['B2C_CONSUMER_KEY'] ?? '';
$consumerSecret = $_ENV['B2C_CONSUMER_SECRET'] ?? '';
$initiatorName = $_ENV['B2C_INITIATOR_NAME'] ?? '';
$partyA = $_ENV['B2C_SHORTCODE'] ?? '';
$initiatorPassword = $_ENV['B2C_INITIATOR_PASSWORD'] ?? '';
$certificatePath = $_ENV['B2C_CERTIFICATE_PATH'] ?? '';

// Validate credentials
if (empty($consumerKey) || empty($consumerSecret) || empty($initiatorName) || empty($partyA) || empty($initiatorPassword) || empty($certificatePath)) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Missing B2C credentials in .env\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'B2C credentials not configured']);
    exit;
}

// Get input from callback POST
$phone = $_POST['referrer_phone'] ?? null;
$amount = $_POST['referrer_amount'] ?? null;
$reason = $_POST['reason'] ?? 'Gas refill referral bonus';
$userId = $_POST['user_id'] ?? null;
$referrerId = $_POST['referrer_id'] ?? null;
$orderId = $_POST['order_id'] ?? null;
$rewardLevel = $_POST['reward_level'] ?? 1;

// Set appropriate reason based on reward level
if ($rewardLevel == 1) {
    $reason = 'Level 1 referral bonus';
} elseif ($rewardLevel == 2) {
    $reason = 'Level 2 referral bonus';
}

if (!$phone || !$amount) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Phone and Amount are required\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Phone and Amount are required']);
    exit;
}

// Convert phone number to 254 format if needed
function convertTo254Format($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (substr($phone, 0, 1) === '0') {
        return '254' . substr($phone, 1);
    }
    elseif (substr($phone, 0, 1) === '7' && strlen($phone) === 9) {
        return '254' . $phone;
    }
    elseif (substr($phone, 0, 3) === '254') {
        return $phone;
    }
    
    return $phone;
}




// Validate phone number format
if (!preg_match('/^2547[0-9]{8}$/', $phone)) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Invalid phone number format: $phone\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid phone number format']);
    exit;
}

// Validate amount
if (!is_numeric($amount) || $amount < 10 || $amount > 150000) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Invalid amount: $amount\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Amount must be between 10 and 150,000 KES']);
    exit;
}

// URLs
$access_token_url = $_ENV['MPESA_ACCESS_TOKEN_URL'] ?? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$b2c_url = $_ENV['MPESA_B2C_URL'] ?? 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';
$queueTimeoutUrl = $_ENV['B2C_TIMEOUT_URL'] ?? 'https://277756c627e8.ngrok-free.app/yusuf/transactions/b2c/b2c_timeout_url.php';
$resultUrl = $_ENV['B2C_RESULT_URL'] ?? 'https://277756c627e8.ngrok-free.app/yusuf/transactions/b2c/b2c_callback_url.php';

// Generate Security Credential
if (!file_exists($certificatePath)) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Certificate file not found: $certificatePath\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Certificate file not found']);
    exit;
}

$password = $initiatorPassword;
openssl_public_encrypt($password, $encrypted, file_get_contents($certificatePath));
$securityCredential = base64_encode($encrypted);

try {
    // Get Access Token
    $headers = ['Content-Type:application/json; charset=utf8'];
    $curl = curl_init($access_token_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ':' . $consumerSecret);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    file_put_contents($logDir . 'access_token.log', date('Y-m-d H:i:s') . " - Access Token Response: $response (HTTP $httpCode)\n", FILE_APPEND);
    
    if ($response === false) {
        throw new Exception("Access token request failed: $error");
    }
    
    $data = json_decode($response, true);
    $access_token = $data['access_token'] ?? null;
    
    if (!$access_token) {
        throw new Exception("Failed to retrieve access token");
    }

    // B2C Request
    $b2cHeader = ['Content-Type:application/json', 'Authorization:Bearer ' . $access_token];
    $curlPostData = [
        'InitiatorName' => $initiatorName,
        'SecurityCredential' => $securityCredential,
        'CommandID' => 'PromotionPayment',
        'Amount' => (int)$amount,
        'PartyA' => $partyA,
        'PartyB' => $phone,
        'Remarks' => $reason,
        'QueueTimeOutURL' => $queueTimeoutUrl,
        'ResultURL' => $resultUrl,
        'Occasion' => 'GasRefillReward'
    ];

    $ch = curl_init($b2c_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $b2cHeader);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curlPostData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    file_put_contents($logDir . 'b2c_api.log', date('Y-m-d H:i:s') . " - B2C Request: " . json_encode($curlPostData) . "\n", FILE_APPEND);
    file_put_contents($logDir . 'b2c_api.log', date('Y-m-d H:i:s') . " - B2C Response: $result (HTTP $httpCode)\n", FILE_APPEND);
    
    if ($result === false) {
        throw new Exception("B2C API request failed: $error");
    }
    
    $jsonResponse = json_decode($result, true);
    
    if (isset($jsonResponse['ResponseCode']) && $jsonResponse['ResponseCode'] == "0") {
        // Save to b2c_transactions table
        saveB2CTransaction($jsonResponse['ConversationID'], $jsonResponse['OriginatorConversationID'], $phone, $amount, 'pending', $userId, $orderId);
        
        file_put_contents($logDir . 'b2c_initiator.log', date('Y-m-d H:i:s') . " - B2C initiated successfully: ConversationID: " . $jsonResponse['ConversationID'] . "\n", FILE_APPEND);
        
        echo json_encode(['success' => true, 'conversation_id' => $jsonResponse['ConversationID']]);
        
    } else {
        $errorMsg = $jsonResponse['ResponseDescription'] ?? $jsonResponse['errorMessage'] ?? 'Unknown error';
        throw new Exception("B2C API error: $errorMsg");
    }

} catch (Exception $e) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - B2C Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
function saveB2CTransaction($conversationId, $originatorConversationId, $phone, $amount, $status, $userId = null, $orderId = null) {
    global $pdo;
    
    try {
        // Check if conversation ID already exists
        $checkStmt = $pdo->prepare("SELECT id FROM b2c_transactions WHERE conversation_id = ?");
        $checkStmt->execute([$conversationId]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " - Duplicate CID: $conversationId, updating instead\n", FILE_APPEND);
            
            $updateStmt = $pdo->prepare("UPDATE b2c_transactions SET status = ?, updated_at = NOW() WHERE conversation_id = ?");
            $updateStmt->execute([$status, $conversationId]);
            return $existing['id'];
        }
        
        // Insert new transaction
        $stmt = $pdo->prepare("INSERT INTO b2c_transactions (user_id, order_id, phone, amount, status, conversation_id, originator_conversation_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $orderId, $phone, $amount, $status, $conversationId, $originatorConversationId]);
        
        file_put_contents(__DIR__ . '/logs/debug.log', date('Y-m-d H:i:s') . " - Saved B2C: CID=$conversationId, Phone=$phone, Amount=$amount\n", FILE_APPEND);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/logs/errors.log', date('Y-m-d H:i:s') . " - Database error saving B2C transaction: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
// In your initiator.php, after saving the transaction:
$transactionId = saveB2CTransaction($jsonResponse['ConversationID'], $jsonResponse['OriginatorConversationID'], $phone, $amount, 'pending', $userId, $orderId);

if ($transactionId) {
    file_put_contents($logDir . 'b2c_initiator.log', date('Y-m-d H:i:s') . " - B2C transaction saved: ID=$transactionId, CID=" . $jsonResponse['ConversationID'] . "\n", FILE_APPEND);
} else {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - FAILED to save B2C transaction: CID=" . $jsonResponse['ConversationID'] . "\n", FILE_APPEND);
}

?>