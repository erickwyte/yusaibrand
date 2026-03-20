<?php
require_once 'db.php';
session_start();

$successMessage = '';
$errorMessage = '';

// Check if token is provided in the URL
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $errorMessage = "Invalid or missing verification token.";
} else {
    $token = $_GET['token'];

    try {
        // Fetch user with matching token and check if token is still valid (e.g., within 24 hours)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email_verification_token = ? AND token_created_at >= NOW() - INTERVAL 1 DAY");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $errorMessage = "Invalid or expired verification token. Please request a new verification email.";
        } elseif (empty($user['new_email'])) {
            $errorMessage = "No pending email change found.";
        } else {
            // Update the user's email and clear verification fields
            $stmt = $pdo->prepare("UPDATE users SET email = ?, new_email = NULL, email_verification_token = NULL, token_created_at = NULL WHERE id = ?");
            $stmt->execute([$user['new_email'], $user['id']]);

            $successMessage = "Your email address has been successfully verified! You can now log in with your new email.";
        }
    } catch (Exception $e) {
        error_log("Error verifying email: " . $e->getMessage());
        $errorMessage = "An error occurred while verifying your email. Please try again or contact support.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | Yusaibrand</title>
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
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --danger: #e63946;
            --success: #2a9d8f;
            --warning: #f4a261;
            --border: #dee2e6;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .header h1 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 1rem;
            gap: 8px;
            background: var(--primary);
            color: white;
            text-decoration: none;
        }
        
        .btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        footer {
            text-align: center;
            padding: 20px;
            color: var(--gray);
            font-size: 0.9rem;
            border-top: 1px solid var(--border);
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-envelope"></i> Email Verification</h1>
            <p>Verify your new email address for Yusaibrand</p>
        </div>
        
        <?php if ($successMessage): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($successMessage) ?>
            </div>
            <a href="edit-profile.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Profile</a>
        <?php elseif ($errorMessage): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($errorMessage) ?>
            </div>
            <a href="edit-profile.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Profile</a>
        <?php endif; ?>
        
        <footer>
            <p>© <?= date('Y') ?> Yusaibrand. All rights reserved. | Need help? Contact support@yusaibrand.co.ke</p>
        </footer>
    </div>
</body>
</html>