<?php
require_once 'db.php';
session_start();

// Check if user is logged in

if (!isset($_SESSION['user_id'])) {
    $redirect = basename($_SERVER['PHP_SELF']); // Get current page name
    header("Location: log_in.php?redirect=" . urlencode($redirect));
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $pdo->prepare("SELECT u.*, r.name AS referrer_name FROM users u 
                       LEFT JOIN users r ON u.referrer_id = r.id 
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: error.php?code=user_not_found');
    exit;
}

// Generate referral code and link
$referralCode = 'YUSAI' . $user['id'];
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$referralLink = $baseUrl . '/sign_up.php?ref=' . urlencode($referralCode);

// Profile image handling
$profileImage = 'https://via.placeholder.com/100'; // Default
if (!empty($user['profile_image'])) {
    $imagePath = realpath($user['profile_image']);
    $allowedPath = realpath('uploads/');
    
    if ($imagePath && strpos($imagePath, $allowedPath) === 0 && file_exists($user['profile_image'])) {
        $profileImage = $user['profile_image'];
    }
}

// Fetch referral stats - UPDATED TO MATCH NEW DATABASE STRUCTURE
$referralStats = ['l1' => 0, 'l2' => 0, 'earnings' => 0.00];

try {
    // Count only Level 1 referrals who have made at least one gas refill
    $l1Stmt = $pdo->prepare("SELECT COUNT(DISTINCT u.id) 
                            FROM users u 
                            INNER JOIN orders o ON u.id = o.user_id 
                            INNER JOIN order_items oi ON o.id = oi.order_id 
                            INNER JOIN products p ON oi.product_id = p.id 
                            WHERE u.referrer_id = ? 
                            AND p.type = 'Gas Refill' 
                            AND o.payment_status = 'paid'");
    $l1Stmt->execute([$user_id]);
    $referralStats['l1'] = $l1Stmt->fetchColumn();

    // Count only Level 2 referrals who have made at least one gas refill
    $l2Stmt = $pdo->prepare("SELECT COUNT(DISTINCT u2.id) 
                            FROM users u1 
                            INNER JOIN users u2 ON u1.id = u2.referrer_id 
                            INNER JOIN orders o ON u2.id = o.user_id 
                            INNER JOIN order_items oi ON o.id = oi.order_id 
                            INNER JOIN products p ON oi.product_id = p.id 
                            WHERE u1.referrer_id = ? 
                            AND p.type = 'Gas Refill' 
                            AND o.payment_status = 'paid'");
    $l2Stmt->execute([$user_id]);
    $referralStats['l2'] = $l2Stmt->fetchColumn();

    // Calculate earnings from rewards table for gas refills - UPDATED
    $earningsStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) 
                                  FROM rewards 
                                  WHERE referrer_id = ? 
                                  AND status IN ('paid', 'manually_paid')
                                  AND reward_type IN ('gas_refill_first', 'gas_refill_chain')");
    $earningsStmt->execute([$user_id]);
    $referralStats['earnings'] = $earningsStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Rank calculation - NOW BASED ON QUALIFIED REFERRALS ONLY
$qualifiedReferrals = $referralStats['l1']; // Only count referrals with gas refills
$ranks = [
    'Beginner' => 0,
    'Strategist' => 5,
    'Leader' => 15,
    'Champion' => 30
];

$currentRank = 'Beginner';
$nextRank = 'Strategist';
$rankProgress = 0;

foreach ($ranks as $rank => $threshold) {
    if ($qualifiedReferrals >= $threshold) {
        $currentRank = $rank;
    } else {
        $nextRank = $rank;
        $prevThreshold = $ranks[$currentRank];
        $nextThreshold = $threshold;
        
        $range = $nextThreshold - $prevThreshold;
        $progress = $qualifiedReferrals - $prevThreshold;
        
        $rankProgress = $range > 0 ? min(100, ($progress / $range) * 100) : 100;
        break;
    }
}

$rankProgress = round($rankProgress, 1);

// Fetch pending referrals (those who haven't made gas refills yet)
$pendingReferrals = 0;
try {
    $pendingStmt = $pdo->prepare("SELECT COUNT(*) 
                                 FROM users 
                                 WHERE referrer_id = ? 
                                 AND id NOT IN (
                                     SELECT DISTINCT u.id 
                                     FROM users u 
                                     INNER JOIN orders o ON u.id = o.user_id 
                                     INNER JOIN order_items oi ON o.id = oi.order_id 
                                     INNER JOIN products p ON oi.product_id = p.id 
                                     WHERE p.type = 'Gas Refill' 
                                     AND o.payment_status = 'paid'
                                 )");
    $pendingStmt->execute([$user_id]);
    $pendingReferrals = $pendingStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Fetch recent rewards - UPDATED
$recentRewards = [];
try {
    $rewardsStmt = $pdo->prepare("SELECT r.*, u.name as referred_user_name 
                                 FROM rewards r 
                                 LEFT JOIN users u ON r.referred_user_id = u.id 
                                 WHERE r.referrer_id = ? 
                                 AND r.status IN ('paid', 'manually_paid')
                                 ORDER BY r.created_at DESC 
                                 LIMIT 5");
    $rewardsStmt->execute([$user_id]);
    $recentRewards = $rewardsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Fetch pending rewards (for admin view)
$pendingRewardsCount = 0;
try {
    $pendingRewardsStmt = $pdo->prepare("SELECT COUNT(*) 
                                        FROM rewards 
                                        WHERE referrer_id = ? 
                                        AND status = 'pending'");
    $pendingRewardsStmt->execute([$user_id]);
    $pendingRewardsCount = $pendingRewardsStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile - Yusai Brand Company</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/profile.css">
  
   <!-- Favicons -->
  <link rel="icon" type="image/png" href="my-favicon/favicon-96x96.png" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="my-favicon/favicon.svg" />
  <link rel="shortcut icon" href="my-favicon/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="my-favicon/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-title" content="Yusai Brand" />
  <link rel="manifest" href="my-favicon/site.webmanifest" />
<style>
 body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f5f5;
      margin: 0;
      padding: 0;
    }

   .profile-container {
      max-width: 1000px;
      margin: auto;
      padding: 20px;
    }
    .profile-header {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 30px;
    }

    .profile-header img {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: 50%;
      border: 3px solid #2c7be5;
    }


  
  
    /* Add these styles to your existing CSS */
    .rewards-badge {
        position: relative;
        display: inline-block;
    }
    
    .pending-rewards-count {
        position: absolute;
        top: -8px;
        right: -8px;
        background-color: #e74c3c;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
    }
    
    .wallet-balance {
        font-size: 1.2em;
        font-weight: bold;
        color: #27ae60;
    }
  

    .profile-header h2 {
      margin: 0;
      color: #333;
    }

    .edit-btn {
      display: inline-block;
      padding: 10px 20px;
      background-color: #12263f;
      color: white;
      border: none;
      border-radius: 6px;
      text-decoration: none;
      font-weight: bold;
      transition: background 0.3s ease;
    }

    .edit-btn:hover {
      background-color: #2c7be5;
    }

    .profile-details,
    .referral-stats,
    .rank-section {
      margin-bottom: 30px;
    }

    .profile-details h3,
    .referral-stats h3,
    .rank-section h3 {
      border-bottom: 2px solid #2c7be5;
      padding-bottom: 5px;
      margin-bottom: 15px;
      color: #333;
    }

   
    .details-grid, .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-top: 15px;
    }

  .detail-box, .stat-box {
      background: #fff;
      padding: 15px;
      border-radius: 6px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .detail-box strong {
      display: block;
      color:#2c7be5;
      margin-bottom: 4px;
    }

    


    .stat-box h4 {
      margin: 10px 0 5px;
      font-size: 1.1em;
      color:#2c7be5;
    }

    .stat-box p {
      font-size: 1.3em;
      font-weight: bold;
      color:#12263f;
    }

      .progress-bar-container {
      background: #ddd;
      height: 20px;
      border-radius: 10px;
      overflow: hidden;
    }

       .progress-bar {
      background: #12263f;
      color: white;
      height: 100%;
      line-height: 20px;
      padding-left: 10px;
    }

 
   
        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, #2c7be5 0%, #12263f 100%);
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            color: white;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(118, 75, 162, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: "";
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -80px;
            right: -80px;
        }
        
        .cta-section::after {
            content: "";
            position: absolute;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            bottom: -60px;
            left: -60px;
        }
        
        .cta-content {
            position: relative;
            z-index: 2;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .cta-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .cta-section p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            line-height: 1.6;
            opacity: 0.9;
        }
        
        .cta-button {
            display: inline-block;
            background: white;
            color: #12263f;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.2rem;
            padding: 16px 45px;
            border-radius: 50px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .cta-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
             color: #2c7be5;
        }
        
        .cta-button i {
            margin-left: 8px;
        }
        
         
    /* Enhanced Referral Section */
    .referral-container {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin: 8px 0;
    }

    .referral-code {
      font-weight: bold;
      color: #2c3e50;
      background: #f8f9fa;
      padding: 8px 15px;
      border-radius: 6px;
      border: 1px solid #ddd;
      flex-grow: 1;
      font-size: 1.1em;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: all 0.3s;
    }

    .referral-code:hover {
      background: #e9ecef;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .referral-code.copied::after {
      content: 'Copied!';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(46, 204, 113, 0.9);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      animation: fadeOut 1.5s forwards;
    }

    .copy-btn {
      background: #3498db;
      color: white;
      border: none;
      padding: 8px 15px;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
    }

    .copy-btn:hover {
      background: #2980b9;
      transform: translateY(-2px);
    }

    .share-btn {
      padding: 8px 15px;
      border-radius: 6px;
      background: #f1f2f6;
      border: 1px solid #ddd;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .share-btn:hover {
      background: #dfe4ea;
    }

    .whatsapp { color: #25D366; }
    .facebook { color: #1877F2; }
    .twitter { color: #1DA1F2; }
    .link { color: #3498db; }
    
    /* Animations */
    @keyframes fadeOut {
      0% { opacity: 1; }
      70% { opacity: 1; }
      100% { opacity: 0; }
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes scaleIn {
      from { transform: scale(0.9); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }

    /* Rank badges */
    .rank-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 0.9em;
      font-weight: bold;
      margin-left: 10px;
    }
    
    .beginner { background: #bdc3c7; color: #2c3e50; }
    .strategist { background: #3498db; color: white; }
    .leader { background: #9b59b6; color: white; }
    .champion { background: #e67e22; color: white; }
    
    /* Progress bar */
    .progress-bar-container {
      height: 28px;
      border-radius: 14px;
      overflow: hidden;
    }
    
    .progress-bar {
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 0.9em;
      transition: width 0.5s ease;
    }
    
    /* Share Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }
    
    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    
    .share-modal {
      background: white;
      border-radius: 15px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
      transform: translateY(30px);
      transition: all 0.4s ease;
      opacity: 0;
    }
    
    .modal-overlay.active .share-modal {
      transform: translateY(0);
      opacity: 1;
    }
    
    .modal-header {
      padding: 20px;
      border-bottom: 1px solid #eee;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .modal-header h3 {
      margin: 0;
      font-size: 1.5rem;
      color: #2c3e50;
    }
    
    .close-modal {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #7f8c8d;
      transition: color 0.3s;
    }
    
    .close-modal:hover {
      color: #e74c3c;
    }
    
    .modal-body {
      padding: 25px;
    }
    
    .referral-link-container {
      display: flex;
      margin-bottom: 25px;
    }
    
    .referral-link-input {
      flex: 1;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 8px 0 0 8px;
      font-size: 1rem;
    }
    
    .copy-link-btn {
      background: #3498db;
      color: white;
      border: none;
      padding: 0 20px;
      border-radius: 0 8px 8px 0;
      cursor: pointer;
      transition: background 0.3s;
    }
    
    .copy-link-btn:hover {
      background: #2980b9;
    }
    
    .share-options-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
    }
    
    .share-option {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px 15px;
      border-radius: 10px;
      background: #f9f9f9;
      cursor: pointer;
      transition: all 0.3s;
      border: 1px solid #eee;
    }
    
    .share-option:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      background: white;
      border-color: #3498db;
    }
    
    .share-icon {
      font-size: 2.5rem;
      margin-bottom: 10px;
    }
    
    .share-label {
      font-weight: 600;
      color: #2c3e50;
    }
    
    /* New style for pending referrals */
    .pending-referrals {
      background: #fff3cd;
      color: #856404;
      padding: 10px 15px;
      border-radius: 6px;
      margin-top: 10px;
      border: 1px solid #ffeaa7;
    }
    
    /* Reward history styles */
    .rewards-section {
      margin-top: 20px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    
    .rewards-list {
      margin-top: 10px;
    }
    
    .reward-item {
      padding: 10px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
    }
    
    .reward-item:last-child {
      border-bottom: none;
    }
    
    .no-rewards {
      padding: 10px;
      text-align: center;
      color: #6c757d;
    }
    
    
        @media (max-width: 600px) {
            .cta-section {
                padding: 35px 20px;
            }
            
            .cta-section h2 {
                font-size: 2rem;
            }
            
          
        }
 

      footer {
      text-align: center;
      margin-top: 30px;
      color: #000000;
      
    }
    

</style>
</head>
<body>

<?php include 'include/header.php'; ?>

<div class="profile-container">
  <!-- Header -->
  <div class="profile-header">
    <img src="<?= htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8') ?>" alt="Profile Photo">
    <div>
      <h2>
        <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
        
      </h2>
      <a href="edit-profile.php" class="edit-btn">Edit Profile</a>
    </div>
  </div>

  <!-- Account Info -->
  <div class="profile-details">
    <h3>Account Information</h3>
    <div class="details-grid">
      <div class="detail-box"><strong>Email</strong><br><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="detail-box"><strong>Phone</strong><br><?= !empty($user['phone']) ? htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8') : 'Not provided' ?></div>
      <div class="detail-box"><strong>Account Status</strong><br>Active</div>

      <!-- Enhanced Referral Section -->
      <div class="detail-box">
        <strong>Referral Code</strong><br>
        <div class="referral-container">
          <span id="referralCode" class="referral-code" title="Click to copy">
            <?= $referralCode ?>
          </span>
          <button id="openShareModal" class="share-btn">
            <i class="fas fa-share-alt"></i> Share
          </button>
        </div>
      </div>

      <div class="detail-box"><strong>Sponsor Name</strong><br><?= !empty($user['referrer_name']) ? htmlspecialchars($user['referrer_name'], ENT_QUOTES, 'UTF-8') : 'N/A' ?></div>
      <div class="detail-box"><strong>Sponsor ID</strong><br><?= $user['referrer_id'] ? 'YUSAI' . $user['referrer_id'] : 'N/A' ?></div>
    </div>
  </div>

  <!-- Bonus Stats -->
  <div class="referral-stats">
    <h3>Bonuses & Referral Stats</h3>
    <div class="stats-grid">
      <div class="stat-box">
        <h4>Qualified Referrals</h4>
        <p><?= $referralStats['l1'] ?></p>
        <?php if ($pendingReferrals > 0): ?>
        <div class="pending-referrals">
          <?= $pendingReferrals ?> pending activation
        </div>
        <?php endif; ?>
      </div>
      <div class="stat-box"><h4>Team Referrals</h4><p><?= $referralStats['l2'] ?></p></div>
      <div class="stat-box">
        <h4>Total Earnings</h4>
        <p>KSh <?= number_format($referralStats['earnings'], 2) ?></p>
        <?php if ($pendingRewardsCount > 0): ?>
        <div class="pending-referrals">
          <span class="rewards-badge">
            <?= $pendingRewardsCount ?> pending rewards
            <span class="pending-rewards-count"><?= $pendingRewardsCount ?></span>
          </span>
        </div>
        <?php endif; ?>
      </div>
   <!--<div class="stat-box">
        <h4>Available Balance</h4>
        <p class="wallet-balance">KSh <?= number_format($user['wallet_balance'] ?? 0, 2) ?></p>
      </div>-->
    </div>
  </div>

  <!-- Rank Progress -->
  <div class="rank-section">
    <h3>Rank Progress</h3>
    <div class="details-grid">
      <div class="detail-box"><strong>Current Rank</strong><br><?= $currentRank ?></div>
      <div class="detail-box"><strong>Next Rank</strong><br><?= $nextRank ?></div>
      <div class="detail-box"><strong>Qualified Referrals Needed</strong><br><?= max(0, ($ranks[$nextRank] ?? 0) - $qualifiedReferrals) ?></div>
      <div class="detail-box" style="grid-column: span 2;">
        <strong>Progress to Next Rank</strong>
        <div class="progress-bar-container">
          <div class="progress-bar" style="width: <?= $rankProgress ?>%; background: linear-gradient(90deg, #3498db, #2ecc71);">
            <?= $rankProgress ?>% Complete
          </div>
        </div>
        <p><small>Only referrals who have made their first gas refill count toward rank progression.</small></p>
      </div>
    </div>
  </div>

  <!-- Recent Rewards -->
  <?php if (!empty($recentRewards)): ?>
  <div class="rewards-section">
    <h3>Recent Rewards</h3>
    <div class="rewards-list">
      <?php foreach ($recentRewards as $reward): ?>
      <div class="reward-item">
        <div>
          <strong><?= htmlspecialchars($reward['referred_user_name'] ?? 'Unknown User', ENT_QUOTES, 'UTF-8') ?></strong>
          <div class="reward-type">
            <?php
            if ($reward['reward_type'] === 'gas_refill_first') {
                echo 'First Gas Refill';
            } elseif ($reward['reward_type'] === 'gas_refill_chain') {
                echo 'Referral Chain (Level ' . $reward['reward_level'] . ')';
            } else {
                echo ucfirst(str_replace('_', ' ', $reward['reward_type']));
            }
            ?>
          </div>
        </div>
        <div>KSh <?= number_format($reward['amount'], 2) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="rewards-section">
    <h3>Recent Rewards</h3>
    <div class="no-rewards">No rewards yet. Share your referral link to earn!</div>
  </div>
  <?php endif; ?>

  <!-- Sell Through Us Section -->
  <div class="cta-section">
    <div class="cta-content">
      <div class="cta-icon">
        <i class="fas fa-gift"></i>
      </div>
      <h2>Sell Your Products With Us</h2>
      <p>Join thousands of sellers on our platform and reach millions of potential customers. Submit your products today and start earning!</p>
      <a href="send_sell_request.php" class="cta-button">
        Submit Your Product <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </div>

  <hr/>
  <footer>
    &copy; <span id="year"></span> Yusai Brand Company. All rights reserved.
  </footer>
</div>

<!-- Share Modal -->
<div class="modal-overlay" id="shareModal">
  <div class="share-modal">
    <div class="modal-header">
      <h3>Share Referral Link</h3>
      <button class="close-modal">&times;</button>
    </div>
    <div class="modal-body">
      <div class="referral-link-container">
        <input type="text" id="referralLinkInput" class="referral-link-input" value="<?= $referralLink ?>" readonly>
        <button class="copy-link-btn" id="modalCopyBtn">
          <i class="fas fa-copy"></i> Copy
        </button>
      </div>
      
      <div class="share-options-grid">
        <div class="share-option" onclick="shareVia('whatsapp')">
          <div class="share-icon whatsapp">
            <i class="fab fa-whatsapp"></i>
          </div>
          <span class="share-label">WhatsApp</span>
        </div>
        
        <div class="share-option" onclick="shareVia('facebook')">
          <div class="share-icon facebook">
            <i class="fab fa-facebook"></i>
          </div>
          <span class="share-label">Facebook</span>
        </div>
        
        <div class="share-option" onclick="shareVia('twitter')">
          <div class="share-icon twitter">
            <i class="fab fa-twitter"></i>
          </div>
          <span class="share-label">Twitter</span>
        </div>
        
        <div class="share-option" onclick="shareVia('link')">
          <div class="share-icon link">
            <i class="fas fa-link"></i>
          </div>
          <span class="share-label">Copy Link</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Set current year
  document.getElementById('year').textContent = new Date().getFullYear();
  
  // Referral code copying functionality
  const referralCodeElement = document.getElementById('referralCode');
  const referralLink = "<?= $referralLink ?>";
  
  referralCodeElement.addEventListener('click', function() {
    navigator.clipboard.writeText(referralLink).then(() => {
      const originalText = this.textContent;
      
      // Add visual feedback
      this.classList.add('copied');
      
      setTimeout(() => {
        this.classList.remove('copied');
      }, 1500);
    }).catch(err => {
      console.error('Failed to copy: ', err);
      alert('Failed to copy link. Please copy manually:\n' + referralLink);
    });
  });
  
  // Modal functionality
  const modal = document.getElementById('shareModal');
  const openModalBtn = document.getElementById('openShareModal');
  const closeModalBtn = document.querySelector('.close-modal');
  const modalCopyBtn = document.getElementById('modalCopyBtn');
  
  function openModal() {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scrolling
  }
  
  function closeModal() {
    modal.classList.remove('active');
    document.body.style.overflow = ''; // Re-enable scrolling
  }
  
  openModalBtn.addEventListener('click', openModal);
  closeModalBtn.addEventListener('click', closeModal);
  
  // Close modal when clicking outside content
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeModal();
    }
  });
  
  // Modal copy button
  modalCopyBtn.addEventListener('click', () => {
    const input = document.getElementById('referralLinkInput');
    input.select();
    navigator.clipboard.writeText(input.value);
    
    // Visual feedback
    modalCopyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(() => {
      modalCopyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy';
    }, 2000);
  });
  
  // Social sharing function
  function shareVia(platform) {
    const shareText = encodeURIComponent("Join me on Yusai Brand Company and start earning! Use my referral code: <?= $referralCode ?>");
    
    let url = '';
    
    switch(platform) {
      case 'whatsapp':
        url = `https://wa.me/?text=${shareText}%0A${encodeURIComponent(referralLink)}`;
        break;
      case 'facebook':
        url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralLink)}&quote=${shareText}`;
        break;
      case 'twitter':
        url = `https://twitter.com/intent/tweet?text=${shareText}&url=${encodeURIComponent(referralLink)}`;
        break;
      case 'link':
        navigator.clipboard.writeText(referralLink);
        // Visual feedback
        const linkOption = document.querySelector('.share-option:nth-child(4)');
        linkOption.innerHTML = '<div class="share-icon link"><i class="fas fa-check"></i></div><span class="share-label">Copied!</span>';
        setTimeout(() => {
          linkOption.innerHTML = '<div class="share-icon link"><i class="fas fa-link"></i></div><span class="share-label">Copy Link</span>';
        }, 2000);
        return;
      default:
        return;
    }
    
    window.open(url, '_blank', 'width=600,height=400');
    closeModal();
  }
</script>
</body>
</html>