<?php
require_once '../db.php'; // Include database connection

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/errors.log');
error_reporting(E_ALL);

// Log directory
$logDir = __DIR__ . '/logs/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log raw input
$rawInput = file_get_contents('php://input');
file_put_contents($logDir . 'b2c_timeout.log', date('Y-m-d H:i:s') . " - B2C Timeout Raw Input: " . $rawInput . "\n", FILE_APPEND);

// Check if input is empty
if (empty($rawInput)) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - Empty B2C timeout received\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Empty timeout']);
    exit;
}

$callbackData = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - B2C Timeout JSON decode error: " . json_last_error_msg() . "\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON']);
    exit;
}

$conversationId = $callbackData['Result']['ConversationID'] ?? '';

if (!empty($conversationId)) {
    try {
        // Use the updateB2CTransactionStatus function from db.php
        $rowsUpdated = updateB2CTransactionStatus($pdo, $conversationId, 'timeout');
        
        file_put_contents($logDir . 'b2c_timeout.log', date('Y-m-d H:i:s') . " - B2C timeout updated: $rowsUpdated rows affected, ConversationID: $conversationId\n", FILE_APPEND);
        
        if ($rowsUpdated === 0) {
            file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - No transaction found for timeout: ConversationID: $conversationId\n", FILE_APPEND);
            
            // Try alternative approach if no rows were updated
            $stmt = $pdo->prepare("UPDATE b2c_transactions SET status = 'timeout', updated_at = NOW() WHERE conversation_id = ?");
            $stmt->execute([$conversationId]);
            $altRowsUpdated = $stmt->rowCount();
            
            if ($altRowsUpdated > 0) {
                file_put_contents($logDir . 'b2c_timeout.log', date('Y-m-d H:i:s') . " - B2C timeout updated via fallback: $altRowsUpdated rows affected\n", FILE_APPEND);
            }
        }
        
        file_put_contents($logDir . 'b2c_timeout.log', date('Y-m-d H:i:s') . " - B2C Timeout Processed: ConversationID=$conversationId\n", FILE_APPEND);
        
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - B2C Timeout Error: $errorMsg, ConversationID=$conversationId\n", FILE_APPEND);
        
        // Optional: Send email notification
        try {
            $stmt = $pdo->query("SELECT email FROM admin_emails");
            $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($emails as $email) {
                @mail($email, "B2C Timeout Error", "Database error for ConversationID=$conversationId: $errorMsg");
            }
        } catch (Exception $mailError) {
            // Silent fail for email notifications
        }
    }
} else {
    file_put_contents($logDir . 'errors.log', date('Y-m-d H:i:s') . " - B2C Timeout missing ConversationID\n", FILE_APPEND);
}

http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Timeout processed']);
?>