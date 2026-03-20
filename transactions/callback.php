<?php
require_once 'db.php';

// Set proper headers
header('Content-Type: application/json');

// Log raw input
$logDir = 'logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

$rawInput = file_get_contents('php://input');
file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Raw Input: " . $rawInput . "\n", FILE_APPEND);

if (empty($rawInput)) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Empty callback input received\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Empty callback data']);
    exit;
}

$callbackData = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - JSON decode error: " . json_last_error_msg() . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON']);
    exit;
}

file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Parsed Data: " . json_encode($callbackData, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

// ✅ Always respond immediately to Safaricom to stop retries
http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
flush(); // push response to client quickly

// Continue heavy logic AFTER responding
if (isset($callbackData['Body']['stkCallback'])) {
    processSTKCallback($callbackData['Body']['stkCallback']);
} else {
    file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Not an STK callback, ignoring\n", FILE_APPEND);
}

function processSTKCallback($stkCallback) {
    global $pdo;
    $logDir = 'logs/';
    
    $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? '';
    $resultCode = $stkCallback['ResultCode'] ?? '';
    $resultDesc = $stkCallback['ResultDesc'] ?? '';

    file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Processing STK: $checkoutRequestId, Result: $resultCode\n", FILE_APPEND);

    if (empty($checkoutRequestId)) {
        file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Missing CheckoutRequestID\n", FILE_APPEND);
        return;
    }

    // Lock mechanism to prevent duplicate processing
    $lockDir = 'locks/';
    if (!file_exists($lockDir)) {
        mkdir($lockDir, 0755, true);
    }
    
    $lockFile = $lockDir . $checkoutRequestId . '.lock';
    if (file_exists($lockFile)) {
        file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Already processing: $checkoutRequestId\n", FILE_APPEND);
        return;
    }
    
    file_put_contents($lockFile, getmypid());
    register_shutdown_function(function() use ($lockFile) {
        if (file_exists($lockFile)) unlink($lockFile);
    });

    try {
        $pdo->beginTransaction();

        // Get transaction details
        $stmt = $pdo->prepare("
            SELECT t.*, o.user_id AS order_user_id 
            FROM transactions t
            JOIN orders o ON t.order_id = o.id
            WHERE t.checkout_request_id = ?
            LIMIT 1
        ");
        $stmt->execute([$checkoutRequestId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Transaction not found: $checkoutRequestId\n", FILE_APPEND);
            $pdo->rollBack();
            return;
        }

        $orderId = $transaction['order_id'];
        $userId  = $transaction['user_id'] ?: $transaction['order_user_id'];

        file_put_contents($logDir . 'debug.log', date('Y-m-d H:i:s') . " - Order ID: $orderId, User ID: $userId\n", FILE_APPEND);

        if ($resultCode == 0) {
            // SUCCESSFUL PAYMENT
            $metadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
            $mpesaReceiptNumber = '';
            $amount = 0;
            $phoneNumber = '';

            foreach ($metadata as $item) {
                switch ($item['Name']) {
                    case 'MpesaReceiptNumber':
                        $mpesaReceiptNumber = $item['Value'] ?? '';
                        break;
                    case 'Amount':
                        $amount = $item['Value'] ?? 0;
                        break;
                    case 'PhoneNumber':
                        $phoneNumber = $item['Value'] ?? '';
                        break;
                }
            }

            // Update database using functions from db.php
            updateTransactionStatus($pdo, $checkoutRequestId, 'completed', $mpesaReceiptNumber);
            updateOrderStatus($pdo, $orderId, 'paid', $mpesaReceiptNumber);

            file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Payment successful: Order $orderId, Receipt: $mpesaReceiptNumber\n", FILE_APPEND);

            // Check rewards
            checkForGasRefillRewards($userId, $orderId, $amount);

        } else {
            // FAILED PAYMENT
            updateTransactionStatus($pdo, $checkoutRequestId, 'failed');
            updateOrderStatus($pdo, $orderId, 'failed');
            file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Payment failed: Order $orderId, Reason: $resultDesc\n", FILE_APPEND);
        }

        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $errorMsg = $e->getMessage();
        file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Database error: " . $errorMsg . "\n", FILE_APPEND);
    }
}

function checkForGasRefillRewards($userId, $orderId, $amount) {
    global $pdo;
    $logDir = 'logs/';
    
    try {
        // Check if order contains gas refill products - FIXED: Use consistent product type
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as refill_count 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ? AND p.type = 'Gas Refill' 
        ");
        $stmt->execute([$orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['refill_count'] == 0) {
            file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - No gas refill products in order $orderId\n", FILE_APPEND);
            return false;
        }

        // Check if this is user's first gas refill purchase
        $firstStmt = $pdo->prepare("
            SELECT COUNT(*) as previous_refills 
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            JOIN products p ON oi.product_id = p.id 
            WHERE o.user_id = ? AND p.type = 'Gas Refill' AND o.payment_status = 'paid' AND o.id != ?
        ");
        $firstStmt->execute([$userId, $orderId]);
        $firstResult = $firstStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($firstResult['previous_refills'] > 0) {
            file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Not first gas refill for user $userId\n", FILE_APPEND);
            return false;
        }

        // Get user details
        $user = getUserById($pdo, $userId);
        
        if (!$user || empty($user['referrer_id'])) {
            file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - User $userId has no referrer\n", FILE_APPEND);
            return false;
        }

        $referrerId = $user['referrer_id'];
        
        // Check if referrer was already rewarded for this user
        $rewardCheck = $pdo->prepare("
            SELECT COUNT(*) as already_rewarded 
            FROM rewards 
            WHERE referrer_id = ? AND referred_user_id = ? AND reward_type = 'gas_refill_first'
        ");
        $rewardCheck->execute([$referrerId, $userId]);
        $rewardResult = $rewardCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($rewardResult['already_rewarded'] > 0) {
            file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Referrer $referrerId already rewarded for user $userId\n", FILE_APPEND);
            return false;
        }

        // Get referrer details (Level 1 - gets 50 KES)
        $referrer = getUserById($pdo, $referrerId);
        
        if (!$referrer || empty($referrer['phone'])) {
            file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Referrer $referrerId not found or no phone\n", FILE_APPEND);
            return false;
        }

        // Check if referrer has a referrer (Level 2 - gets 10 KES)
        $level2ReferrerId = $referrer['referrer_id'] ?? null;
        $level2Referrer = null;
        $level2ReferrerPhone = null;
        
        if ($level2ReferrerId) {
            $level2Referrer = getUserById($pdo, $level2ReferrerId);
            if ($level2Referrer && !empty($level2Referrer['phone'])) {
                $level2ReferrerPhone = $level2Referrer['phone'];
                
                // Check if Level 2 referrer was already rewarded for this chain
                $level2RewardCheck = $pdo->prepare("
                    SELECT COUNT(*) as already_rewarded 
                    FROM rewards 
                    WHERE referrer_id = ? AND referred_user_id = ? AND reward_type = 'gas_refill_chain'
                ");
                $level2RewardCheck->execute([$level2ReferrerId, $userId]);
                $level2RewardResult = $level2RewardCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($level2RewardResult['already_rewarded'] > 0) {
                    $level2Referrer = null; // Skip if already rewarded
                }
            }
        }

        // Record Level 1 reward
        $rewardRecord = $pdo->prepare("
            INSERT INTO rewards (referrer_id, referred_user_id, amount, reward_type, order_id, status, created_at) 
            VALUES (?, ?, ?, 'gas_refill_first', ?, 'pending', NOW())
        ");
        $rewardRecord->execute([$referrerId, $userId, 50, $orderId]);

        // Record Level 2 reward if applicable - FIXED: Only insert if level2Referrer exists
        if ($level2Referrer) {
            $level2RewardRecord = $pdo->prepare("
                INSERT INTO rewards (referrer_id, referred_user_id, amount, reward_type, order_id, status, created_at) 
                VALUES (?, ?, ?, 'gas_refill_chain', ?, 'pending', NOW())
            ");
            $level2RewardRecord->execute([$level2ReferrerId, $userId, 10, $orderId]);
        }

        // Mark order as rewarded
        $updateOrder = $pdo->prepare("UPDATE orders SET is_rewarded = TRUE WHERE id = ?");
        $updateOrder->execute([$orderId]);

        // Prepare reward data for manual processing - Level 1 referrer gets 50 KES
        $rewardData = [
            'user_id' => $userId,
            'referrer_id' => $referrerId,
            'referrer_phone' => $referrer['phone'],
            'referrer_amount' => 50,
            'order_id' => $orderId,
            'transaction_amount' => $amount,
            'reward_level' => 1
        ];

        file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Storing Level 1 reward: " . json_encode($rewardData) . "\n", FILE_APPEND);

        // Store reward for manual processing
        storeRewardForManualProcessing($rewardData);
        
        // If Level 2 referrer exists, store their reward too
        if ($level2Referrer) {
            $level2RewardData = [
                'user_id' => $userId,
                'referrer_id' => $level2ReferrerId,
                'referrer_phone' => $level2ReferrerPhone,
                'referrer_amount' => 10,
                'order_id' => $orderId,
                'transaction_amount' => $amount,
                'reward_level' => 2
            ];
            
            file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Storing Level 2 reward: " . json_encode($level2RewardData) . "\n", FILE_APPEND);
            
            // Store Level 2 reward for manual processing
            storeRewardForManualProcessing($level2RewardData);
        }
        
        return true;

    } catch (PDOException $e) {
        file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Reward check error: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

function storeRewardForManualProcessing($rewardData) {
    global $pdo;
    $logDir = 'logs/';
    
    try {
        // Store the reward for manual processing
        $stmt = $pdo->prepare("
            INSERT INTO pending_b2c_rewards 
            (user_id, referrer_id, referrer_phone, referrer_amount, order_id, transaction_amount, reward_level, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $rewardData['user_id'],
            $rewardData['referrer_id'],
            $rewardData['referrer_phone'],
            $rewardData['referrer_amount'],
            $rewardData['order_id'],
            $rewardData['transaction_amount'],
            $rewardData['reward_level']
        ]);
        
        file_put_contents($logDir . 'callback.log', date('Y-m-d H:i:s') . " - Reward stored for manual processing: " . json_encode($rewardData) . "\n", FILE_APPEND);
        
        return true;
    } catch (PDOException $e) {
        file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Error storing reward for manual processing: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
?>