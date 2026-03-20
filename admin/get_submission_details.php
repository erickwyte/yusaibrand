<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../log_in.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM sell_requests WHERE id = ?");
    $stmt->execute([$id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        http_response_code(404);
        echo json_encode(['error' => 'Submission not found']);
        exit;
    }

    // Define public URL for images
    $publicBaseUrl = 'https://yusaibrand.co.ke/uploads/sell_requests/';

    // Update image paths for web display
    foreach (['image1', 'image2', 'image3'] as $field) {
        if (!empty($submission[$field])) {
            $filename = basename($submission[$field]);
            $submission[$field] = $publicBaseUrl . $filename;
        }
    }

    // Debug: Log the fetched submission
    error_log("Fetched submission ID: $id, product_name: {$submission['product_name']}");

    header('Content-Type: application/json');
    echo json_encode($submission);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}