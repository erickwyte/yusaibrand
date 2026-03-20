<?php
// checkout.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    $redirect = str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME']);
    $redirect = ltrim($redirect, '/');
    header("Location: /log_in.php?redirect=" . urlencode($redirect));
    exit;
}

$userId = $_SESSION['user_id'];
$user = getUserById($pdo, $userId);
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
$error_message = $_GET['error'] ?? null;
$success_message = $_GET['success'] ?? null;

// --- HANDLE AJAX POLL FOR TRANSACTION STATUS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['check_status'])) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents("php://input"), true);
    $checkoutRequestId = $input['checkoutRequestId'] ?? '';

    if (!$checkoutRequestId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing CheckoutRequestID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT status, order_id FROM transactions WHERE checkout_request_id = ?");
        $stmt->execute([$checkoutRequestId]);
        $transaction = $stmt->fetch();

        if ($transaction) {
            echo json_encode([
                'status' => $transaction['status'],
                'order_id' => $transaction['order_id'],
                'message' => $transaction['status'] === 'completed'
                    ? 'Payment successful! Redirecting...'
                    : ($transaction['status'] === 'failed'
                        ? 'Payment failed. Please try again.'
                        : 'Awaiting payment confirmation...')
            ]);
        } else {
            echo json_encode(['status' => 'pending', 'message' => 'Transaction not found yet. Still processing...']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
    }
    exit;
}

// --- Extract CheckoutRequestID from success_message for polling ---
$orderId = '';
$checkoutRequestId = '';
if ($success_message && strpos($success_message, 'CheckoutRequestID') !== false) {
    $checkoutRequestId = explode('CheckoutRequestID: ', $success_message)[1] ?? '';
    try {
        $stmt = $pdo->prepare("SELECT order_id FROM transactions WHERE checkout_request_id = ?");
        $stmt->execute([$checkoutRequestId]);
        $transaction = $stmt->fetch();
        $orderId = $transaction['order_id'] ?? '';
    } catch (PDOException $e) {
        file_put_contents('errors.log', "checkout.php: " . $e->getMessage() . "\n", FILE_APPEND);
        $error_message = 'Error fetching transaction details.';
    }
}
?>

<?php
function formatPhoneNumber($phone) {
    if (strpos($phone, '07') === 0) {
        return '254' . substr($phone, 1);
    }
    return $phone;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - YUSAI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c7be5;
            --secondary-color: #00d97e;
            --error-color: #e44d26;
            --success-color: #28a745;
            --pending-color: #ffc107;
            --dark-color: #12263f;
            --light-color: #f9fbfd;
            --gray-color: #95aac9;
            --border-radius: 8px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .checkout-container {
            max-width: 700px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .checkout-container h2 {
            font-size: 2.2rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
        }

        .checkout-container h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--secondary-color);
            border-radius: 2px;
        }

        .checkout-container h3 {
            font-size: 1.5rem;
            color: var(--dark-color);
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .error-message {
            color: white;
            background-color: var(--error-color);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }

        .success-message {
            color: white;
            background-color: var(--success-color);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }

        #cart-summary {
            margin-bottom: 1.5rem;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            margin-bottom: 0.75rem;
            background: var(--light-color);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .cart-item:hover {
            background: #e6f0ff;
        }

        #cart-total {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            display: inline-block;
            margin-left: 0.5rem;
        }

        #status-message {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: var(--border-radius);
            text-align: center;
            background: #f8f9fa;
            font-weight: 500;
            display: block;
        }

        .status-completed {
            background-color: var(--success-color);
            color: white;
        }

        .status-pending {
            background-color: var(--pending-color);
            color: var(--dark-color);
        }

        .status-failed {
            background-color: var(--error-color);
            color: white;
        }

        .status-error {
            background-color: var(--error-color);
            color: white;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.2);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn-submit {
            display: block;
            width: 100%;
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1.5rem;
            text-align: center;
        }

        .btn-submit:hover {
            background: #1c65c7;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .btn-submit:disabled {
            background: var(--gray-color);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-submit i {
            margin-right: 0.5rem;
        }

        /* Loading spinner */
        .spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .checkout-container {
                padding: 1.5rem;
                margin: 1rem;
            }

            .checkout-container h2 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 480px) {
            .checkout-container {
                padding: 1rem;
            }

            .checkout-container h2 {
                font-size: 1.5rem;
            }

            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
<?php include 'include/header.php'; ?>

<div class="checkout-container">
    <h2>Checkout</h2>
    <?php if ($error_message): ?>
        <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <p class="success-message"><?= htmlspecialchars($success_message) ?></p>
    <?php endif; ?>

    <h3>Order Summary</h3>
    <div id="cart-summary"></div>
    <h3>Total: KSh <span id="cart-total">0.00</span></h3>
    <div id="status-message"></div>

    <form id="checkout-form" action="stk_push.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone Number (2547XXXXXXXX)</label>
            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars(formatPhoneNumber($user['phone'] ?? '')) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="address">Delivery Address</label>
            <textarea id="address" name="address" placeholder="e.g., Greenfield Apartments, Door 4B, Riverbank Rd, Kisumu Town" required></textarea>
        </div>

        <div class="form-group">
            <label for="notes">Order Notes (Optional)</label>
            <textarea id="notes" name="notes"></textarea>
        </div>
        <input type="hidden" name="amount" id="amount">
        <input type="hidden" name="cart" id="cart">
        <button type="submit" class="btn-submit" id="submit-btn">
            <i class="fas fa-mobile-alt"></i> Pay with M-Pesa
        </button>
    </form>
</div>

<script>
// Function to merge carts and get unified cart data
function getUnifiedCart() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const blackMarketCart = JSON.parse(localStorage.getItem('blackMarketCart')) || [];
    
    console.log('Main cart:', cart);
    console.log('Black market cart:', blackMarketCart);
    
    // Add source indicator to each cart
    const cartWithSource = cart.map(item => ({
        ...item,
        fromBlackMarket: false,
        source: 'regular'
    }));
    
    const blackMarketWithSource = blackMarketCart.map(item => ({
        ...item,
        fromBlackMarket: true,
        source: 'black_market'
    }));
    
    // Merge carts - black market items first
    const unifiedCart = [...blackMarketWithSource, ...cartWithSource];
    
    console.log('Unified cart with source:', unifiedCart);
    return unifiedCart;
}

function loadCart() {
    const unifiedCart = getUnifiedCart();
    const summary = document.getElementById('cart-summary');
    const totalEl = document.getElementById('cart-total');
    const amountInput = document.getElementById('amount');
    const cartInput = document.getElementById('cart');
    let total = 0;

    summary.innerHTML = ''; // Clear previous content

    if (unifiedCart.length === 0) {
        summary.innerHTML = '<p>Your cart is empty.</p>';
        document.getElementById('submit-btn').disabled = true;
        return;
    }

    unifiedCart.forEach((item, index) => {
        const subtotal = item.quantity * item.price;
        total += subtotal;
        
        // Add source indicator in display
        const sourceBadge = item.source === 'black_market' ? ' <span style="font-size: 0.8rem; color: #666; background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">BM</span>' : '';
        
        summary.innerHTML += `
            <div class="cart-item">
                <span>${item.name}${sourceBadge} (x${item.quantity})</span>
                <span>KSh ${(subtotal).toFixed(2)}</span>
            </div>
        `;
    });

    totalEl.textContent = total.toFixed(2);
    amountInput.value = total;
    
    // Prepare cart data for submission - UPDATED with source field
    const cartDataForSubmission = unifiedCart.map(item => ({
        id: item.id || item.product_id || 0,
        product_id: item.id || item.product_id || 0,
        name: item.name || item.product_name || 'Unknown Product',
        price: parseFloat(item.price) || 0,
        quantity: parseInt(item.quantity) || 1,
        image: item.image || item.product_image || 'default-product.jpg',
        // CRITICAL: Add source field for backend validation
        source: item.source || (item.fromBlackMarket ? 'black_market' : 'regular')
    }));
    
    console.log('Cart data for submission WITH SOURCE:', cartDataForSubmission);
    cartInput.value = JSON.stringify(cartDataForSubmission);
}

document.getElementById('checkout-form').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent default to see what's being sent
    
    const phone = document.getElementById('phone').value;
    if (!phone.match(/^2547\d{8}$/)) {
        alert('Please enter a valid phone number starting with 2547 and 10 digits total.');
        return false;
    }
    
    const unifiedCart = getUnifiedCart();
    if (unifiedCart.length === 0) {
        alert('Your cart is empty. Please add items before checking out.');
        return false;
    }
    
    // Validate each item has required fields
    const invalidItems = unifiedCart.filter(item => {
        return !item.id || !item.name || !item.price || !item.quantity;
    });
    
    if (invalidItems.length > 0) {
        console.error('Invalid cart items:', invalidItems);
        alert('Some items in your cart have missing information. Please refresh the page and try again.');
        return false;
    }
    
    // Show what's being submitted
    console.log('Form data being submitted:');
    console.log('Name:', document.getElementById('name').value);
    console.log('Phone:', document.getElementById('phone').value);
    console.log('Address:', document.getElementById('address').value);
    console.log('Amount:', document.getElementById('amount').value);
    console.log('Cart JSON:', document.getElementById('cart').value);
    
    // Enable loading state
    document.getElementById('submit-btn').disabled = true;
    document.getElementById('submit-btn').innerHTML = '<div class="spinner"></div> Processing...';
    
    // Submit the form after a brief delay to see logs
    setTimeout(() => {
        console.log('Submitting form...');
        this.submit();
    }, 100);
});

<?php if ($checkoutRequestId && $orderId): ?>
const checkoutRequestId = '<?= htmlspecialchars($checkoutRequestId) ?>';
const orderId = '<?= htmlspecialchars($orderId) ?>';

console.log('Starting polling for CheckoutRequestID:', checkoutRequestId);

async function pollStatus() {
    try {
        const statusMessage = document.getElementById('status-message');
        statusMessage.style.display = 'block';
        statusMessage.className = 'status-pending';
        statusMessage.innerHTML = '<div class="spinner"></div> Checking payment status...';

        console.log('Sending polling request for:', checkoutRequestId);
        
        const res = await fetch('checkout.php?check_status=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ checkoutRequestId: checkoutRequestId })
        });
        
        console.log('Poll response status:', res.status);
        
        if (!res.ok) {
            throw new Error('Network response was not ok: ' . res.status);
        }
        
        const result = await res.json();
        console.log('Poll result:', result);
        
        if (result.status === 'completed') {
            statusMessage.className = 'status-completed';
            statusMessage.innerHTML = result.message;
            
            console.log('Payment completed! Redirecting to success page...');
            
            // Clear both carts after successful payment
            localStorage.removeItem('cart');
            localStorage.removeItem('blackMarketCart');
            
            // Update cart count in header
            updateCartCount();
            
            // Redirect to success page after 2 seconds
            setTimeout(() => {
                window.location.href = `success.php?order_id=${result.order_id}&transaction_id=${checkoutRequestId}`;
            }, 2000);
            return; // Stop polling
            
        } else if (result.status === 'failed') {
            statusMessage.className = 'status-failed';
            statusMessage.textContent = result.message;
            document.getElementById('submit-btn').disabled = false;
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-mobile-alt"></i> Try Again';
            return; // Stop polling
            
        } else if (result.status === 'error') {
            statusMessage.className = 'status-error';
            statusMessage.textContent = result.message;
            return; // Stop polling
            
        } else {
            // Still pending
            statusMessage.className = 'status-pending';
            statusMessage.innerHTML = '<div class="spinner"></div> ' + result.message;
            
            console.log('Still pending, polling again in 5 seconds...');
            
            // Continue polling every 5 seconds
            setTimeout(pollStatus, 5000);
        }
        
    } catch (err) {
        console.error('Polling error:', err);
        const statusMessage = document.getElementById('status-message');
        statusMessage.style.display = 'block';
        statusMessage.className = 'status-error';
        statusMessage.textContent = 'Error checking payment status. Please refresh the page.';
        
        console.log('Retrying polling in 5 seconds due to error...');
        
        // Continue polling despite error (might be temporary network issue)
        setTimeout(pollStatus, 5000);
    }
}

// Start polling immediately
console.log('Starting polling process...');
pollStatus();
<?php else: ?>
console.log('No CheckoutRequestID found or orderId missing');
<?php endif; ?>

// Helper function to update cart count in header
function updateCartCount() {
    const unifiedCart = getUnifiedCart();
    const totalItems = unifiedCart.reduce((sum, item) => sum + item.quantity, 0);
    
    // Update cart count in header
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = totalItems;
    });
}

// Initialize cart on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCart();
    updateCartCount();
    
    // Also listen for storage events (if cart is updated in another tab)
    window.addEventListener('storage', function(event) {
        if (event.key === 'cart' || event.key === 'blackMarketCart') {
            console.log('Cart updated in another tab, reloading...');
            loadCart();
            updateCartCount();
        }
    });
});

// Add this to your existing JS or create a new function
function migrateOldCartData() {
    // Check if there's old cart data without source field and migrate it
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const blackMarketCart = JSON.parse(localStorage.getItem('blackMarketCart')) || [];
    
    let needsMigration = false;
    
    // Check main cart
    const migratedCart = cart.map(item => {
        if (!item.hasOwnProperty('fromBlackMarket')) {
            needsMigration = true;
            return {
                ...item,
                fromBlackMarket: false,
                source: 'regular'
            };
        }
        return item;
    });
    
    // Check black market cart
    const migratedBlackMarketCart = blackMarketCart.map(item => {
        if (!item.hasOwnProperty('fromBlackMarket')) {
            needsMigration = true;
            return {
                ...item,
                fromBlackMarket: true,
                source: 'black_market'
            };
        }
        return item;
    });
    
    if (needsMigration) {
        console.log('Migrating cart data to include source field...');
        localStorage.setItem('cart', JSON.stringify(migratedCart));
        localStorage.setItem('blackMarketCart', JSON.stringify(migratedBlackMarketCart));
        loadCart(); // Reload with migrated data
    }
}

// Run migration on page load
document.addEventListener('DOMContentLoaded', migrateOldCartData);
</script>
</body>
</html>