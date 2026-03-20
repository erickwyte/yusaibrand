<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) { 
    header('Location: ../log_in.php');
    exit;
}

// Check if logged-in user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../access_denied.php');
    exit;
}

// Fetch admin email from database or session
try {
    $stmt = $pdo->prepare("SELECT email FROM admin_emails LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $adminEmail = $admin['email'] ?? ($_SESSION['admin_email'] ?? 'admin@yusai.com');
} catch (PDOException $e) {
    $adminEmail = $_SESSION['admin_email'] ?? 'admin@yusai.com';
}

// Handle status updates (both payment and delivery)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['order_id'], $_POST['payment_status'])) {
        $orderId = (int)$_POST['order_id'];
        $status = $_POST['payment_status'];

        if (in_array($status, ['pending', 'paid', 'failed'])) {
            try {
                $updateStmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
                $updateStmt->execute([$status, $orderId]);

                if ($status === 'paid' || $status === 'failed') {
                    $transactionStmt = $pdo->prepare("UPDATE transactions SET status = ? WHERE order_id = ?");
                    $transactionStmt->execute([$status, $orderId]);
                }

                header("Location: deliveries.php?success=" . urlencode("Payment status updated successfully."));
                exit;
            } catch (PDOException $e) {
                header("Location: deliveries.php?error=" . urlencode("Database error: " . $e->getMessage()));
                exit;
            }
        } else {
            header("Location: deliveries.php?error=" . urlencode("Invalid payment status selected."));
            exit;
        }
    }
    elseif (isset($_POST['order_id'], $_POST['delivery_status'])) {
        $orderId = (int)$_POST['order_id'];
        $status = $_POST['delivery_status'];

        if (in_array($status, ['pending', 'delivered'])) {
            try {
                $updateStmt = $pdo->prepare("UPDATE orders SET delivery_status = ? WHERE id = ?");
                $updateStmt->execute([$status, $orderId]);

                header("Location: deliveries.php?success=" . urlencode("Delivery status updated successfully."));
                exit;
            } catch (PDOException $e) {
                header("Location: deliveries.php?error=" . urlencode("Database error: " . $e->getMessage()));
                exit;
            }
        } else {
            header("Location: deliveries.php?error=" . urlencode("Invalid delivery status selected."));
            exit;
        }
    }
}

// Fetch paid deliveries
$search = $_GET['search'] ?? '';
$query = "
    SELECT o.id, o.user_id, o.name, o.phone, o.address, o.notes, o.total_amount, o.created_at, 
           o.payment_status, o.delivery_status, u.email AS user_email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.payment_status = 'paid'";
$params = [];

if ($search) {
    $query .= " AND (o.id LIKE ? OR u.email LIKE ? OR o.phone LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$query .= " ORDER BY o.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $paidDeliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $orderIds = array_column($paidDeliveries, 'id');
    $orderProducts = [];
    
    if (!empty($orderIds)) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $productQuery = "SELECT order_id, product_name, quantity, total_price 
                         FROM order_items 
                         WHERE order_id IN ($placeholders)";
        $productStmt = $pdo->prepare($productQuery);
        $productStmt->execute($orderIds);
        $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            $orderId = $product['order_id'];
            if (!isset($orderProducts[$orderId])) {
                $orderProducts[$orderId] = [];
            }
            $orderProducts[$orderId][] = $product;
        }
    }
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
    <title>Yusai Admin - Paid Deliveries</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 8px;
            --box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f8fc;
            color: #333;
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }

        .main-content {
            margin-left: 230px;
            padding: 15px;
            margin-top: 60px;
            transition: var(--transition);
            overflow-x: hidden;
        }

        .container {
            max-width: 100%;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .success,
        .error {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: var(--border-radius);
            font-weight: bold;
            border-left: 4px solid;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-color: #721c24;
        }

        .search-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-filter input {
            flex: 1;
            min-width: 150px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
        }

        .search-filter button {
            padding: 12px 20px;
            background: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .search-filter button:hover {
            background: #0056b3;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-top: 20px;
        }

        .delivery-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
            min-width: 1000px;
        }

        .delivery-table th,
        .delivery-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            text-align: left;
        }

        .delivery-table th {
            background: var(--primary-color);
            color: #fff;
            position: sticky;
            top: 0;
            white-space: nowrap;
        }

        .delivery-table tr:hover {
            background: #f1f1f1;
        }

        .status-form {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .status-form select,
        .status-form button {
            padding: 8px;
            font-size: 0.85rem;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
        }

        .status-form button {
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .status-form button:hover {
            background-color: #0056b3;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background-color: var(--warning-color);
            color: #212529;
        }

        .status-delivered {
            background-color: var(--success-color);
            color: white;
        }

        .notes-cell {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .products-cell {
            max-width: 200px;
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

        .product-quantity {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
        }

        .view-products-btn {
            margin-top: 5px;
            background: #17a2b8;
            color: #fff;
            padding: 6px 12px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .view-products-btn:hover {
            background: #138496;
        }

        .action-links a {
            margin-top: 5px;
            display: inline-block;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .action-links a:hover {
            text-decoration: underline;
        }

        /* Card Layout for Small Screens */
        .delivery-cards {
            display: none;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }

        .delivery-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .delivery-card h4 {
            margin: 0 0 10px;
            font-size: 1.1rem;
            color: var(--primary-color);
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }

        .delivery-card p {
            margin: 8px 0;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
        }

        .delivery-card p strong {
            flex: 1;
            color: #555;
        }

        .card-actions {
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        /* Modal Styles */
        .products-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            padding: 10px;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-sizing: border-box;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #777;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-modal:hover {
            color: #333;
        }

        .product-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .product-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        /* Mobile Navigation */
        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--primary-color);
            color: white;
            padding: 12px 15px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .mobile-header h1 {
            font-size: 1.2rem;
            margin: 0;
        }

        .hamburger {
            display: none;
            font-size: 1.2rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 10px 12px;
            cursor: pointer;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 230px;
            height: 100%;
            background: #2c3e50;
            transition: transform 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        /* Responsive Layout */
        @media screen and (max-width: 1200px) {
            .delivery-table {
                min-width: 1100px;
            }
        }

        @media screen and (max-width: 1024px) {
            .delivery-table {
                min-width: 1000px;
            }
        }

        @media screen and (max-width: 900px) {
            .delivery-table {
                min-width: 900px;
            }
        }

        @media screen and (max-width: 768px) {
            .mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .main-content {
                margin-left: 0;
                padding: 10px;
                margin-top: 50px;
            }

            .hamburger {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1200;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .table-container {
                display: none;
            }

            .delivery-cards {
                display: flex;
            }

            .search-filter {
                flex-direction: column;
            }

            .search-filter input,
            .search-filter button {
                width: 100%;
                font-size: 1rem;
                padding: 12px;
            }

            .status-form {
                flex-direction: column;
                align-items: stretch;
            }

            .status-form select,
            .status-form button {
                width: 100%;
                margin-bottom: 5px;
                font-size: 0.9rem;
                padding: 10px;
            }

            .container {
                padding: 15px;
                border-radius: 0;
                box-shadow: none;
            }
            
            .action-links a {
                display: block;
                text-align: center;
                padding: 8px;
                background: #f0f0f0;
                border-radius: var(--border-radius);
                margin-top: 8px;
            }
        }

        @media screen and (max-width: 480px) {
            .mobile-header h1 {
                font-size: 1.1rem;
            }
            
            .delivery-card h4 {
                font-size: 1rem;
            }

            .delivery-card p {
                font-size: 0.85rem;
                flex-direction: column;
            }
            
            .delivery-card p strong {
                margin-bottom: 3px;
            }

            .view-products-btn,
            .status-form select,
            .status-form button {
                font-size: 0.9rem;
                padding: 8px;
            }

            .modal-content {
                padding: 15px;
                max-width: 95%;
            }

            .product-list li {
                font-size: 0.85rem;
            }

            .status-badge {
                font-size: 0.8rem;
                padding: 4px 8px;
            }
            
            .card-actions {
                flex-direction: column;
            }
        }

        @media screen and (max-width: 360px) {
            .delivery-card {
                padding: 12px;
            }

            .search-filter input,
            .search-filter button {
                font-size: 0.9rem;
                padding: 10px;
            }

            .hamburger {
                padding: 8px 10px;
                font-size: 1rem;
            }
            
            .mobile-header {
                padding: 10px;
            }
        }
        
        /* Improved focus states for accessibility */
        button:focus,
        select:focus,
        input:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* Animation for modal */
        .products-modal {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <h1>Yusai Deliveries</h1>
    </div>

    <!-- Hamburger Menu -->
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <?php if (isset($_GET['success'])): ?>
                <div class="success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <!-- Search Form -->
            <form method="GET" class="search-filter">
                <input type="text" name="search" placeholder="Search by order ID, email, or phone..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Search</button>
            </form>

            <div class="content">
                <?php if (empty($paidDeliveries)): ?>
                    <p>No paid deliveries found.</p>
                <?php else: ?>
                    <!-- Table Layout for Desktop -->
                    <div class="table-container">
                        <table class="delivery-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>User</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Notes</th>
                                    <th>Products</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                    <th>Payment</th>
                                    <th>Delivery</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paidDeliveries as $delivery): ?>
                                    <?php
                                    $phone = $delivery['phone'];
                                    if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
                                        $phone = '+254' . substr($phone, 1);
                                    } elseif (strlen($phone) === 12 && substr($phone, 0, 3) !== '+254') {
                                        $phone = '+254' . substr($phone, 3);
                                    }
                                    $products = $orderProducts[$delivery['id']] ?? [];
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($delivery['id']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($delivery['name']) ?><br>
                                            <small><?= htmlspecialchars($delivery['user_email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($phone) ?></td>
                                        <td><?= htmlspecialchars($delivery['address']) ?></td>
                                        <td class="notes-cell" title="<?= htmlspecialchars($delivery['notes']) ?>">
                                            <?= $delivery['notes'] ? htmlspecialchars($delivery['notes']) : '--' ?>
                                        </td>
                                        <td class="products-cell">
                                            <?php if (!empty($products)): ?>
                                                <?php 
                                                $count = 0;
                                                foreach ($products as $product): 
                                                    if ($count >= 2) break;
                                                ?>
                                                    <div class="product-item">
                                                        <span class="product-name"><?= htmlspecialchars($product['product_name']) ?></span>
                                                        <span class="product-quantity">x<?= $product['quantity'] ?></span>
                                                    </div>
                                                <?php 
                                                    $count++;
                                                endforeach; 
                                                ?>
                                                <?php if (count($products) > 2): ?>
                                                    <div>+<?= count($products) - 2 ?> more</div>
                                                <?php endif; ?>
                                                <button class="view-products-btn" 
                                                        data-order-id="<?= $delivery['id'] ?>"
                                                        data-order-user="<?= htmlspecialchars($delivery['name']) ?>">
                                                    View All
                                                </button>
                                            <?php else: ?>
                                                No products
                                            <?php endif; ?>
                                        </td>
                                        <td>KSh <?= number_format($delivery['total_amount'], 2) ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($delivery['created_at'])) ?></td>
                                        <td>
                                            <span class="status-badge 
                                                <?= $delivery['payment_status'] === 'paid' ? 'status-delivered' : 'status-pending' ?>">
                                                <?= htmlspecialchars($delivery['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge 
                                                <?= $delivery['delivery_status'] === 'delivered' ? 'status-delivered' : 'status-pending' ?>">
                                                <?= htmlspecialchars($delivery['delivery_status']) ?>
                                            </span>
                                        </td>
                                        <td class="action-links">
                                            <form class="status-form" method="POST">
                                                <input type="hidden" name="order_id" value="<?= $delivery['id'] ?>">
                                                <select name="delivery_status">
                                                    <option value="pending" <?= $delivery['delivery_status'] === 'pending' ? 'selected' : '' ?>>
                                                        Not Delivered
                                                    </option>
                                                    <option value="delivered" <?= $delivery['delivery_status'] === 'delivered' ? 'selected' : '' ?>>
                                                        Delivered
                                                    </option>
                                                </select>
                                                <button type="submit">Update</button>
                                            </form>
                                            <a href="order_details.php?order_id=<?= $delivery['id'] ?>">Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Card Layout for Small Screens -->
                    <div class="delivery-cards">
                        <?php foreach ($paidDeliveries as $delivery): ?>
                            <?php
                            $phone = $delivery['phone'];
                            if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
                                $phone = '+254' . substr($phone, 1);
                            } elseif (strlen($phone) === 12 && substr($phone, 0, 3) !== '+254') {
                                $phone = '+254' . substr($phone, 3);
                            }
                            $products = $orderProducts[$delivery['id']] ?? [];
                            ?>
                            <div class="delivery-card">
                                <h4>Order #<?= htmlspecialchars($delivery['id']) ?></h4>
                                <p><strong>Customer:</strong> <span><?= htmlspecialchars($delivery['name']) ?> (<small><?= htmlspecialchars($delivery['user_email']) ?></small>)</span></p>
                                <p><strong>Phone:</strong> <span><?= htmlspecialchars($phone) ?></span></p>
                                <p><strong>Address:</strong> <span><?= htmlspecialchars($delivery['address']) ?></span></p>
                                <p><strong>Notes:</strong> <span><?= $delivery['notes'] ? htmlspecialchars($delivery['notes']) : '--' ?></span></p>
                                <p><strong>Products:</strong>
                                    <span>
                                    <?php if (!empty($products)): ?>
                                        <?= htmlspecialchars($products[0]['product_name']) ?> (x<?= $products[0]['quantity'] ?>)
                                        <?php if (count($products) > 1): ?>
                                            +<?= count($products) - 1 ?> more
                                        <?php endif; ?>
                                    <?php else: ?>
                                        No products
                                    <?php endif; ?>
                                    </span>
                                </p>
                                <p><strong>Total:</strong> <span>KSh <?= number_format($delivery['total_amount'], 2) ?></span></p>
                                <p><strong>Date:</strong> <span><?= date('M d, Y H:i', strtotime($delivery['created_at'])) ?></span></p>
                                <p><strong>Payment:</strong> 
                                    <span class="status-badge 
                                        <?= $delivery['payment_status'] === 'paid' ? 'status-delivered' : 'status-pending' ?>">
                                        <?= htmlspecialchars($delivery['payment_status']) ?>
                                    </span>
                                </p>
                                <p><strong>Delivery:</strong> 
                                    <span class="status-badge 
                                        <?= $delivery['delivery_status'] === 'delivered' ? 'status-delivered' : 'status-pending' ?>">
                                        <?= htmlspecialchars($delivery['delivery_status']) ?>
                                    </span>
                                </p>
                                <div class="card-actions">
                                    <form class="status-form" method="POST">
                                        <input type="hidden" name="order_id" value="<?= $delivery['id'] ?>">
                                        <select name="delivery_status">
                                            <option value="pending" <?= $delivery['delivery_status'] === 'pending' ? 'selected' : '' ?>>
                                                Not Delivered
                                            </option>
                                            <option value="delivered" <?= $delivery['delivery_status'] === 'delivered' ? 'selected' : '' ?>>
                                                Delivered
                                            </option>
                                        </select>
                                        <button type="submit">Update Status</button>
                                    </form>
                                    <button class="view-products-btn" 
                                            data-order-id="<?= $delivery['id'] ?>"
                                            data-order-user="<?= htmlspecialchars($delivery['name']) ?>">
                                        View Products
                                    </button>
                                    <a href="order_details.php?order_id=<?= $delivery['id'] ?>">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Products Modal -->
    <div class="products-modal" id="productsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Order Products</h3>
                <button class="close-modal">&times;</button>
            </div>
            <ul class="product-list" id="productList"></ul>
        </div>
    </div>

    <script>
        // Products modal functionality
        document.querySelectorAll('.view-products-btn').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                const userName = this.getAttribute('data-order-user');
                
                document.getElementById('modal-title').textContent = 
                    `Products for Order #${orderId} (${userName})`;
                
                const products = <?= json_encode($orderProducts); ?>;
                const orderProducts = products[orderId] || [];
                
                const productList = document.getElementById('productList');
                productList.innerHTML = '';
                
                if (orderProducts.length === 0) {
                    productList.innerHTML = '<li>No products found for this order</li>';
                } else {
                    let total = 0;
                    orderProducts.forEach(product => {
                        total += parseFloat(product.total_price);
                        const li = document.createElement('li');
                        li.innerHTML = `
                            <div>
                                <strong>${product.product_name}</strong><br>
                                <small>Quantity: ${product.quantity}</small>
                            </div>
                            <div>KSh ${parseFloat(product.total_price).toFixed(2)}</div>
                        `;
                        productList.appendChild(li);
                    });
                    
                    // Add total row
                    const totalLi = document.createElement('li');
                    totalLi.innerHTML = `
                        <div><strong>Total</strong></div>
                        <div><strong>KSh ${total.toFixed(2)}</strong></div>
                    `;
                    totalLi.style.borderTop = '2px solid #ddd';
                    totalLi.style.fontWeight = 'bold';
                    productList.appendChild(totalLi);
                }
                
                document.getElementById('productsModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        });
        
        document.querySelector('.close-modal').addEventListener('click', function() {
            document.getElementById('productsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        });
        
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('productsModal');
            if (event.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });

        // Toggle sidebar for mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
            
            // Update hamburger icon
            const hamburger = document.querySelector('.hamburger');
            if (sidebar.classList.contains('active')) {
                hamburger.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                hamburger.innerHTML = '<i class="fas fa-bars"></i>';
            }
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const hamburger = document.querySelector('.hamburger');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('active') &&
                !sidebar.contains(event.target) &&
                event.target !== hamburger &&
                !hamburger.contains(event.target)) {
                toggleSidebar();
            }
        });
        
        // Adjust table visibility based on screen size
        function checkScreenSize() {
            const tableContainer = document.querySelector('.table-container');
            const cards = document.querySelector('.delivery-cards');
            
            if (window.innerWidth <= 768) {
                if (tableContainer) tableContainer.style.display = 'none';
                if (cards) cards.style.display = 'flex';
            } else {
                if (tableContainer) tableContainer.style.display = 'block';
                if (cards) cards.style.display = 'none';
            }
        }
        
        // Initial check
        checkScreenSize();
        
        // Listen for resize events
        window.addEventListener('resize', checkScreenSize);
    </script>
</body>
</html>