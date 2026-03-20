<?php
// cart.php
session_start();
require_once 'db.php';

// Initialize $conn from db.php
global $conn;

// Check if payment was completed (this would be set after successful payment)
if (isset($_SESSION['payment_completed']) && $_SESSION['payment_completed'] === true) {
    // Clear the cart from localStorage using JavaScript
    echo "<script>localStorage.removeItem('cart'); localStorage.removeItem('blackMarketCart');</script>";
    // Unset the session variable
    unset($_SESSION['payment_completed']);
}

// Function to check if payment was completed (alternative approach)
function checkPaymentCompletion($dbConnection) {
    if (isset($_SESSION['user_id']) && $dbConnection) {
        $userId = $_SESSION['user_id'];
        $query = "SELECT t.status FROM transactions t 
                 JOIN orders o ON t.order_id = o.id 
                 WHERE o.user_id = ? AND t.status = 'completed' 
                 ORDER BY t.created_at DESC LIMIT 1";
        $stmt = $dbConnection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['status'] === 'completed') {
                echo "<script>localStorage.removeItem('cart'); localStorage.removeItem('blackMarketCart');</script>";
            }
        }
    }
}

// Call the function to check payment status with the connection
checkPaymentCompletion($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Cart - YUSAI</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Favicons -->
  <link rel="icon" type="image/png" href="my-favicon/favicon-96x96.png" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="my-favicon/favicon.svg" />
  <link rel="shortcut icon" href="my-favicon/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="my-favicon/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-title" content="yusaibrand" />
  <link rel="manifest" href="my-favicon/site.webmanifest" />

  <style>
    :root {
      --primary-color: #2c7be5;
      --secondary-color: #00d97e;
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
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: var(--dark-color);
      background-color: #f5f7fa;
    }

    .cart-container {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 0.5rem;
    }

    .cart-container h2 {
      font-size: 2.2rem;
      color: var(--primary-color);
      margin-bottom: 2rem;
      text-align: center;
      position: relative;
      padding-bottom: 1rem;
    }

    .cart-container h2::after {
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

    .cart-list {
      margin-bottom: 2rem;
    }

    .cart-list > p {
      text-align: center;
      padding: 3rem;
      color: var(--gray-color);
      font-size: 1.1rem;
    }

    .cart-item {
      display: flex;
      background: white;
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: var(--box-shadow);
      transition: var(--transition);
      align-items: center;
    }

    .cart-item:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .cart-item img {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: var(--border-radius);
      margin-right: 1.5rem;
    }

    .cart-item > div {
      flex: 1;
    }

    .cart-item h4 {
      font-size: 1.2rem;
      margin-bottom: 0.5rem;
      color: var(--dark-color);
    }

    .cart-item p {
      color: var(--primary-color);
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .cart-item .qty {
      width: 70px;
      padding: 0.5rem;
      border: 1px solid #e2e8f0;
      border-radius: var(--border-radius);
      margin-right: 1rem;
      text-align: center;
    }

    .btn-remove {
      background: #f8d7da;
      color: #721c24;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: var(--border-radius);
      cursor: pointer;
      transition: var(--transition);
      font-weight: 500;
    }

    .btn-remove:hover {
      background: #f1b0b7;
    }

    .btn-remove i {
      margin-right: 5px;
    }

    .cart-summary {
      background: white;
      border-radius: var(--border-radius);
      padding: 2rem;
      box-shadow: var(--box-shadow);
      text-align: right;
    }

    .cart-summary h3 {
      font-size: 1.5rem;
      margin-bottom: 1.5rem;
      color: var(--dark-color);
    }

    #cart-total {
      color: var(--primary-color);
      font-weight: 700;
    }

    .btn-checkout {
      display: inline-block;
      background: var(--primary-color);
      color: white;
      padding: 0.75rem 2rem;
      border-radius: var(--border-radius);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .btn-checkout:hover {
      background: #1c65c7;
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    /* Payment success message */
    .payment-success {
      background: #d4edda;
      color: #155724;
      padding: 1rem;
      border-radius: var(--border-radius);
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .payment-success i {
      font-size: 1.2rem;
    }

    /* Responsive styles */
    @media (max-width: 768px) {
      .cart-item {
        flex-direction: column;
        text-align: center;
      }

      .cart-item img {
        margin-right: 0;
        margin-bottom: 1rem;
        width: 150px;
        height: 150px;
      }

      .cart-item > div {
        width: 100%;
      }

      .qty, .btn-remove {
        width: 100%;
        margin: 0.5rem 0;
      }
    }

    @media (max-width: 480px) {
      .cart-container h2 {
        font-size: 1.8rem;
      }

      .cart-summary {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
<?php 
// Check if header exists before including
if(file_exists('include/header.php')) {
    include 'include/header.php'; 
} else {
    echo "<!-- Header file not found -->";
}
?>

<div class="cart-container">
  <h2>Your Shopping Cart</h2>
  
  <?php if (isset($_SESSION['payment_completed']) && $_SESSION['payment_completed'] === true): ?>
    <div class="payment-success">
      <i class="fas fa-check-circle"></i>
      <span>Payment completed successfully! Your cart has been cleared.</span>
    </div>
    <?php unset($_SESSION['payment_completed']); ?>
  <?php endif; ?>
  
  <div class="cart-list"></div>
  <div class="cart-summary">
    <h3>Total: KSh <span id="cart-total">0.00</span></h3>
    <a href="transactions/checkout.php" class="btn-checkout">Proceed to Checkout</a>
  </div>
</div>

<script>
// Function to get unified cart from both sources
function getUnifiedCart() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const blackMarketCart = JSON.parse(localStorage.getItem('blackMarketCart')) || [];
    
    // Add source indicators if not present
    const cartWithSource = cart.map(item => ({
        ...item,
        fromBlackMarket: item.fromBlackMarket || false,
        source: item.source || 'regular'
    }));
    
    const blackMarketWithSource = blackMarketCart.map(item => ({
        ...item,
        fromBlackMarket: item.fromBlackMarket || true,
        source: item.source || 'black_market'
    }));
    
    // Return combined array
    return [...blackMarketWithSource, ...cartWithSource];
}

// Function to load cart from localStorage
function loadCart() {
    const unifiedCart = getUnifiedCart();
    const list = document.querySelector('.cart-list');
    list.innerHTML = '';

    let total = 0;

    if (unifiedCart.length === 0) {
        list.innerHTML = `
          <p>
            <i class="fas fa-shopping-cart" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
            Your cart is empty.
          </p>
        `;
        document.getElementById('cart-total').textContent = '0.00';
        updateCartCount();
        return;
    }

    unifiedCart.forEach((item, index) => {
        const subtotal = item.quantity * item.price;
        total += subtotal;
        
        // Determine correct image path
        let imagePath = 'images/default-product.jpg';
        if (item.image) {
            // Check if it's a full path or just filename
            if (item.image.includes('uploads/') || item.image.includes('http')) {
                imagePath = item.image;
            } else {
                imagePath = 'uploads/products/' + item.image;
            }
        }
        
        // Add source badge
        const sourceBadge = item.source === 'black_market' ? 
            ' <span style="font-size: 0.7rem; color: #666; background: #f0f0f0; padding: 2px 6px; border-radius: 3px; margin-left: 5px;">BM</span>' : 
            '';

        list.innerHTML += `
          <div class="cart-item">
            <img src="${imagePath}" alt="${item.name}" loading="lazy" onerror="this.src='images/default-product.jpg'">
            <div>
              <h4>${item.name}${sourceBadge}</h4>
              <p>KSh ${item.price.toFixed(2)}</p>
              <div style="display: flex; align-items: center; gap: 1rem;">
                <input type="number" min="1" value="${item.quantity}" data-index="${index}" data-source="${item.source}" class="qty">
                <button data-index="${index}" data-source="${item.source}" class="btn-remove">
                  <i class="fas fa-trash-alt"></i> Remove
                </button>
              </div>
            </div>
          </div>
        `;
    });

    document.getElementById('cart-total').textContent = total.toFixed(2);
    addEventListeners();
    updateCartCount();
}

// Add event listeners for quantity changes and remove buttons
function addEventListeners() {
    document.querySelectorAll('.qty').forEach(input => {
        input.addEventListener('change', e => {
            const index = parseInt(e.target.dataset.index);
            const source = e.target.dataset.source;
            const newQuantity = parseInt(e.target.value);
            
            if (newQuantity < 1) {
                e.target.value = 1;
                return;
            }
            
            if (source === 'black_market') {
                let blackMarketCart = JSON.parse(localStorage.getItem('blackMarketCart')) || [];
                if (blackMarketCart[index]) {
                    blackMarketCart[index].quantity = newQuantity;
                    localStorage.setItem('blackMarketCart', JSON.stringify(blackMarketCart));
                }
            } else {
                let cart = JSON.parse(localStorage.getItem('cart')) || [];
                if (cart[index]) {
                    cart[index].quantity = newQuantity;
                    localStorage.setItem('cart', JSON.stringify(cart));
                }
            }
            
            loadCart();
        });
    });

    document.querySelectorAll('.btn-remove').forEach(button => {
        button.addEventListener('click', e => {
            const index = parseInt(e.target.dataset.index);
            const source = e.target.dataset.source;
            
            if (source === 'black_market') {
                let blackMarketCart = JSON.parse(localStorage.getItem('blackMarketCart')) || [];
                blackMarketCart.splice(index, 1);
                localStorage.setItem('blackMarketCart', JSON.stringify(blackMarketCart));
            } else {
                let cart = JSON.parse(localStorage.getItem('cart')) || [];
                cart.splice(index, 1);
                localStorage.setItem('cart', JSON.stringify(cart));
            }
            
            loadCart();
        });
    });
}

// Helper function to update cart count in header
function updateCartCount() {
    const unifiedCart = getUnifiedCart();
    const totalItems = unifiedCart.reduce((sum, item) => sum + (item.quantity || 0), 0);
    
    // Update cart count in header
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = totalItems;
    });
}

// Check URL for payment success parameter
function checkPaymentSuccess() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('payment_success')) {
        // Clear both carts
        localStorage.removeItem('cart');
        localStorage.removeItem('blackMarketCart');
        
        // Show success message
        const list = document.querySelector('.cart-list');
        list.innerHTML = `
          <div class="payment-success">
            <i class="fas fa-check-circle"></i>
            <span>Payment completed successfully! Your cart has been cleared.</span>
          </div>
          <p>
            <i class="fas fa-shopping-cart" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
            Your cart is empty.
          </p>
        `;
        document.getElementById('cart-total').textContent = '0.00';
        updateCartCount();
        
        // Remove the parameter from URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// Initialize the page
document.addEventListener('DOMContentLoaded', () => {
    checkPaymentSuccess();
    loadCart();
    
    // Listen for storage events from other tabs
    window.addEventListener('storage', (event) => {
        if (event.key === 'cart' || event.key === 'blackMarketCart') {
            console.log('Cart updated in another tab, reloading...');
            loadCart();
        }
    });
});

// Optional: Add this function if you want to migrate old cart data
function migrateOldCartData() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const blackMarketCart = JSON.parse(localStorage.getItem('blackMarketCart')) || [];
    
    let needsMigration = false;
    
    // Migrate main cart
    const migratedCart = cart.map(item => {
        if (!item.hasOwnProperty('source')) {
            needsMigration = true;
            return {
                ...item,
                fromBlackMarket: false,
                source: 'regular'
            };
        }
        return item;
    });
    
    // Migrate black market cart
    const migratedBlackMarketCart = blackMarketCart.map(item => {
        if (!item.hasOwnProperty('source')) {
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