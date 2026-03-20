<?php
// pending_rewards.php
session_start();
require_once 'db.php';

// Check if user is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: log_in.php');
    exit;
}

// Process manual payout if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $rewardId = $_POST['reward_id'];
    $userId = $_POST['user_id'];
    $amount = $_POST['amount'];
    
    try {
        $pdo->beginTransaction();
        
        // Update reward status to manually paid
        $stmt = $pdo->prepare("UPDATE rewards SET status = 'manually_paid', paid_at = NOW() WHERE id = ?");
        $stmt->execute([$rewardId]);
        
        // Update user's wallet_balance (not balance)
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);
        
        // Record transaction
        $stmt = $pdo->prepare("INSERT INTO manual_payouts (reward_id, user_id, amount, processed_by, processed_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$rewardId, $userId, $amount, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "Reward of KSh " . number_format($amount, 2) . " has been manually paid out.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error processing payout: " . $e->getMessage();
    }
    
    header("Location: pending_rewards.php");
    exit;
}

// Get pending rewards
$pendingRewards = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as referred_user_name, u.phone as referred_user_phone,
               ref.name as referrer_name, ref.phone as referrer_phone,
               o.id as order_id, o.total_amount as order_amount
        FROM rewards r
        LEFT JOIN users u ON r.referred_user_id = u.id
        LEFT JOIN users ref ON r.referrer_id = ref.id
        LEFT JOIN orders o ON r.order_id = o.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $pendingRewards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching pending rewards: " . $e->getMessage();
}

// Get manual payout history
$payoutHistory = [];
try {
    $stmt = $pdo->prepare("
        SELECT mp.*, u.name as user_name, u.phone as user_phone, r.reward_type, r.amount as reward_amount
        FROM manual_payouts mp
        LEFT JOIN rewards r ON mp.reward_id = r.id
        LEFT JOIN users u ON mp.user_id = u.id
        ORDER BY mp.processed_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $payoutHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Don't show error for history if it fails
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Rewards - Yusai Brand Company</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --success-color: #27ae60;
            --success-dark: #219653;
            --info-color: #17a2b8;
            --info-dark: #138496;
            --danger-color: #e74c3c;
            --dark-color: #2c3e50;
            --light-color: #f5f5f5;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-color);
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px;
        }
        
        .header {
            background-color: var(--dark-color);
            color: white;
            padding: 12px 15px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.4rem;
            margin-right: 15px;
            margin-bottom: 8px;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--primary-color);
            color: white;
            padding: 12px 15px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-body {
            padding: 15px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }
        
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .btn {
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: background-color 0.2s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-dark);
        }
        
        .btn-info {
            background: var(--info-color);
            color: white;
        }
        
        .btn-info:hover {
            background: var(--info-dark);
        }
        
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .phone-number {
            font-family: monospace;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            display: inline-block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .copy-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        
        .copy-btn:hover {
            background: #5a6268;
        }
        
        .section-title {
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            font-size: 1.3rem;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        /* Responsive improvements */
        .table-responsive {
            overflow-x: auto;
            width: 100%;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Mobile-first adjustments */
        @media (max-width: 768px) {
            .container {
                padding:10px 0 0 0;
            }
            
            .header {
                padding: 10px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header h1 {
                font-size: 1.2rem;
                margin-bottom: 10px;
                margin-left:10px;
            }
            .card{
                border-radius:0;
             }
            .section-title{
            padding-left:10px;
            }
            
            .card-header {
                padding: 10px 12px;
                font-size: 0.95rem;
            }
            
            .card-body {
                padding: 10px;
            }
            
            th, td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
            
            .btn {
                font-size: 0.8rem;
                padding: 5px 8px;
            }
            
            .section-title {
                font-size: 1.1rem;
            }
            
            /* Make action buttons stack on very small screens */
            @media (max-width: 480px) {
                .action-buttons {
                    flex-direction: column;
                }
                
                .btn {
                    width: 100%;
                    justify-content: center;
                }
            }
        }
        
        /* Print styles */
        @media print {
            .header, .btn, .copy-btn {
                display: none !important;
            }
            
            .container {
                width: 100%;
                padding: 0;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Pending Rewards </h1>
        <a href="admin_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success_message']; ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?= $_SESSION['error_message']; ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= $error; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-clock"></i> Pending Rewards (<?= count($pendingRewards) ?>)
            </div>
            <div class="card-body">
                <?php if (count($pendingRewards) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Referrer</th>
                                    <th>Referrer Phone</th>
                                    <th>Referred User</th>
                                    <th>Reward Type</th>
                                    <th>Amount</th>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRewards as $reward): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($reward['referrer_name']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="phone-number" id="phone-<?= $reward['id'] ?>">
                                                <?= htmlspecialchars($reward['referrer_phone']) ?>
                                            </span>
                                            <button class="copy-btn" onclick="copyToClipboard('phone-<?= $reward['id'] ?>')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($reward['referred_user_name']) ?>
                                        </td>
                                        <td>
                                            <?php
                                            $rewardType = $reward['reward_type'];
                                            if ($rewardType === 'gas_refill_first') {
                                                echo 'First Gas Refill';
                                            } elseif ($rewardType === 'gas_refill_chain') {
                                                echo 'Referral Chain';
                                            } else {
                                                echo ucfirst(str_replace('_', ' ', $rewardType));
                                            }
                                            ?>
                                        </td>
                                        <td><strong>KSh <?= number_format($reward['amount'], 2) ?></strong></td>
                                        <td>#<?= $reward['order_id'] ?></td>
                                        <td><?= date('M j, Y H:i', strtotime($reward['created_at'])) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="reward_id" value="<?= $reward['id'] ?>">
                                                    <input type="hidden" name="user_id" value="<?= $reward['referrer_id'] ?>">
                                                    <input type="hidden" name="amount" value="<?= $reward['amount'] ?>">
                                                    <button type="submit" name="mark_paid" class="btn btn-success" 
                                                            onclick="return confirm('Mark reward of KSh <?= number_format($reward['amount'], 2) ?> to <?= htmlspecialchars($reward['referrer_name']) ?> as paid?')">
                                                        <i class="fas fa-check"></i> Paid
                                                    </button>
                                                </form>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $reward['referrer_phone']) ?>?text=Hello%20<?= urlencode($reward['referrer_name']) ?>%2C%20your%20reward%20of%20KSh%20<?= $reward['amount'] ?>%20has%20been%20processed." 
                                                   target="_blank" class="btn btn-info">
                                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No pending rewards at this time.</p>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="section-title">Manual Payout History</h2>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i> Recent Manual Payouts
            </div>
            <div class="card-body">
                <?php if (count($payoutHistory) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Phone</th>
                                    <th>Reward Type</th>
                                    <th>Amount</th>
                                    <th>Processed By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payoutHistory as $payout): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($payout['user_name']) ?></td>
                                        <td>
                                            <span class="phone-number">
                                                <?= htmlspecialchars($payout['user_phone']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $rewardType = $payout['reward_type'];
                                            if ($rewardType === 'gas_refill_first') {
                                                echo 'First Gas Refill';
                                            } elseif ($rewardType === 'gas_refill_chain') {
                                                echo 'Referral Chain';
                                            } else {
                                                echo ucfirst(str_replace('_', ' ', $rewardType));
                                            }
                                            ?>
                                        </td>
                                        <td><strong>KSh <?= number_format($payout['amount'], 2) ?></strong></td>
                                        <td>Admin #<?= $payout['processed_by'] ?></td>
                                        <td><?= date('M j, Y H:i', strtotime($payout['processed_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No manual payout history yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent.trim();
            
            navigator.clipboard.writeText(text).then(() => {
                // Show temporary feedback
                const btn = element.nextElementSibling;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
                alert('Failed to copy phone number');
            });
        }
    </script>
</body>
</html>