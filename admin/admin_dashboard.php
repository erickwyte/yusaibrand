<?php
require_once '../db.php';
session_start();

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

// Load admin email from session or default
$adminEmail = $_SESSION['admin_email'] ?? 'admin@yusai.com';

// Dashboard stats
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalReferrals = $pdo->query("SELECT COUNT(*) FROM referral_earnings")->fetchColumn();
$totalDeliveries = $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid' AND delivery_status = 'pending'")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalMessages = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE response IS NULL")->fetchColumn();
$totalSubscribers = $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();

// Count pending rewards
$stmt = $pdo->query("SELECT COUNT(*) AS pending_rewards FROM rewards WHERE status = 'pending'");
$pendingRewards = $stmt->fetchColumn();

// Get recent activities
$recentOrders = $pdo->query("
    SELECT o.*, u.name as user_name 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get revenue data for chart
$revenueData = $pdo->query("
    SELECT 
        DATE(created_at) as date, 
        SUM(total_amount) as revenue 
    FROM orders 
    WHERE payment_status = 'paid' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for chart
$chartLabels = [];
$chartData = [];
foreach ($revenueData as $row) {
    $chartLabels[] = date('M j', strtotime($row['date']));
    $chartData[] = (float)$row['revenue'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Yusai Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --primary-color: #e74c3c;
      --primary-light: rgba(231, 76, 60, 0.1);
      --secondary-color: #3498db;
      --success-color: #27ae60;
      --warning-color: #f39c12;
      --text-color: #2c3e50;
      --text-light: #7f8c8d;
      --bg-color: #f5f6fa;
      --card-bg: #ffffff;
      --card-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
      --navbar-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      --border-radius: 12px;
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--bg-color);
      color: var(--text-color);
      line-height: 1.6;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    .main-content {
      margin-left: 250px;
      padding: 30px;
      width: calc(100% - 250px);
      transition: margin-left 0.3s ease, width 0.3s ease;
      min-height: 100vh;
      margin-top:70px;
    }

    .navbar {
      background: var(--card-bg);
      padding: 20px 30px;
      border-radius: var(--border-radius);
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      box-shadow: var(--navbar-shadow);
      position: sticky;
      top: 20px;
      z-index: 100;
    }

    .navbar h1 {
      margin: 0;
      font-size: 1.8em;
      flex-grow: 1;
      font-weight: 700;
      color: var(--primary-color);
    }

    .admin-info {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 0.95em;
      color: var(--text-light);
    }

    .admin-info i {
      color: var(--primary-color);
    }

    .menu-toggle {
      display: none;
      background: none;
      border: none;
      color: var(--text-color);
      font-size: 24px;
      cursor: pointer;
      padding: 8px;
      border-radius: 6px;
      transition: var(--transition);
    }

    .menu-toggle:hover {
      background: var(--primary-light);
      color: var(--primary-color);
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }

    .card {
      background: var(--card-bg);
      padding: 25px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 4px;
      height: 100%;
      background: var(--primary-color);
    }

    .card h3 {
      font-size: 1.1em;
      color: var(--text-light);
      margin: 0 0 15px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .card h3 i {
      color: var(--primary-color);
    }

    .card p {
      font-size: 2.2em;
      font-weight: 700;
      color: var(--text-color);
      margin: 0;
    }

    .card .trend {
      font-size: 0.9em;
      margin-top: 10px;
      display: flex;
      align-items: center;
      gap: 5px;
      color: var(--success-color);
    }

    .card .trend.down {
      color: #e74c3c;
    }

    .card a {
      display: block;
      padding: 20px;
      margin: -20px;
    }

    .chart-container {
      background: var(--card-bg);
      padding: 25px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      margin-bottom: 30px;
    }

    .chart-container h2 {
      margin: 0 0 20px;
      font-size: 1.4em;
      color: var(--text-color);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .chart-container h2 i {
      color: var(--primary-color);
    }

    .recent-activities {
      background: var(--card-bg);
      padding: 25px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
    }

    .recent-activities h2 {
      margin: 0 0 20px;
      font-size: 1.4em;
      color: var(--text-color);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .recent-activities h2 i {
      color: var(--primary-color);
    }

    .activity-list {
      list-style: none;
    }

    .activity-item {
      padding: 15px 0;
      border-bottom: 1px solid #eee;
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .activity-item:last-child {
      border-bottom: none;
    }

    .activity-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--primary-light);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-color);
    }

    .activity-content {
      flex: 1;
    }

    .activity-title {
      font-weight: 600;
      margin-bottom: 5px;
    }

    .activity-time {
      font-size: 0.85em;
      color: var(--text-light);
    }

    .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.8em;
      font-weight: 600;
    }

    .status-paid {
      background: #e8f5e8;
      color: #27ae60;
    }

    .status-pending {
      background: #fef5e7;
      color: #f39c12;
    }

    @media (max-width: 1024px) {
      .dashboard-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      }
    }

    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        width: 100%;
        padding: 20px;
      }

      .menu-toggle {
        display: block;
      }

      .navbar {
        padding: 15px 20px;
      }

      .navbar h1 {
        font-size: 1.5em;
      }

      .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .card {
        padding: 20px;
      }

      .card p {
        font-size: 1.8em;
      }
    }

    @media (max-width: 480px) {
      .navbar {
        flex-direction: column;
        gap: 15px;
        text-align: center;
      }

      .navbar h1 {
        font-size: 1.3em;
      }

      .admin-info {
        font-size: 0.9em;
      }
    }

    /* Animation for numbers */
    @keyframes countUp {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .card p {
      animation: countUp 0.6s ease-out;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Main Content -->
  <div class="main-content">
   
    <div class="dashboard-grid">
      <div class="card">
        <a href="admin_products.php">
          <h3><i class="fas fa-box"></i> Total Products</h3>
          <p><?= htmlspecialchars($totalProducts) ?></p>
          <div class="trend">
            <i class="fas fa-arrow-up"></i> 12% from last month
          </div>
        </a>
      </div>

      <div class="card">
        <a href="deliveries.php">
          <h3><i class="fas fa-truck"></i> Pending Deliveries</h3>
          <p><?= htmlspecialchars($totalDeliveries) ?></p>
          <div class="trend">
            <i class="fas fa-clock"></i> Needs attention
          </div>
        </a>
      </div>

      <div class="card">
        <a href="referral_management.php">
          <h3><i class="fas fa-users"></i> Total Referrals</h3>
          <p><?= htmlspecialchars($totalReferrals) ?></p>
          <div class="trend">
            <i class="fas fa-arrow-up"></i> 8% growth
          </div>
        </a>
      </div>

      <div class="card">
        <a href="admin_users.php">
          <h3><i class="fas fa-user-friends"></i> Total Users</h3>
          <p><?= htmlspecialchars($totalUsers) ?></p>
          <div class="trend">
            <i class="fas fa-arrow-up"></i> 15% this month
          </div>
        </a>
      </div>

      <div class="card">
        <a href="admin_contact_messages.php">
          <h3><i class="fas fa-envelope"></i> Pending Messages</h3>
          <p><?= htmlspecialchars($totalMessages) ?></p>
          <div class="trend">
            <i class="fas fa-clock"></i> Requires response
          </div>
        </a>
      </div>

      <div class="card">
        <a href="admin_subscribers.php">
          <h3><i class="fas fa-newspaper"></i> Email Subscribers</h3>
          <p><?= htmlspecialchars($totalSubscribers) ?></p>
          <div class="trend">
            <i class="fas fa-arrow-up"></i> 22 new this week
          </div>
        </a>
      </div>

      <div class="card">
        <a href="pending_rewards.php">
          <h3><i class="fas fa-gift"></i> Pending Rewards</h3>
          <p><?= htmlspecialchars($pendingRewards) ?></p>
          <div class="trend">
            <i class="fas fa-clock"></i> Needs processing
          </div>
        </a>
      </div>

      <div class="card">
        <a href="revenue.php">
          <h3><i class="fas fa-chart-line"></i> Total Revenue</h3>
          <p>Ksh <?= number_format(array_sum($chartData), 2) ?></p>
          <div class="trend">
            <i class="fas fa-arrow-up"></i> Last 7 days
          </div>
        </a>
      </div>
    </div>

    <div class="chart-container">
      <h2><i class="fas fa-chart-bar"></i> Revenue Overview (Last 7 Days)</h2>
      <canvas id="revenueChart" height="100"></canvas>
    </div>

    <div class="recent-activities">
      <h2><i class="fas fa-history"></i> Recent Orders</h2>
      <ul class="activity-list">
        <?php if (count($recentOrders) > 0): ?>
          <?php foreach ($recentOrders as $order): ?>
            <li class="activity-item">
              <div class="activity-icon">
                <i class="fas fa-shopping-cart"></i>
              </div>
              <div class="activity-content">
                <div class="activity-title">
                  Order #<?= $order['id'] ?> - <?= htmlspecialchars($order['user_name'] ?? 'Guest') ?>
                </div>
                <div class="activity-time">
                  <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?> • 
                  Ksh <?= number_format($order['total_amount'], 2) ?>
                </div>
              </div>
              <span class="status-badge status-<?= $order['payment_status'] ?>">
                <?= ucfirst($order['payment_status']) ?>
              </span>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="activity-item">
            <div class="activity-content">
              <div class="activity-title">No recent orders</div>
            </div>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <script>
    function toggleSidebar() {
      const sidebar = document.querySelector('.sidebar');
      const mainContent = document.querySelector('.main-content');
      
      sidebar.classList.toggle('active');
      mainContent.classList.toggle('sidebar-active');
    }

    // Revenue Chart
    const revenueChart = new Chart(
      document.getElementById('revenueChart'),
      {
        type: 'bar',
        data: {
          labels: <?= json_encode($chartLabels) ?>,
          datasets: [{
            label: 'Revenue (Ksh)',
            data: <?= json_encode($chartData) ?>,
            backgroundColor: 'rgba(231, 76, 60, 0.2)',
            borderColor: 'rgba(231, 76, 60, 1)',
            borderWidth: 2,
            borderRadius: 6,
            hoverBackgroundColor: 'rgba(231, 76, 60, 0.4)'
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(0, 0, 0, 0.05)'
              },
              ticks: {
                callback: function(value) {
                  return 'Ksh ' + value.toLocaleString();
                }
              }
            },
            x: {
              grid: {
                display: false
              }
            }
          }
        }
      }
    );

    // Add animation to numbers
    document.addEventListener('DOMContentLoaded', function() {
      const counters = document.querySelectorAll('.card p');
      counters.forEach(counter => {
        const target = parseInt(counter.textContent.replace(/,/g, ''));
        if (!isNaN(target)) {
          animateCounter(counter, target);
        }
      });
    });

    function animateCounter(element, target) {
      let current = 0;
      const duration = 2000;
      const increment = target / (duration / 16);
      
      const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
          element.textContent = target.toLocaleString();
          clearInterval(timer);
        } else {
          element.textContent = Math.floor(current).toLocaleString();
        }
      }, 16);
    }
  </script>
</body>
</html>