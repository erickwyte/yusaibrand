<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
   <!-- PWA / Mobile Optimization -->
  <meta name="theme-color" content="#2d6a4f">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Yusai Brand">
  <meta name="application-name" content="Yusai Brand">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="format-detection" content="telephone=no">
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <title>YUSAI Energy</title>
  <style>
      :root {
    --primary-color: #12263f;
    --secondary-color: #4caf50;
    --accent-color: #1976d2;
    --error-color: #f44336;
    --text-color: #333;
    --bg-color: #ffffff;
    --shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    --transition: all 0.3s ease;
  }

  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    font-family: 'Poppins', 'Roboto', sans-serif;
  }

  header {
    background-color: var(--bg-color);
    box-shadow: var(--shadow);
    padding: 15px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    width: 100%;
  }

  .header-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
    position: relative;
  }

  .logo {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .logo i {
    color: var(--error-color);
    font-size: 28px;
  }

  .logo h1 {
    font-size: 22px;
    font-weight: 700;
    color: var(--primary-color);
  }

  .logo span {
    color: var(--secondary-color);
  }

  .logo a {
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  nav ul {
    display: flex;
    list-style: none;
    gap: 25px;
  }

  nav ul li a {
    color: var(--text-color);
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    padding: 8px 0;
    position: relative;
  }

  nav ul li a:hover {
    color: var(--secondary-color);
  }

  nav ul li a::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--secondary-color);
    transition: var(--transition);
  }

  nav ul li a:hover::after {
    width: 100%;
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 20px;
  }
  
  .logout-btn-in-li{
  display:none;
}

  .cart-icon {
    position: relative;
    color: var(--accent-color);
    font-size: 20px;
    cursor: pointer;
    transition: var(--transition);
  }

  .cart-icon:hover {
    transform: scale(1.1);
  }

  .cart-count {
    position: absolute;
    top: -8px;
    right: -10px;
    background-color: var(--error-color);
    color: white;
    font-size: 12px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    text-align: center;
    line-height: 18px;
    font-weight: bold;
  }

  .menu-toggle {
    display: none;
    flex-direction: column;
    cursor: pointer;
    gap: 5px;
    padding: 5px;
  }

  .menu-toggle span {
    height: 3px;
    width: 25px;
    background: var(--primary-color);
    border-radius: 2px;
    transition: var(--transition);
  }

  .menu-toggle.active span:nth-child(1) {
    transform: rotate(45deg) translate(5px, 5px);
  }

  .menu-toggle.active span:nth-child(2) {
    opacity: 0;
  }

  .menu-toggle.active span:nth-child(3) {
    transform: rotate(-45deg) translate(5px, -5px);
  }

  /* ========== Mobile Slide Menu ========== */
  @media (max-width: 790px) {
    nav {
      position: fixed;
      top: 0;
      right: -100%;
      width: 60%;
      height: 100vh;
      background: var(--bg-color);
      box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
      transition: right 0.3s ease;
      z-index: 2000;
      padding-top: 80px;
    }
.logout-btn-in-li{
  display:block;
}
    nav ul {
      flex-direction: column;
      gap: 20px;
      padding-left: 30px;
    }

    nav.active {
      right: 0;
    } 

    .logout-btn{
    display:none;
  }


    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.3);
      z-index: 100;
      display: none;
    }

    .overlay.show {
      display: block;
    }

    .menu-toggle {
      display: flex;
    }
  }

  </style>
</head>
<body>
<header>
  <div class="header-container">
    <div class="logo">
      <a href="index.php">
        <i class="fas fa-fire"></i>
        <h1>YUSAI<span>BRAND</span></h1>
      </a>
    </div>

   

    <nav>
      <ul>
        <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="../products.php"><i class="fas fa-box-open"></i> Products</a></li>
        <li><a href="../black-market.php"> <i class="fas fa-skull-crossbones"></i> Black Market</a></li>
        <li><a href="../profile.php"><i class="fas fa-user"></i> Profile</a></li>
        <li><a href="../contact_us.php"><i class="fas fa-phone"></i> Contact Us</a></li>
         <?php if (isset($_SESSION['user_id'])): ?>
  <form action="../logout.php" method="POST" style="margin: 0;">
    <button class="logout-btn-in-li" type="submit" style="padding: 8px 12px; background: #fe1302ff; color: white; border: none; border-radius: 4px; cursor: pointer;">
      <i class="fas fa-sign-out-alt"></i> Logout
    </button>
  </form>
<?php endif; ?>
      </ul>
    </nav>

     <div class="header-right">
  <a href="cart.php" class="cart-icon">
    <i class="fas fa-shopping-cart"></i>
    <div class="cart-count" id="cart-count">0</div>
  </a>

 <?php if (isset($_SESSION['user_id'])): ?>
  <form action="logout.php" method="POST" style="margin: 0;">
    <button class="logout-btn" type="submit" style="padding: 8px 12px; background: #fe1302ff; color: white; border: none; border-radius: 4px; cursor: pointer;">
      <i class="fas fa-sign-out-alt"></i> Logout
    </button>
  </form>
<?php endif; ?>


  <div class="menu-toggle" onclick="toggleMenu()">
    <span></span><span></span><span></span>
  </div>
</div>

  </div>


</header>

<script>
  function toggleMenu() {
    const nav = document.querySelector("nav");
    const menuToggle = document.querySelector(".menu-toggle");
    const overlay = document.querySelector(".overlay");

    nav.classList.toggle("active");
    menuToggle.classList.toggle("active");
    overlay.classList.toggle("show");
  }

  document.addEventListener("DOMContentLoaded", function () {
    const overlay = document.createElement("div");
    overlay.classList.add("overlay");
    overlay.addEventListener("click", toggleMenu);
    document.body.appendChild(overlay);

    // Cart update
    function updateCartCount() {
      const cart = JSON.parse(localStorage.getItem('cart')) || [];
      const count = cart.reduce((sum, item) => sum + item.quantity, 0);
      document.getElementById('cart-count').textContent = count;
    }

    updateCartCount();

    window.addEventListener('storage', function (event) {
      if (event.key === 'cart') {
        updateCartCount();
      }
    });
  });
</script>
</body>
</html>