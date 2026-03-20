
<?php
// Safe session start for pages that forget it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPath = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

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

  * { margin: 0; padding: 0; box-sizing: border-box; }

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

  .skip-link {
    position: absolute;
    left: -999px;
    top: -999px;
  }
  .skip-link:focus {
    left: 12px;
    top: 12px;
    background: var(--bg-color);
    color: var(--primary-color);
    padding: 10px 14px;
    border-radius: 6px;
    box-shadow: var(--shadow);
    z-index: 1500;
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
    letter-spacing: 0.5px;
  }

  .logo span { color: var(--secondary-color); }

  .logo a {
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  nav { display: block; }

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

  nav ul li a:hover,
  nav ul li a:focus-visible,
  nav ul li a.active {
    color: var(--secondary-color);
  }

  nav ul li a::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--secondary-color);
    transition: var(--transition);
  }

  nav ul li a:hover::after,
  nav ul li a:focus-visible::after,
  nav ul li a.active::after {
    width: 100%;
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 20px;
  }

  .logout-btn,
  .logout-btn-in-li {
    padding: 8px 12px;
    background: #fe1302ff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    letter-spacing: 0.2px;
    transition: var(--transition);
  }
  .logout-btn:hover,
  .logout-btn-in-li:hover { filter: brightness(0.95); }
  .logout-btn:focus-visible,
  .logout-btn-in-li:focus-visible {
    outline: 2px solid var(--accent-color);
    outline-offset: 2px;
  }
  
  .logout-btn-in-li { display: none; }

  .cart-icon {
    position: relative;
    color: var(--accent-color);
    font-size: 20px;
    cursor: pointer;
    transition: var(--transition);
  }

  .cart-icon:hover { transform: scale(1.05); }

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

  .menu-toggle.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
  .menu-toggle.active span:nth-child(2) { opacity: 0; }
  .menu-toggle.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }

  /* ========== Mobile Slide Menu ========== */
  @media (max-width: 790px) {
    nav {
      position: fixed;
      top: 0;
      right: -100%;
      width: 65%;
      height: 100vh;
      background: var(--bg-color);
      box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
      transition: right 0.3s ease;
      z-index: 2000;
      padding: 90px 0 30px 0;
    }
    .logout-btn-in-li { display: block; }
    nav ul {
      flex-direction: column;
      gap: 20px;
      padding-left: 30px;
    }
    nav.active { right: 0; } 
    .logout-btn { display: none; }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.3);
      z-index: 1500;
      display: none;
    }
    .overlay.show { display: block; }
    .menu-toggle { display: flex; }
  }
</style>
</head>
<header role="banner">
  <div class="header-container">
    <a class="skip-link" href="#main">Skip to content</a>

    <div class="logo" aria-label="YUSAI Brand">
      <a href="index.php">
        <i class="fas fa-fire" aria-hidden="true"></i>
        <h1>YUSAI<span>BRAND</span></h1>
      </a>
    </div>

    <nav aria-label="Primary" role="navigation">
      <ul>
        <li><a data-page="index.php" href="index.php"><i class="fas fa-home" aria-hidden="true"></i> Home</a></li>
        <li><a data-page="products.php" href="products.php"><i class="fas fa-box-open" aria-hidden="true"></i> Products</a></li>
        <li><a data-page="black-market.php" href="black-market.php"><i class="fas fa-skull-crossbones" aria-hidden="true"></i> Black Market</a></li>
        <li><a data-page="profile.php" href="profile.php"><i class="fas fa-user" aria-hidden="true"></i> Profile</a></li>
        <li><a data-page="contact_us.php" href="contact_us.php"><i class="fas fa-phone" aria-hidden="true"></i> Contact Us</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
          <li>
            <form action="logout.php" method="POST" style="margin: 0;">
              <button class="logout-btn-in-li" type="submit" aria-label="Logout">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout
              </button>
            </form>
          </li>
        <?php endif; ?>
      </ul>
    </nav>

    <div class="header-right">
      <a href="cart.php" class="cart-icon" aria-label="Cart">
        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
        <div class="cart-count" id="cart-count">0</div>
      </a>

      <?php if (isset($_SESSION['user_id'])): ?>
        <form action="logout.php" method="POST" style="margin: 0;">
          <button class="logout-btn" type="submit" aria-label="Logout">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout
          </button>
        </form>
      <?php endif; ?>

      <button class="menu-toggle" type="button" aria-label="Toggle menu" aria-expanded="false" aria-controls="primary-nav" onclick="toggleMenu()">
        <span></span><span></span><span></span>
      </button>
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
    const isOpen = nav.classList.contains("active");
    menuToggle.setAttribute("aria-expanded", isOpen);
    if (overlay) {
      overlay.classList.toggle("show", isOpen);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (!document.querySelector(".overlay")) {
      const overlay = document.createElement("div");
      overlay.classList.add("overlay");
      overlay.addEventListener("click", toggleMenu);
      document.body.appendChild(overlay);
    }

    // Ensure skip link has a target
    const mainEl = document.getElementById('main') || document.querySelector('main') || document.querySelector('.main-content');
    if (mainEl && !mainEl.id) {
      mainEl.id = 'main';
    }

    // Active nav marker
    const current = "<?php echo htmlspecialchars($currentPath, ENT_QUOTES, 'UTF-8'); ?>";
    document.querySelectorAll('nav a[data-page]').forEach(link => {
      if (link.dataset.page === current) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      }
    });

    // Cart update
    function updateCartCount() {
      const cart = JSON.parse(localStorage.getItem('cart')) || [];
      const count = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
      const el = document.getElementById('cart-count');
      if (el) el.textContent = count;
    }
    updateCartCount();
    window.addEventListener('storage', function (event) {
      if (event.key === 'cart') updateCartCount();
    });
  });
</script>

<?php
// Lightweight organization schema for SEO; safe to include in body.
if (!defined('YUSAI_SCHEMA')) {
    define('YUSAI_SCHEMA', true);
    echo '<script type="application/ld+json">' . json_encode([
        "@context" => "https://schema.org",
        "@type" => "Organization",
        "name" => "YUSAI Brand",
        "url" => "https://yusaibrand.co.ke/",
        "logo" => "https://yusaibrand.co.ke/my-favicon/favicon-96x96.png",
        "contactPoint" => [
            [
                "@type" => "ContactPoint",
                "telephone" => "+254719122571",
                "contactType" => "customer support",
                "areaServed" => "KE",
                "availableLanguage" => ["en"]
            ]
        ],
        "sameAs" => [
            "https://www.facebook.com/share/177k5ZNzya/",
            "https://youtube.com/@yusufsaidi7996",
            "https://www.instagram.com/yusuf787209"
        ]
    ], JSON_UNESCAPED_SLASHES) . '</script>';
}
?>
