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

// Fetch rewards data with comprehensive analysis
$rewardsData = [];
$stats = [];
$error = '';

try {
    // 1. Overall rewards statistics
    $statsQuery = "
        SELECT 
            COUNT(*) AS total_rewards,
            SUM(amount) AS total_amount,
            AVG(amount) AS avg_amount,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_rewards,
            COUNT(CASE WHEN status = 'paid' THEN 1 END) AS paid_rewards,
            COUNT(CASE WHEN status = 'manually_paid' THEN 1 END) AS manually_paid_rewards,
            SUM(CASE WHEN status IN ('paid', 'manually_paid') THEN amount ELSE 0 END) AS paid_amount,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) AS pending_amount
        FROM rewards
    ";
    $stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
    
    // 2. Rewards by type
    $typeQuery = "
        SELECT 
            reward_type,
            COUNT(*) AS count,
            SUM(amount) AS total_amount,
            AVG(amount) AS avg_amount,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
            COUNT(CASE WHEN status IN ('paid', 'manually_paid') THEN 1 END) AS paid_count
        FROM rewards
        GROUP BY reward_type
        ORDER BY total_amount DESC
    ";
    $rewardsData['by_type'] = $pdo->query($typeQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Rewards by status
    $statusQuery = "
        SELECT 
            status,
            COUNT(*) AS count,
            SUM(amount) AS total_amount,
            AVG(amount) AS avg_amount,
            MIN(created_at) AS oldest_reward,
            MAX(created_at) AS newest_reward
        FROM rewards
        GROUP BY status
        ORDER BY status
    ";
    $rewardsData['by_status'] = $pdo->query($statusQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Recent rewards
    $recentQuery = "
        SELECT 
            r.*,
            referrer.name AS referrer_name,
            referrer.email AS referrer_email,
            referred.name AS referred_name,
            referred.email AS referred_email,
            o.total_amount AS order_amount,
            o.payment_status AS order_status
        FROM rewards r
        LEFT JOIN users referrer ON r.referrer_id = referrer.id
        LEFT JOIN users referred ON r.referred_user_id = referred.id
        LEFT JOIN orders o ON r.order_id = o.id
        ORDER BY r.created_at DESC
        LIMIT 50
    ";
    $rewardsData['recent'] = $pdo->query($recentQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Top earners
    $topEarnersQuery = "
        SELECT 
            u.id,
            u.name,
            u.email,
            u.wallet_balance,
            COUNT(r.id) AS total_rewards,
            SUM(r.amount) AS total_earnings,
            COUNT(CASE WHEN r.status = 'pending' THEN 1 END) AS pending_rewards,
            COUNT(CASE WHEN r.status IN ('paid', 'manually_paid') THEN 1 END) AS paid_rewards
        FROM users u
        JOIN rewards r ON u.id = r.referrer_id
        GROUP BY u.id
        ORDER BY total_earnings DESC
        LIMIT 10
    ";
    $rewardsData['top_earners'] = $pdo->query($topEarnersQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // 6. Pending rewards that need attention (older than 3 days)
    $pendingQuery = "
        SELECT 
            r.*,
            referrer.name AS referrer_name,
            referrer.email AS referrer_email,
            referrer.phone AS referrer_phone,
            referred.name AS referred_name,
            o.total_amount AS order_amount,
            DATEDIFF(NOW(), r.created_at) AS days_pending
        FROM rewards r
        LEFT JOIN users referrer ON r.referrer_id = referrer.id
        LEFT JOIN users referred ON r.referred_user_id = referred.id
        LEFT JOIN orders o ON r.order_id = o.id
        WHERE r.status = 'pending'
        AND DATEDIFF(NOW(), r.created_at) >= 3
        ORDER BY r.created_at ASC
        LIMIT 20
    ";
    $rewardsData['needs_attention'] = $pdo->query($pendingQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // 7. Reward distribution by month
    $monthlyQuery = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS reward_count,
            SUM(amount) AS total_amount,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
            COUNT(CASE WHEN status IN ('paid', 'manually_paid') THEN 1 END) AS paid_count
        FROM rewards
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ";
    $rewardsData['monthly'] = $pdo->query($monthlyQuery)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log($error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Yusai Admin - Rewards Monitoring</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --border: #dee2e6;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fb;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1400px;
            margin: 150px auto 20px;
            padding: 15px;
            transition: margin-left 0.3s ease;
            margin-left:230px;
        }
        
        .sidebar-open .container {
            margin-left: 230px;
        }
        
       
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease;
        }
        
        .stat-card:active {
            transform: scale(0.98);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .stat-card-title {
            font-size: 0.9rem;
            color: var(--secondary);
            font-weight: 500;
        }
        
        .stat-card-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .icon-primary { background: rgba(67, 97, 238, 0.1); color: var(--primary); }
        .icon-success { background: rgba(40, 167, 69, 0.1); color: var(--success); }
        .icon-warning { background: rgba(255, 193, 7, 0.1); color: var(--warning); }
        .icon-danger { background: rgba(220, 53, 69, 0.1); color: var(--danger); }
        
        .stat-card-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card-description {
            font-size: 0.8rem;
            color: var(--secondary);
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            background: white;
            border-radius: 10px;
            padding: 8px;
            box-shadow: var(--card-shadow);
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
        }
        
        .tab {
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .tab.active {
            background: var(--primary);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: var(--card-shadow);
        }
        
        .chart-card-header {
            margin-bottom: 10px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        
        /* Tables */
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: var(--card-shadow);
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .table-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .table-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            white-space: nowrap;
        }
        
        tbody tr:hover {
            background-color: #f8f9ff;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success { background: rgba(40, 167, 69, 0.1); color: var(--success); }
        .badge-warning { background: rgba(255, 193, 7, 0.1); color: var(--warning); }
        .badge-danger { background: rgba(220, 53, 69, 0.1); color: var(--danger); }
        
        /* Status colors */
        .status-pending { color: var(--warning); font-weight: 600; }
        .status-paid { color: var(--success); font-weight: 600; }
        .status-manually_paid { color: var(--info); font-weight: 600; }
        
        /* Alert for attention needed */
        .attention-alert {
            background: #fff3cd;
            border-left: 4px solid var(--warning);
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1000;
            background: var(--primary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
             /* Responsive Design */
        @media (max-width: 999px) {
            .container {
                margin-top: 100px;
                margin-left:230px;
            }
        }
        
        
        /* Responsive Design */
        @media (min-width: 992px) {
            .charts-section {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 991px) {
            .container {
                margin-top: 100px;
                
            }
            
            .sidebar-open .container {
                margin-left: 0;
            }
            
            .mobile-menu-toggle {
                display: flex;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
                margin-left:0;
            }
            
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card-value {
                font-size: 1.3rem;
            }
            
            .tabs {
                padding: 5px;
            }
            
            .tab {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            
            .chart-container {
                height: 200px;
            }
            
            /* Stack table cells for mobile */
            .responsive-table {
                width: 100%;
            }
            
            .responsive-table thead {
                display: none;
            }
            
            .responsive-table tr {
                display: block;
                margin-bottom: 15px;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                background: white;
            }
            
            .responsive-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 15px;
                border-bottom: 1px solid #eee;
                text-align: right;
            }
            
            .responsive-table td:before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--dark);
                margin-right: 15px;
                flex: 1;
                text-align: left;
            }
            
            .responsive-table td:last-child {
                border-bottom: none;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                margin: 100px auto 15px;
                padding: 8px;
            }
            
          
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-card-value {
                font-size: 1.2rem;
            }
            
            .stat-card-description {
                font-size: 0.75rem;
            }
            
            .chart-container {
                height: 180px;
            }
            
            .attention-alert {
                font-size: 0.8rem;
                padding: 10px;
            }
            
            .tab {
                font-size: 0.75rem;
                padding: 5px 8px;
            }
            
            .table-card {
                padding: 10px;
            }
        }
        
        /* Print styles */
        @media print {
          
            
            .container {
                margin: 0;
                padding: 0;
                width: 100%;
            }
            
            .tab-content {
                display: block !important;
            }
            
            .stat-card, .chart-card, .table-card {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
  
    <div class="container">
       
        
        <?php if ($error): ?>
            <div class="attention-alert" style="border-color: var(--danger);">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($rewardsData['needs_attention'])): ?>
            <div class="attention-alert">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Attention needed:</strong> You have <?= count($rewardsData['needs_attention']) ?> rewards pending for more than 3 days that need processing.
            </div>
        <?php endif; ?>
        
        <!-- Stats Overview -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-title">Total Rewards</div>
                    <div class="stat-card-icon icon-primary">
                        <i class="fas fa-gift"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['total_rewards'] ?? 0) ?></div>
                <div class="stat-card-description">All reward transactions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-title">Total Reward Value</div>
                    <div class="stat-card-icon icon-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <div class="stat-card-value">KSh <?= number_format($stats['total_amount'] ?? 0, 2) ?></div>
                <div class="stat-card-description">Total value of all rewards</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-title">Pending Rewards</div>
                    <div class="stat-card-icon icon-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format($stats['pending_rewards'] ?? 0) ?></div>
                <div class="stat-card-description">KSh <?= number_format($stats['pending_amount'] ?? 0, 2) ?> value</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-title">Paid Rewards</div>
                    <div class="stat-card-icon icon-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?= number_format(($stats['paid_rewards'] ?? 0) + ($stats['manually_paid_rewards'] ?? 0)) ?></div>
                <div class="stat-card-description">KSh <?= number_format($stats['paid_amount'] ?? 0, 2) ?> value</div>
            </div>
        </div>
        
        <!-- Tabs Navigation -->
        <div class="tabs">
            <div class="tab active" data-tab="overview">Overview</div>
            <div class="tab" data-tab="recent">Recent Activity</div>
            <div class="tab" data-tab="earners">Top Earners</div>
            <div class="tab" data-tab="pending">Pending Rewards</div>
        </div>
        
        <!-- Charts Section -->
        <div class="tab-content active" id="overview">
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-card-header">Rewards by Type</div>
                    <div class="chart-container">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-card-header">Monthly Rewards Trend</div>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Rewards by Status -->
            <div class="table-card">
                <div class="table-card-header">
                    <div class="table-card-title">Rewards by Status</div>
                </div>
                <div class="table-container">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Total Amount</th>
                                <th>Average Amount</th>
                                <th>Oldest Reward</th>
                                <th>Newest Reward</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rewardsData['by_status'])): ?>
                                <?php foreach ($rewardsData['by_status'] as $status): ?>
                                    <tr>
                                        <td data-label="Status"><span class="status-<?= $status['status'] ?>"><?= ucfirst(str_replace('_', ' ', $status['status'])) ?></span></td>
                                        <td data-label="Count"><?= number_format($status['count']) ?></td>
                                        <td data-label="Total Amount">KSh <?= number_format($status['total_amount'], 2) ?></td>
                                        <td data-label="Average Amount">KSh <?= number_format($status['avg_amount'], 2) ?></td>
                                        <td data-label="Oldest Reward"><?= $status['oldest_reward'] ? date('M d, Y', strtotime($status['oldest_reward'])) : 'N/A' ?></td>
                                        <td data-label="Newest Reward"><?= $status['newest_reward'] ? date('M d, Y', strtotime($status['newest_reward'])) : 'N/A' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;">
                                        No reward data available
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Rewards by Type -->
            <div class="table-card">
                <div class="table-card-header">
                    <div class="table-card-title">Rewards by Type</div>
                </div>
                <div class="table-container">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Reward Type</th>
                                <th>Total Count</th>
                                <th>Total Amount</th>
                                <th>Average Amount</th>
                                <th>Pending</th>
                                <th>Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rewardsData['by_type'])): ?>
                                <?php foreach ($rewardsData['by_type'] as $type): ?>
                                    <tr>
                                        <td data-label="Reward Type"><?= ucfirst(str_replace('_', ' ', $type['reward_type'])) ?></td>
                                        <td data-label="Total Count"><?= number_format($type['count']) ?></td>
                                        <td data-label="Total Amount">KSh <?= number_format($type['total_amount'], 2) ?></td>
                                        <td data-label="Average Amount">KSh <?= number_format($type['avg_amount'], 2) ?></td>
                                        <td data-label="Pending"><?= number_format($type['pending_count']) ?></td>
                                        <td data-label="Paid"><?= number_format($type['paid_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;">
                                        No reward type data available
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity Tab -->
        <div class="tab-content" id="recent">
            <div class="table-card">
                <div class="table-card-header">
                    <div class="table-card-title">Recent Reward Activity</div>
                </div>
                <div class="table-container">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Referrer</th>
                                <th>Referred User</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rewardsData['recent'])): ?>
                                <?php foreach ($rewardsData['recent'] as $reward): ?>
                                    <tr>
                                        <td data-label="Date"><?= date('M d, H:i', strtotime($reward['created_at'])) ?></td>
                                        <td data-label="Referrer">
                                            <?= $reward['referrer_name'] ? htmlspecialchars($reward['referrer_name']) : 'N/A' ?>
                                            <?php if ($reward['referrer_email']): ?>
                                                <br><small><?= htmlspecialchars($reward['referrer_email']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Referred User">
                                            <?= $reward['referred_name'] ? htmlspecialchars($reward['referred_name']) : 'N/A' ?>
                                            <?php if ($reward['referred_email']): ?>
                                                <br><small><?= htmlspecialchars($reward['referred_email']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Type"><?= ucfirst(str_replace('_', ' ', $reward['reward_type'])) ?></td>
                                        <td data-label="Amount">KSh <?= number_format($reward['amount'], 2) ?></td>
                                        <td data-label="Status"><span class="status-<?= $reward['status'] ?>"><?= ucfirst($reward['status']) ?></span></td>
                                        <td data-label="Order">
                                            <?php if ($reward['order_id']): ?>
                                                Order #<?= $reward['order_id'] ?>
                                                <?php if ($reward['order_status']): ?>
                                                    <br><span class="badge badge-<?= $reward['order_status'] === 'paid' ? 'success' : 'warning' ?>">
                                                        <?= ucfirst($reward['order_status']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px;">
                                        No recent reward activity
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Top Earners Tab -->
        <div class="tab-content" id="earners">
            <div class="table-card">
                <div class="table-card-header">
                    <div class="table-card-title">Top Reward Earners</div>
                </div>
                <div class="table-container">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Total Rewards</th>
                                <th>Total Earnings</th>
                                <th>Pending</th>
                                <th>Paid</th>
                                <th>Wallet Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rewardsData['top_earners'])): ?>
                                <?php foreach ($rewardsData['top_earners'] as $earner): ?>
                                    <tr>
                                        <td data-label="User">
                                            <div><?= htmlspecialchars($earner['name']) ?></div>
                                            <small><?= htmlspecialchars($earner['email']) ?></small>
                                        </td>
                                        <td data-label="Total Rewards"><?= number_format($earner['total_rewards']) ?></td>
                                        <td data-label="Total Earnings"><strong>KSh <?= number_format($earner['total_earnings'], 2) ?></strong></td>
                                        <td data-label="Pending"><?= number_format($earner['pending_rewards']) ?></td>
                                        <td data-label="Paid"><?= number_format($earner['paid_rewards']) ?></td>
                                        <td data-label="Wallet Balance">KSh <?= number_format($earner['wallet_balance'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;">
                                        No top earners data available
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Pending Rewards Tab -->
        <div class="tab-content" id="pending">
            <?php if (!empty($rewardsData['needs_attention'])): ?>
                <div class="table-card">
                    <div class="table-card-header">
                        <div class="table-card-title">Rewards Needing Attention (Pending > 3 Days)</div>
                    </div>
                    <div class="table-container">
                        <table class="responsive-table">
                            <thead>
                                <tr>
                                    <th>Days Pending</th>
                                    <th>Referrer</th>
                                    <th>Referred User</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Order Amount</th>
                                    <th>Date Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rewardsData['needs_attention'] as $reward): ?>
                                    <tr>
                                        <td data-label="Days Pending"><span class="badge badge-danger"><?= $reward['days_pending'] ?> days</span></td>
                                        <td data-label="Referrer">
                                            <?= htmlspecialchars($reward['referrer_name']) ?>
                                            <?php if ($reward['referrer_email']): ?>
                                                <br><small><?= htmlspecialchars($reward['referrer_email']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($reward['referrer_phone']): ?>
                                                <br><small><?= htmlspecialchars($reward['referrer_phone']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Referred User"><?= htmlspecialchars($reward['referred_name']) ?></td>
                                        <td data-label="Type"><?= ucfirst(str_replace('_', ' ', $reward['reward_type'])) ?></td>
                                        <td data-label="Amount"><strong>KSh <?= number_format($reward['amount'], 2) ?></strong></td>
                                        <td data-label="Order Amount">KSh <?= number_format($reward['order_amount'], 2) ?></td>
                                        <td data-label="Date Created"><?= date('M d, Y', strtotime($reward['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-card">
                    <div class="table-card-header">
                        <div class="table-card-title">Pending Rewards</div>
                    </div>
                    <div class="table-container">
                        <div style="text-align: center; padding: 40px;">
                            <i class="fas fa-check-circle" style="font-size: 2.5rem; color: var(--success); margin-bottom: 12px;"></i>
                            <h3>No Rewards Need Attention</h3>
                            <p>All pending rewards are within acceptable time limits.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.body.classList.toggle('sidebar-open');
        });
        
        // Tab functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
        
        // Function to export data (placeholder)
        function exportData() {
            alert('Export functionality would be implemented here. This could export reward data as CSV or PDF.');
        }
        
        // Prepare data for charts
        const typeData = {
            labels: [<?php 
                if (!empty($rewardsData['by_type'])) {
                    $labels = [];
                    foreach ($rewardsData['by_type'] as $type) {
                        $labels[] = "'" . ucfirst(str_replace('_', ' ', $type['reward_type'])) . "'";
                    }
                    echo implode(', ', $labels);
                }
            ?>],
            datasets: [{
                data: [<?php 
                    if (!empty($rewardsData['by_type'])) {
                        $amounts = [];
                        foreach ($rewardsData['by_type'] as $type) {
                            $amounts[] = $type['total_amount'];
                        }
                        echo implode(', ', $amounts);
                    }
                ?>],
                backgroundColor: ['#4361ee', '#28a745', '#ffc107', '#dc3545', '#6c757d', '#17a2b8'],
                borderWidth: 0
            }]
        };
        
        // Monthly trends data
        const monthlyLabels = [<?php 
            if (!empty($rewardsData['monthly'])) {
                $labels = [];
                foreach ($rewardsData['monthly'] as $month) {
                    $labels[] = "'" . $month['month'] . "'";
                }
                echo implode(', ', array_reverse($labels));
            }
        ?>];
        
        const monthlyAmounts = [<?php 
            if (!empty($rewardsData['monthly'])) {
                $amounts = [];
                foreach ($rewardsData['monthly'] as $month) {
                    $amounts[] = $month['total_amount'];
                }
                echo implode(', ', array_reverse($amounts));
            }
        ?>];
        
        const monthlyCounts = [<?php 
            if (!empty($rewardsData['monthly'])) {
                $counts = [];
                foreach ($rewardsData['monthly'] as $month) {
                    $counts[] = $month['reward_count'];
                }
                echo implode(', ', array_reverse($counts));
            }
        ?>];
        
        // Initialize charts when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Type distribution chart
            const typeCtx = document.getElementById('typeChart').getContext('2d');
            if (typeCtx) {
                new Chart(typeCtx, {
                    type: 'doughnut',
                    data: typeData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.label + ': KSh ' + context.raw.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Monthly trends chart
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            if (monthlyCtx) {
                new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyLabels,
                        datasets: [
                            {
                                label: 'Reward Amount',
                                data: monthlyAmounts,
                                backgroundColor: 'rgba(67, 97, 238, 0.7)',
                                borderColor: '#4361ee',
                                borderWidth: 1
                            },
                            {
                                label: 'Number of Rewards',
                                data: monthlyCounts,
                                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                                borderColor: '#28a745',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        if (context.dataset.label === 'Reward Amount') {
                                            return 'Amount: KSh ' + context.raw.toLocaleString();
                                        } else {
                                            return 'Count: ' + context.raw;
                                        }
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>