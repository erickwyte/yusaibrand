<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($message) {
        header('Content-Type: application/json');
        echo json_encode($message);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Message not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Message ID required']);
}
exit;