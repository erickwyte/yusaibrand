<?php
session_start();

// Set payment completed flag
$_SESSION['payment_completed'] = true;

require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$orderId = $_GET['order_id'] ?? '';
$transactionId = $_GET['transaction_id'] ?? '';

if (!$orderId || !$transactionId) {
    header('Location: checkout.php?error=' . urlencode('Invalid order or transaction ID'));
    exit;
}

try {
    $order = getOrderById($pdo, $orderId);
    if (!$order || $order['user_id'] != $_SESSION['user_id'] || $order['payment_status'] != 'paid') {
        header('Location: checkout.php?error=' . urlencode('Invalid or unpaid order'));
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE checkout_request_id = ? AND order_id = ? AND status = 'completed'");
    $stmt->execute([$transactionId, $orderId]);
    $transaction = $stmt->fetch();
    if (!$transaction) {
        header('Location: checkout.php?error=' . urlencode('Transaction not found or not completed'));
        exit;
    }
    
    // Clear the cart from session (if stored there)
    if (isset($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }
    
} catch (PDOException $e) {
    file_put_contents('errors.log', "success.php error: " . $e->getMessage() . "\n", FILE_APPEND);
    header('Location: checkout.php?error=' . urlencode('Database error'));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - YUSAI Brand</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
       :root {
            --primary-color: #2c7be5;
            --secondary-color: #00d97e;
            --dark-color: #12263f;
            --light-color: #f9fbfd;
            --success-color: #28a745;
            --border-radius: 8px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ed 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            margin: 0;
        }

        .success-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 500px;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2.5rem;
        }

        .success-title {
            font-size: 2rem;
            color: var(--success-color);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .success-message {
            color: var(--dark-color);
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .order-details {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .order-details h2 {
            font-size: 1.25rem;
            color: var(--dark-color);
            margin-bottom: 1rem;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .detail-label {
            font-weight: 500;
            color: #4a5568;
        }

        .detail-value {
            font-weight: 600;
            color: var(--dark-color);
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            width: 100%;
            max-width: 200px;
            margin: 0 auto;
        }

        .btn-home:hover {
            background: #1c65c7;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-home i {
            margin-right: 0.5rem;
        }

        /* Decorative elements */
        .success-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--success-color);
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .success-container {
                padding: 1.5rem;
            }
            
            .success-title {
                font-size: 1.75rem;
            }
        }
    </style>
    <script>
        // Clear the localStorage cart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Clear the cart from localStorage
            localStorage.removeItem('cart');
            
            // Optional: Notify other tabs that cart was cleared
            window.dispatchEvent(new Event('storage'));
            
            // Optional: Redirect to cart page with success parameter
            // window.location.href = 'cart.php?payment_success=1';
        });
    </script>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h1 class="success-title">Payment Successful!</h1>
        <p class="success-message">Thank you for your purchase, <?php echo htmlspecialchars($order['name']); ?>! Your order is being processed.</p>
        
        <div class="order-details">
            <h2>Order Details</h2>
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($orderId); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($transaction['mpesa_receipt_number']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount Paid:</span>
                <span class="detail-value">KES <?php echo number_format($transaction['amount'], 2); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value"><?php echo htmlspecialchars($transaction['created_at']); ?></span>
            </div>
        </div>
        
        <a href="../index.php" class="btn-home">
            <i class="fas fa-home"></i> Return Home
        </a>
        
        <!-- Hidden iframe to force cart refresh in some browsers -->
        <iframe src="cart.php?clear_cart=1" style="display:none;"></iframe>
    </div>
</body>
</html>