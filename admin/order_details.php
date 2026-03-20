<?php
// order_details.php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) { 
    header('Location: ../log_in.php');
    exit;
}

// Check if logged-in user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../access_denied.php'); // Redirect to access denied page
    exit;
}

// Load admin email from session
$adminEmail = $_SESSION['admin_email'] ?? 'admin@yusai.com';

// Get order ID from query string
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$orderId) {
    header("Location: deliveries.php?error=" . urlencode("Invalid order ID."));
    exit;
}

// Handle delivery status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_status'])) {
    $status = $_POST['delivery_status'];

    if (in_array($status, ['pending', 'delivered'])) {
        try {
            $updateStmt = $pdo->prepare("UPDATE orders SET delivery_status = ? WHERE id = ?");
            $updateStmt->execute([$status, $orderId]);

            header("Location: order_details.php?order_id=$orderId&success=" . urlencode("Delivery status updated successfully."));
            exit;
        } catch (PDOException $e) {
            header("Location: order_details.php?order_id=$orderId&error=" . urlencode("Database error: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: order_details.php?order_id=$orderId&error=" . urlencode("Invalid delivery status selected."));
        exit;
    }
}

// Fetch order details
try {
    // Get order header info
    $orderQuery = "
        SELECT o.*, u.email AS user_email, 
               DATE_FORMAT(o.created_at, '%b %d, %Y %H:%i') AS formatted_date,
               t.mpesa_receipt_number, t.phone_number, t.amount AS transaction_amount, t.created_at AS transaction_date,
               t.status AS transaction_status
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN transactions t ON o.id = t.order_id
        WHERE o.id = ?";
    $orderStmt = $pdo->prepare($orderQuery);
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header("Location: deliveries.php?error=" . urlencode("Order not found."));
        exit;
    }

    // Format phone number
    $phone = $order['phone'];
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        $phone = '+254' . substr($phone, 1);
    } elseif (strlen($phone) === 12 && substr($phone, 0, 3) !== '+254') {
        $phone = '+254' . substr($phone, 3);
    }

    // Get order items
    $itemsQuery = "SELECT * FROM order_items WHERE order_id = ?";
    $itemsStmt = $pdo->prepare($itemsQuery);
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate subtotal
    $subtotal = array_reduce($orderItems, function($carry, $item) {
        return $carry + ($item['product_price'] * $item['quantity']);
    }, 0);

} catch (PDOException $e) {
    header("Location: deliveries.php?error=" . urlencode("Database error: " . $e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yusai Admin - Order Details #<?= $orderId ?></title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #007bff;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .container {
            max-width: 1000px;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .success, .error {
            padding: 12px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-weight: bold;
        }
        
        .success {
            background: #e6ffed;
            color: #2d6a4f;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background: #ffe5e5;
            color: #b02a37;
            border-left: 4px solid #dc3545;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .order-info {
            flex: 1;
            min-width: 300px;
        }
        
        .order-status {
            flex: 1;
            min-width: 300px;
            text-align: right;
        }
        
        .order-id {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .order-date {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 15px;
        }
        
        .user-info {
            margin-top: 20px;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            display: inline-block;
            min-width: 120px;
        }
        
        .info-value {
            color: #333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-left: 10px;
        }
        
        .status-pending { background: #ffc107; color: #333; }
        .status-paid { background: #28a745; color: white; }
        .status-delivered { background: #28a745; color: white; }
        .status-failed { background: #dc3545; color: white; }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .summary-item {
            padding: 10px;
        }
        
        .summary-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 1.1rem;
            color: #333;
        }
        
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .order-table th {
            background-color: var(--primary);
            color: white;
            text-align: left;
            padding: 15px;
            font-weight: 600;
        }
        
        .order-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-table tr:last-child td {
            border-bottom: none;
        }
        
        .order-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .order-table tr:hover {
            background-color: #f1f8ff;
        }
        
        .total-row {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .text-right {
            text-align: right;
        }
        
        .status-form {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
        }
        
        .status-form select {
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 1rem;
            min-width: 200px;
        }
        
        .status-form button {
            padding: 10px 20px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .status-form button:hover {
            background-color: #0069d9;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 25px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .back-link:hover {
            background: #5a6268;
        }
        
        .notes-section {
            background: #fff8e6;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        
        .notes-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .notes-content {
            color: #666;
            line-height: 1.5;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .order-header {
                flex-direction: column;
            }
            
            .order-status {
                text-align: left;
                margin-top: 20px;
            }
            
            .container {
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .order-table {
                font-size: 0.9rem;
            }
            
            .order-table th,
            .order-table td {
                padding: 10px 8px;
            }
            
            .order-id {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="navbar">
            <h3>Order Details</h3>
            <div class="admin-info">
                <i class="fas fa-user-shield"></i> <?= htmlspecialchars($adminEmail) ?>
            </div>
        </div>

        <div class="container">
            <?php if (isset($_GET['success'])): ?>
                <div class="success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <div class="order-header">
                <div class="order-info">
                    <div class="order-id">Order #<?= $orderId ?></div>
                    <div class="order-date">Placed on <?= $order['formatted_date'] ?></div>
                    
                    <div class="user-info">
                        <div><span class="info-label">Customer:</span>
                            <span class="info-value"><?= htmlspecialchars($order['name']) ?></span></div>
                        <div><span class="info-label">Email:</span>
                            <span class="info-value"><?= htmlspecialchars($order['user_email']) ?></span></div>
                        <div><span class="info-label">Phone:</span>
                            <span class="info-value"><?= htmlspecialchars($phone) ?></span></div>
                        <div><span class="info-label">Address:</span>
                            <span class="info-value"><?= htmlspecialchars($order['address']) ?></span></div>
                    </div>
                    
                    <?php if ($order['notes']): ?>
                        <div class="notes-section">
                            <div class="notes-label">Customer Notes:</div>
                            <div class="notes-content"><?= htmlspecialchars($order['notes']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="order-status">
                    <div>
                        <strong>Payment Status:</strong>
                        <span class="status-badge 
                            <?= $order['payment_status'] === 'paid' ? 'status-paid' : 
                                ($order['payment_status'] === 'failed' ? 'status-failed' : 'status-pending') ?>">
                            <?= htmlspecialchars(ucfirst($order['payment_status'])) ?>
                        </span>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <strong>Delivery Status:</strong>
                        <span class="status-badge 
                            <?= $order['delivery_status'] === 'delivered' ? 'status-delivered' : 'status-pending' ?>">
                            <?= htmlspecialchars(ucfirst($order['delivery_status'])) ?>
                        </span>
                    </div>
                    
                    <?php if ($order['mpesa_receipt_number']): ?>
                        <div class="order-summary" style="margin-top: 20px; text-align: left;">
                            <div><strong>Payment Details:</strong></div>
                            <div><span class="info-label">M-Pesa Receipt:</span> 
                                <?= htmlspecialchars($order['mpesa_receipt_number']) ?></div>
                            <div><span class="info-label">Phone:</span> 
                                <?= htmlspecialchars($order['phone_number']) ?></div>
                            <div><span class="info-label">Amount:</span> 
                                KSh <?= number_format($order['transaction_amount'], 2) ?></div>
                            <div><span class="info-label">Status:</span> 
                                <span class="status-badge 
                                    <?= $order['transaction_status'] === 'completed' ? 'status-paid' : 
                                        ($order['transaction_status'] === 'failed' ? 'status-failed' : 'status-pending') ?>">
                                    <?= htmlspecialchars(ucfirst($order['transaction_status'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-summary">
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Order Total</div>
                        <div class="summary-value">KSh <?= number_format($order['total_amount'], 2) ?></div>
                    </div>
                    
                    <div class="summary-item">
                        <div class="summary-label">Items</div>
                        <div class="summary-value"><?= count($orderItems) ?></div>
                    </div>
                    
                    <div class="summary-item">
                        <div class="summary-label">Payment Method</div>
                        <div class="summary-value">M-Pesa</div>
                    </div>
                    
                    <div class="summary-item">
                        <div class="summary-label">Delivery Method</div>
                        <div class="summary-value">Standard Delivery</div>
                    </div>
                </div>
            </div>

            <h3 style="margin: 30px 0 15px 0;">Order Items</h3>
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td>KSh <?= number_format($item['product_price'], 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td class="text-right">KSh <?= number_format($item['product_price'] * $item['quantity'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Subtotal:</td>
                        <td class="text-right">KSh <?= number_format($subtotal, 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Delivery Fee:</td>
                        <td class="text-right">KSh 0.00</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" class="text-right">Grand Total:</td>
                        <td class="text-right">KSh <?= number_format($order['total_amount'], 2) ?></td>
                    </tr>
                </tbody>
            </table>

            <form class="status-form" method="POST">
                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                <div>
                    <strong>Update Delivery Status:</strong>
                </div>
                <select name="delivery_status">
                    <option value="pending" <?= $order['delivery_status'] === 'pending' ? 'selected' : '' ?>>
                        Not Delivered
                    </option>
                    <option value="delivered" <?= $order['delivery_status'] === 'delivered' ? 'selected' : '' ?>>
                        Delivered
                    </option>
                </select>
                <button type="submit">Update Status</button>
            </form>

            <a href="deliveries.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Deliveries
            </a>
        </div>
    </div>
</body>
</html>