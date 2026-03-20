<?php
// check_status.php
session_start();
require_once 'db.php';
require 'vendor/autoload.php';
use GuzzleHttp\Client;

if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php?error=' . urlencode('Please log in.'));
    exit;
}

$checkoutRequestId = filter_input(INPUT_GET, 'checkout_request_id', FILTER_SANITIZE_STRING);
if (!$checkoutRequestId) {
    header('Location: checkout.php?error=' . urlencode('Invalid request ID.'));
    exit;
}

$consumerKey = 'yVkIFo8GyK8bmU0gNtPTKJm7552rg0DaA6k7brAinccAG25m';
$consumerSecret = '3jE08oKbYrNgjtJGSbXvTFshxAysm47ZExWNfOtLBaGU2lDxIGtYZN8vqbfGKFQo';
$shortCode = '174379';
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$baseUrl = 'https://sandbox.safaricom.co.ke';

$accessToken = getAccessToken($consumerKey, $consumerSecret, $baseUrl);
$timestamp = date('YmdHis');
$password = base64_encode($shortCode . $passkey . $timestamp);

$client = new Client();
$payload = [
    'BusinessShortCode' => $shortCode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'CheckoutRequestID' => $checkoutRequestId
];
try {
    $response = $client->post("$baseUrl/mpesa/stkpushquery/v1/query", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
            'Content-Type' => 'application/json'
        ],
        'json' => $payload
    ]);
    $result = json_decode($response->getBody(), true);
    file_put_contents('check_status.log', "Status Query: " . json_encode($result) . "\n", FILE_APPEND);

    if ($result['ResultCode'] == '0') {
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed', mpesa_receipt_number = ? WHERE checkout_request_id = ?");
        $stmt->execute([$result['MpesaReceiptNumber'], $checkoutRequestId]);
        $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', mpesa_receipt_number = ? WHERE id = (SELECT order_id FROM transactions WHERE checkout_request_id = ?)");
        $stmt->execute([$result['MpesaReceiptNumber'], $checkoutRequestId]);
        header('Location: success.php');
    } else {
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'failed' WHERE checkout_request_id = ?");
        $stmt->execute([$checkoutRequestId]);
        header('Location: checkout.php?error=' . urlencode('Transaction failed: ' . $result['ResultDesc']));
    }
} catch (Exception $e) {
    file_put_contents('errors.log', "Status Check Error: " . $e->getMessage() . "\n", FILE_APPEND);
    header('Location: checkout.php?error=' . urlencode('Failed to check transaction status.'));
}
exit;
?>