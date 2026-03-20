<?php
require_once '../db.php'; // DB connection

// Error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/errors.log');
error_reporting(E_ALL);

// Log directory
$logDir = __DIR__ . '/logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log raw callback
$rawInput = file_get_contents('php://input');
file_put_contents(
    $logDir . 'b2c_callback.log',
    date('Y-m-d H:i:s') . " - RAW: " . $rawInput . PHP_EOL,
    FILE_APPEND
);

// Empty callback
if (empty($rawInput)) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Empty B2C callback\n", FILE_APPEND);
    http_response_code(400);
    die(json_encode(["ResultCode" => 1, "ResultDesc" => "Empty callback"]));
}

// Decode Safaricom JSON
$data = json_decode($rawInput, true);
if (!$data || !isset($data['Result'])) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Invalid JSON payload\n", FILE_APPEND);
    http_response_code(400);
    die(json_encode(["ResultCode" => 1, "ResultDesc" => "Invalid payload"]));
}

$resultCode      = $data['Result']['ResultCode'] ?? null;
$conversationId  = $data['Result']['ConversationID'] ?? null;
$resultDesc      = $data['Result']['ResultDesc'] ?? null;
$transactionId   = $data['Result']['TransactionID'] ?? null;

$amount = null;
$phone  = null;
$receipt = null;

// Parse ResultParameters safely
if (isset($data['Result']['ResultParameters']['ResultParameter'])) {
    foreach ($data['Result']['ResultParameters']['ResultParameter'] as $param) {
        if ($param['Key'] === "TransactionAmount") $amount = $param['Value'];
        if ($param['Key'] === "ReceiverPartyPublicName") {
            $parts = explode(' - ', $param['Value']);
            $phone = $parts[0] ?? $param['Value']; // Extract just the phone number
        }
        if ($param['Key'] === "TransactionReceipt") $receipt = $param['Value'];
    }
}

file_put_contents(
    $logDir . 'b2c_callback.log',
    date('Y-m-d H:i:s') . " - Parsed: CID=$conversationId, Code=$resultCode, Amount=$amount, Phone=$phone, Receipt=$receipt\n",
    FILE_APPEND
);

// Function to create missing B2C transaction
function createMissingB2CTransaction($conversationId, $resultCode, $receipt, $amount, $phone) {
    global $pdo, $logDir;
    
    try {
        $status = ($resultCode == 0) ? 'completed' : 'failed';
        
        $stmt = $pdo->prepare("INSERT INTO b2c_transactions (phone, amount, status, conversation_id, receipt, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$phone, $amount, $status, $conversationId, $receipt]);
        
        file_put_contents($logDir . 'b2c_callback.log', date('Y-m-d H:i:s') . " - Created missing B2C transaction: CID=$conversationId\n", FILE_APPEND);
        
    } catch (PDOException $e) {
        file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Error creating missing transaction: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

try {
    if (!empty($conversationId)) {
        // RETRY MECHANISM: Wait for transaction to appear in database
        $maxRetries = 5; // Increased to 5 retries
        $retryCount = 0;
        $transactionFound = false;
        
        while ($retryCount < $maxRetries && !$transactionFound) {
            // Check if transaction exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM b2c_transactions WHERE conversation_id = ?");
            $checkStmt->execute([$conversationId]);
            $exists = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exists['count'] > 0) {
                $transactionFound = true;
                break;
            }
            
            $retryCount++;
            if ($retryCount < $maxRetries) {
                sleep(1); // Wait 1 second before retrying
            }
        }
        
        if (!$transactionFound) {
            file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Transaction not found after $maxRetries retries: CID=$conversationId\n", FILE_APPEND);
            
            // Create the missing transaction record
            createMissingB2CTransaction($conversationId, $resultCode, $receipt, $amount, $phone);
            
            // Even if we created it, we won't process further since it was missing initially
            http_response_code(200);
            echo json_encode(["ResultCode" => 0, "ResultDesc" => "Callback received (transaction created)"]);
            exit;
        }
        
        // TRANSACTION PROCESSING - only reached if transaction was found
        $pdo->beginTransaction();

        if ($resultCode == 0) {
            // ✅ Successful B2C transaction
            $status = 'completed';

            $stmt = $pdo->prepare("
                UPDATE b2c_transactions 
                SET status = ?, receipt = ?, updated_at = NOW() 
                WHERE conversation_id = ?
            ");
            $stmt->execute([$status, $receipt, $conversationId]);

            $rowsUpdated = $stmt->rowCount();

            file_put_contents(
                $logDir . 'b2c_callback.log',
                date('Y-m-d H:i:s') . " - SUCCESS: $rowsUpdated rows updated (CID: $conversationId, Receipt: $receipt)\n",
                FILE_APPEND
            );

            if ($rowsUpdated > 0) {
                // Get transaction info
                $txStmt = $pdo->prepare("SELECT id, user_id, amount, order_id FROM b2c_transactions WHERE conversation_id = ?");
                $txStmt->execute([$conversationId]);
                $transaction = $txStmt->fetch(PDO::FETCH_ASSOC);

                if ($transaction && !empty($transaction['user_id'])) {
                    $userId = $transaction['user_id'];
                    $txAmount = $transaction['amount'];

                    // Check if this transaction is linked to a reward
                    $rewardStmt = $pdo->prepare("SELECT id FROM rewards WHERE referrer_id = ? AND order_id = ? LIMIT 1");
                    $rewardStmt->execute([$userId, $transaction['order_id']]);
                    $reward = $rewardStmt->fetch(PDO::FETCH_ASSOC);

                    if ($reward) {
                        // Update wallet balance
                        $updateStmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                        $updateStmt->execute([$txAmount, $userId]);

                        file_put_contents(
                            $logDir . 'b2c_callback.log',
                            date('Y-m-d H:i:s') . " - Wallet credited: +$txAmount KES to user $userId\n",
                            FILE_APPEND
                        );
                    }
                }
            }

        } else {
            // ❌ Failed B2C transaction
            $status = 'failed';

            $stmt = $pdo->prepare("
                UPDATE b2c_transactions 
                SET status = ?, updated_at = NOW() 
                WHERE conversation_id = ?
            ");
            $stmt->execute([$status, $conversationId]);

            file_put_contents(
                $logDir . 'b2c_callback.log',
                date('Y-m-d H:i:s') . " - FAILED: CID=$conversationId, Reason=$resultDesc\n",
                FILE_APPEND
            );
        }

        $pdo->commit();

    } else {
        file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Missing ConversationID in callback\n", FILE_APPEND);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - DB error: " . $e->getMessage() . "\n", FILE_APPEND);
}

// ✅ Always return success to Safaricom
http_response_code(200);
echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "Callback received successfully"
]);