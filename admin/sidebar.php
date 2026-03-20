<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication guard
if (!isset($_SESSION['user_id'])) { 
    header('Location: ../log_in.php');
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../access_denied.php');
    exit;
}

$currentAdminPath = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  :root {
    --primary-color: #e74c3c;
    --primary-dark: #c0392b;
    --text-color: #2c3e50;
    --light-gray: #f5f6fa;
    --dark-gray: #7f8c8d;
    --white: #ffffff;
    --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  }

  .header {
    background: var(--primary-color);
    color: var(--white);
    padding: 15px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    box-shadow: var(--shadow);
  }

  .menu-btn {
    background: none;
    border: none;
    color: var(--white);
    font-size: 1.5rem;
    cursor: pointer;
    display: none;
    transition: transform 0.3s ease;
  }
  .menu-btn:hover { transform: scale(1.05); }

  .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.5rem;
    font-weight: bold;
  }
  .logo i { font-size: 1.8rem; color: #ffd700; }

  /* Sidebar styles */
  .sidebar {
    width: 250px;
    background: var(--primary-color);
    color: var(--white);
    height: 100vh;
    padding: 20px 0;
    position: fixed;
    left: 0;
    top: 48px;
    transition: all 0.3s ease;
    z-index: 900;
  }

  .sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px 15px;
    margin-bottom: 5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .close-sidebar {
    background: none;
    border: none;
    color: var(--white);
    font-size: 1.5rem;
    cursor: pointer;
    display: none;
    transition: transform 0.3s ease;
  }
  .close-sidebar:hover { transform: rotate(90deg); }

  .sidebar ul { list-style: none; padding: 0; }

  .sidebar ul li {
    padding: 15px 20px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
  }
  .sidebar ul li::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.15);
    transition: left 0.3s ease;
    z-index: -1;
  }
  .sidebar ul li:hover::before,
  .sidebar ul li.active::before { left: 0; }

  .sidebar a {
    text-decoration: none;
    color: var(--white);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1rem;
  }

  .sidebar i { width: 24px; text-align: center; font-size: 1.2rem; }

  @media (max-width: 768px) {
    .menu-btn { display: block; }
    .sidebar { transform: translateX(-100%); top: 0; z-index: 1000; }
    .sidebar.show { transform: translateX(0); box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2); }
    .close-sidebar { display: block; }
    .main-content { margin-left: 0; width: 100%; }
  }
</style>

<header class="header">
  <button class="menu-btn" id="menuToggle" aria-label="Toggle sidebar" aria-expanded="false">
    <i class="fas fa-bars"></i>
  </button>
  <div class="logo">
    <i class="fas fa-fire" aria-hidden="true"></i>
    <span>YUSAI Admin</span>
  </div>
  <div class="date"><?php echo date('F j, Y'); ?></div>
</header>

<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <h2></h2>
    <button class="close-sidebar" id="closeSidebar" aria-label="Close sidebar">
      <i class="fas fa-times"></i>
    </button>
  </div>
  
  <ul>
    <li>
      <a data-page="admin_dashboard.php" href="admin_dashboard.php">
        <i class="fas fa-tachometer-alt" aria-hidden="true"></i> Dashboard
      </a>
    </li>
    <li>
      <a data-page="admin_products.php" href="admin_products.php">
        <i class="fas fa-box" aria-hidden="true"></i> Products
      </a>
    </li>
    <li>
      <a data-page="black-market-admin.php" href="black-market-admin.php">
        <i class="fas fa-box" aria-hidden="true"></i> Black Market
      </a>
    </li>
    <li>
      <a data-page="manage-black-market-admins.php" href="manage-black-market-admins.php">
        <i class="fas fa-users-cog" aria-hidden="true"></i> Black Market Admins
      </a>
    </li>
    <li>
      <a data-page="slideshow-admin.php" href="slideshow-admin.php">
        <i class="fas fa-images" aria-hidden="true"></i> Slideshow
      </a>
    </li>
    <li>
      <a data-page="deliveries.php" href="deliveries.php">
        <i class="fas fa-truck" aria-hidden="true"></i> Deliveries
      </a>
    </li>
    <li>
      <a data-page="referral_management.php" href="referral_management.php">
        <i class="fas fa-users" aria-hidden="true"></i> Referrals
      </a>
    </li>
    <li>
      <a data-page="sell_requests.php" href="sell_requests.php">
        <i class="fas fa-handshake" aria-hidden="true"></i> Sell Requests
      </a>
    </li>
    <li>
      <a data-page="admin_subscribers.php" href="admin_subscribers.php">
        <i class="fas fa-envelope-open" aria-hidden="true"></i> User Emails
      </a>
    </li>
    <li>
      <a data-page="admin_contact_messages.php" href="admin_contact_messages.php">
        <i class="fas fa-comments" aria-hidden="true"></i> User Messages
      </a>
    </li>
    <li>
      <a data-page="manage_admin_emails.php" href="manage_admin_emails.php">
        <i class="fas fa-envelope" aria-hidden="true"></i> Admin Emails
      </a>
    </li>
    <li>
      <a data-page="../index.php" href="../index.php">
        <i class="fas fa-globe" aria-hidden="true"></i> Website
      </a>
    </li>
    <li>
      <a data-page="logout.php" href="logout.php">
        <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout
      </a>
    </li>
  </ul>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menuToggle');
    const closeSidebar = document.getElementById('closeSidebar');
    const sidebar = document.getElementById('sidebar');

    // Toggle sidebar visibility
    menuToggle.addEventListener('click', function() {
      const isOpen = sidebar.classList.toggle('show');
      menuToggle.setAttribute('aria-expanded', isOpen);
    });

    // Close sidebar
    closeSidebar.addEventListener('click', function() {
      sidebar.classList.remove('show');
      menuToggle.setAttribute('aria-expanded', 'false');
    });

    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
      const isClickInsideSidebar = sidebar.contains(event.target);
      const isMenuButton = event.target === menuToggle || menuToggle.contains(event.target);
      
      if (sidebar.classList.contains('show') && !isClickInsideSidebar && !isMenuButton) {
        sidebar.classList.remove('show');
        menuToggle.setAttribute('aria-expanded', 'false');
      }
    });

    // Active state by current page
    const current = "<?php echo htmlspecialchars($currentAdminPath, ENT_QUOTES, 'UTF-8'); ?>";
    document.querySelectorAll('.sidebar a[data-page]').forEach(link => {
      const parent = link.closest('li');
      const isMatch = link.dataset.page === current;
      if (parent) {
        parent.classList.toggle('active', isMatch);
        if (isMatch) link.setAttribute('aria-current', 'page');
      }
    });

    // Close on small screen after click
    document.querySelectorAll('.sidebar li').forEach(item => {
      item.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
          sidebar.classList.remove('show');
          menuToggle.setAttribute('aria-expanded', 'false');
        }
      });
    });
  });
</script>
