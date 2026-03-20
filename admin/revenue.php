<?php
// Prevent output before headers
ob_start();

session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

try {
    // Include database connection
    $db_path = realpath(__DIR__ . '/../db.php');
    if (!$db_path || !file_exists($db_path)) {
        throw new Exception('Database configuration file not found');
    }
    require_once $db_path;

    // Check if PDO is properly initialized
    if (!isset($pdo)) {
        throw new Exception('Database connection not established');
    }

    // Set default time range (last 30 days)
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

    // Validate dates
    if (!strtotime($start_date) || !strtotime($end_date)) {
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
    }

    // Ensure end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        $temp = $start_date;
        $start_date = $end_date;
        $end_date = $temp;
    }

    // Get total revenue
    $revenue_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_revenue 
        FROM orders 
        WHERE payment_status = 'paid' 
        AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $revenue_stmt->execute([$start_date, $end_date]);
    $total_revenue = $revenue_stmt->fetchColumn();

    // Get revenue by date for chart
    $revenue_data_stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date, 
            COALESCE(SUM(total_amount), 0) as daily_revenue,
            COUNT(*) as order_count
        FROM orders 
        WHERE payment_status = 'paid' 
        AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $revenue_data_stmt->execute([$start_date, $end_date]);
    $revenue_data = $revenue_data_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for chart
    $chart_labels = [];
    $chart_revenue = [];
    $chart_orders = [];
    foreach ($revenue_data as $row) {
        $chart_labels[] = date('M j', strtotime($row['date']));
        $chart_revenue[] = (float)$row['daily_revenue'];
        $chart_orders[] = (int)$row['order_count'];
    }

    // Get revenue by product category
    $category_revenue_stmt = $pdo->prepare("
        SELECT 
            COALESCE(p.type, 'Uncategorized') as category,
            COALESCE(SUM(oi.total_price), 0) as revenue,
            COUNT(oi.id) as items_sold
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.payment_status = 'paid'
        AND o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY p.type
        ORDER BY revenue DESC
    ");
    $category_revenue_stmt->execute([$start_date, $end_date]);
    $category_revenue = $category_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get top products by revenue
    $top_products_stmt = $pdo->prepare("
        SELECT 
            p.name as product_name,
            COALESCE(p.type, 'Uncategorized') as category,
            COALESCE(SUM(oi.total_price), 0) as revenue,
            COUNT(oi.id) as quantity_sold
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.payment_status = 'paid'
        AND o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY oi.product_id
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $top_products_stmt->execute([$start_date, $end_date]);
    $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get referral earnings
    $referral_earnings_stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total_earnings,
            COUNT(*) as referral_count
        FROM referral_earnings 
        WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    ");
    $referral_earnings_stmt->execute([$start_date, $end_date]);
    $referral_earnings = $referral_earnings_stmt->fetch(PDO::FETCH_ASSOC);

    // Get paid rewards
    $rewards_stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total_rewards,
            COUNT(*) as rewards_count
        FROM rewards 
        WHERE status IN ('paid', 'manually_paid')
        AND (paid_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) 
             OR created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    ");
    $rewards_stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    $rewards_data = $rewards_stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate net revenue
    $net_revenue = $total_revenue - ($referral_earnings['total_earnings'] ?? 0) - ($rewards_data['total_rewards'] ?? 0);

} catch (Exception $e) {
    error_log('Error in revenue.php: ' . $e->getMessage());
    ob_end_clean();
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'An error occurred. Please try again later.']);
    exit;
}

// Clear output buffer before rendering HTML
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Revenue Analytics | Yusai Admin</title>
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
      margin-top:80px;
    }

    .navbar {
      background: var(--card-bg);
      padding: 20px 30px;
      border-radius: var(--border-radius);
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      box-shadow: var(--navbar-shadow);
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

    .filters {
      background: var(--card-bg);
      padding: 20px;
      border-radius: var(--border-radius);
      margin-bottom: 25px;
      box-shadow: var(--card-shadow);
    }

    .filter-form {
      display: flex;
      gap: 15px;
      align-items: flex-end;
      flex-wrap: wrap;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .form-group label {
      font-weight: 600;
      font-size: 0.9em;
      color: var(--text-light);
    }

    .form-control {
      padding: 10px 15px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 1em;
      transition: var(--transition);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px var(--primary-light);
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-size: 1em;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-primary {
      background: var(--primary-color);
      color: white;
    }

    .btn-primary:hover {
      background: #c0392b;
    }

    .btn-outline {
      background: transparent;
      border: 1px solid var(--primary-color);
      color: var(--primary-color);
    }

    .btn-outline:hover {
      background: var(--primary-light);
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

    .card .description {
      font-size: 0.9em;
      margin-top: 10px;
      color: var(--text-light);
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

    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }

    .data-table th,
    .data-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #eee;
    }

    .data-table th {
      background-color: #f8f9fa;
      font-weight: 600;
      color: var(--text-color);
    }

    .data-table tr:hover {
      background-color: #f8f9fa;
    }

    .table-container {
      background: var(--card-bg);
      padding: 25px;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      margin-bottom: 30px;
      overflow-x: auto;
    }

    .table-container h2 {
      margin: 0 0 20px;
      font-size: 1.4em;
      color: var(--text-color);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .table-container h2 i {
      color: var(--primary-color);
    }

    .positive {
      color: var(--success-color);
    }

    .negative {
      color: #e74c3c;
    }

    .export-options {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-bottom: 20px;
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

      .filter-form {
        flex-direction: column;
        align-items: stretch;
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
      
      .export-options {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <?php 
    $sidebar_path = realpath(__DIR__ . '/sidebar.php');
    if ($sidebar_path && file_exists($sidebar_path)) {
        include $sidebar_path;
    } else {
        error_log('Sidebar file not found at: ' . __DIR__ . '/sidebar.php');
        echo '<aside class="sidebar">Sidebar not available</aside>';
    }
  ?>

  <!-- Main Content -->
  <div class="main-content">
   

    <!-- Filters -->
    <div class="filters">
      <form method="GET" class="filter-form">
        <div class="form-group">
          <label for="start_date">Start Date</label>
          <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="form-group">
          <label for="end_date">End Date</label>
          <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="form-group">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Apply Filters
          </button>
        </div>
        <div class="form-group">
          <a href="revenue.php" class="btn btn-outline">
            <i class="fas fa-sync"></i> Reset
          </a>
        </div>
      </form>
    </div>

    <!-- Revenue Overview -->
    <div class="dashboard-grid">
      <div class="card">
        <h3><i class="fas fa-money-bill-wave"></i> Total Revenue</h3>
        <p>Ksh <?= number_format($total_revenue, 2) ?></p>
        <div class="description">
          From <?= date('M j, Y', strtotime($start_date)) ?> to <?= date('M j, Y', strtotime($end_date)) ?>
        </div>
      </div>

      <div class="card">
        <h3><i class="fas fa-chart-line"></i> Net Revenue</h3>
        <p>Ksh <?= number_format($net_revenue, 2) ?></p>
        <div class="description">
          After referrals and rewards
        </div>
      </div>

      <div class="card">
        <h3><i class="fas fa-shopping-cart"></i> Total Orders</h3>
        <p><?= array_sum($chart_orders) ?></p>
        <div class="description">
          Paid orders only
        </div>
      </div>

      <div class="card">
        <h3><i class="fas fa-hand-holding-usd"></i> Referral Earnings</h3>
        <p>Ksh <?= number_format($referral_earnings['total_earnings'] ?? 0, 2) ?></p>
        <div class="description">
          <?= $referral_earnings['referral_count'] ?? 0 ?> referrals
        </div>
      </div>

      <div class="card">
        <h3><i class="fas fa-gift"></i> Rewards Paid</h3>
        <p>Ksh <?= number_format($rewards_data['total_rewards'] ?? 0, 2) ?></p>
        <div class="description">
          <?= $rewards_data['rewards_count'] ?? 0 ?> rewards
        </div>
      </div>

      <div class="card">
        <h3><i class="fas fa-calendar"></i> Date Range</h3>
        <p><?= round((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24)) ?> days</p>
        <div class="description">
          Analysis period
        </div>
      </div>
    </div>

    <!-- Revenue Chart -->
    <div class="chart-container">
      <h2><i class="fas fa-chart-bar"></i> Daily Revenue</h2>
      <canvas id="revenueChart" height="100"></canvas>
    </div>

    <!-- Category Revenue -->
    <div class="table-container">
      <h2><i class="fas fa-tags"></i> Revenue by Category</h2>
      <table class="data-table">
        <thead>
          <tr>
            <th>Category</th>
            <th>Revenue</th>
            <th>Items Sold</th>
            <th>Percentage</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($category_revenue) > 0): ?>
            <?php foreach ($category_revenue as $category): ?>
              <tr>
                <td><?= htmlspecialchars($category['category'] ?? 'Uncategorized') ?></td>
                <td>Ksh <?= number_format($category['revenue'], 2) ?></td>
                <td><?= $category['items_sold'] ?></td>
                <td><?= $total_revenue > 0 ? number_format(($category['revenue'] / $total_revenue) * 100, 2) : 0 ?>%</td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" style="text-align: center;">No revenue data available for this period</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Top Products -->
    <div class="table-container">
      <h2><i class="fas fa-star"></i> Top Products by Revenue</h2>
      <table class="data-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Revenue</th>
            <th>Quantity Sold</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($top_products) > 0): ?>
            <?php foreach ($top_products as $product): ?>
              <tr>
                <td><?= htmlspecialchars($product['product_name']) ?></td>
                <td><?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?></td>
                <td>Ksh <?= number_format($product['revenue'], 2) ?></td>
                <td><?= $product['quantity_sold'] ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" style="text-align: center;">No product data available for this period</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Export Options -->
    <div class="export-options">
      <a href="export_revenue.php?format=pdf&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn btn-primary">
        <i class="fas fa-file-pdf"></i> Export PDF
      </a>
      <a href="export_revenue.php?format=csv&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn btn-primary">
        <i class="fas fa-file-csv"></i> Export CSV
      </a>
    </div>
  </div>

  <script>
    function toggleSidebar() {
      const sidebar = document.querySelector('.sidebar');
      const mainContent = document.querySelector('.main-content');
      
      if (sidebar && mainContent) {
        sidebar.classList.toggle('active');
        mainContent.classList.toggle('sidebar-active');
      }
    }

    // Initialize the revenue chart
    document.addEventListener('DOMContentLoaded', function() {
      const ctx = document.getElementById('revenueChart').getContext('2d');
      const revenueChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: <?= json_encode($chart_labels) ?>,
          datasets: [{
            label: 'Daily Revenue (Ksh)',
            data: <?= json_encode($chart_revenue) ?>,
            backgroundColor: 'rgba(231, 76, 60, 0.2)',
            borderColor: 'rgba(231, 76, 60, 1)',
            borderWidth: 2,
            borderRadius: 6
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  return 'Ksh ' + context.raw.toLocaleString();
                }
              }
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
      });

      // Add animation to numbers
      const counters = document.querySelectorAll('.card p');
      counters.forEach(counter => {
        const text = counter.textContent;
        if (text.includes('Ksh')) {
          const amount = parseFloat(text.replace(/[^\d.]/g, ''));
          if (!isNaN(amount)) {
            animateCounter(counter, amount);
          }
        } else if (!isNaN(parseInt(text.replace(/,/g, '')))) {
          const value = parseInt(text.replace(/,/g, ''));
          animateCounter(counter, value);
        }
      });
    });

    function animateCounter(element, target) {
      const isCurrency = element.textContent.includes('Ksh');
      let current = 0;
      const duration = 2000;
      const increment = target / (duration / 16);
      
      const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
          element.textContent = isCurrency ? 'Ksh ' + target.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : target.toLocaleString();
          clearInterval(timer);
        } else {
          element.textContent = isCurrency ? 'Ksh ' + Math.floor(current).toLocaleString() : Math.floor(current).toLocaleString();
        }
      }, 16);
    }
  </script>
</body>
</html>