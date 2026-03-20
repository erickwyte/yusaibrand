<?php
// b2c_result.php

// Database connection
require_once '../db.php'; // adjust path if needed

// Capture callback response
$callbackResponse = file_get_contents('php://input');
$response = json_decode($callbackResponse, true);

// Log raw response for debugging
$logFile = "B2CResultResponse.json";
$log = fopen($logFile, "a");
fwrite($log, $callbackResponse . "\n");
fclose($log);

// Process response if valid
if ($response && isset($response['Result'])) {
    $result = $response['Result'];

    $conversationId = $result['ConversationID'] ?? '';
    $transactionId  = $result['TransactionID'] ?? '';
    $resultCode     = $result['ResultCode'] ?? '';
    $resultDesc     = $result['ResultDesc'] ?? '';

    // Extract recipient details
    $receiver = '';
    $amount   = '';
    if (isset($result['ResultParameters']['ResultParameter'])) {
        foreach ($result['ResultParameters']['ResultParameter'] as $param) {
            if ($param['Key'] == 'TransactionAmount') {
                $amount = $param['Value'];
            }
            if ($param['Key'] == 'ReceiverPartyPublicName') {
                $receiver = $param['Value'];
            }
        }
    }

    // Save into DB
    $stmt = $pdo->prepare("INSERT INTO b2c_rewards (conversation_id, transaction_id, receiver, amount, result_code, result_desc, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $conversationId,
        $transactionId,
        $receiver,
        $amount,
        $resultCode,
        $resultDesc
    ]);
}

http_response_code(200);
echo json_encode(["ResultReceived" => true]);
