<?php
session_start();
require_once 'db.php'; // Database connection ($pdo)

$successMessage = '';
$errorMessage = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    $errorMessage = 'Invalid or missing reset token.';
} else {
    try {
        // Check if token is valid and not expired (30 minutes)
        $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE token = ? AND used = 0 AND created_at >= NOW() - INTERVAL 30 MINUTE");
        $stmt->execute([$token]);
        $resetToken = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetToken) {
            $errorMessage = 'Invalid or expired reset token. Please request a new one.';
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'];
                $confirmPassword = $_POST['confirmPassword'];

                // Validate passwords
                if (empty($password) || empty($confirmPassword)) {
                    $errorMessage = 'Please enter and confirm your new password.';
                } elseif ($password !== $confirmPassword) {
                    $errorMessage = 'Passwords do not match.';
                } elseif (strlen($password) < 8) {
                    $errorMessage = 'Password must be at least 8 characters long.';
                } else {
                    // Update user's password
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $resetToken['user_id']]);

                    // Mark token as used
                    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
                    $stmt->execute([$token]);

                    $successMessage = 'Your password has been reset successfully! You can now log in with your new password.';
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database error during password reset: " . $e->getMessage());
        $errorMessage = 'An error occurred. Please try again later.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password | Yusai</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #e74c3c;
            --primary-light: rgba(231, 76, 60, 0.1);
            --secondary: #2c3e50;
            --light: #f9f9f9;
            --success: #27ae60;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e7ec 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .graphic-side {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, #c0392b 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            color: white;
            display: none;
        }
        
        .graphic-side .logo {
            font-size: 3rem;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .graphic-side h2 {
            font-size: 2.2rem;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .graphic-side p {
            font-size: 1.1rem;
            text-align: center;
            max-width: 400px;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .form-side {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .form-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .form-header .logo {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        
        .form-header h1 {
            font-size: 2rem;
            color: var(--secondary);
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: #777;
            font-size: 1.05rem;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary);
            font-size: 1rem;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
        }
        
        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e1e5eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 25px;
            color: #777;
        }
        
        .back-to-login a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .back-to-login a:hover {
            text-decoration: underline;
        }
        
        .success-message, .error-message {
            background: #e6ffed;
            border: 1px solid #b7eb8f;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            display: <?php echo ($successMessage || $errorMessage) ? 'block' : 'none'; ?>;
        }
        
        .error-message {
            background: #ffe6e6;
            border: 1px solid #ff9999;
        }
        
        .success-message i, .error-message i {
            color: <?php echo $successMessage ? 'var(--success)' : '#e74c3c'; ?>;
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @media (min-width: 768px) {
            .graphic-side {
                display: flex;
            }
        }
        
        @media (max-width: 576px) {
            .form-side {
                padding: 40px 25px;
            }
            
            .form-header h1 {
                font-size: 1.8rem;
            }
            
            .form-header p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="graphic-side">
            <div class="logo">
                <i class="fas fa-fire"></i>
                <span>YUSAI</span>
            </div>
            <h2>Reset Your Password</h2>
            <p>Create a new password to securely access your YUSAI account.</p>
        </div>
        
        <div class="form-side">
            <div class="form-container">
                <div class="form-header">
                    <div class="logo">
                        <i class="fas fa-fire"></i>
                        <span>YUSAI</span>
                    </div>
                    <h1>Reset Password</h1>
                    <p>Enter a new password for your account</p>
                </div>
                
                <?php if ($successMessage): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <strong><?php echo htmlspecialchars($successMessage); ?></strong>
                    </div>
                <?php endif; ?>
                
                <?php if ($errorMessage): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong><?php echo htmlspecialchars($errorMessage); ?></strong>
                    </div>
                <?php endif; ?>
                
                <?php if (!$successMessage && !$errorMessage || $errorMessage): ?>
                    <form id="resetPasswordForm" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" class="form-control" placeholder="Enter new password" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <div class="input-with-icon">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" placeholder="Confirm new password" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">
                            <i class="fas fa-check"></i> Reset Password
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="back-to-login">
                    <a href="log_in.php<?php echo isset($_SESSION['redirect_after_login']) ? '?redirect=' . urlencode($_SESSION['redirect_after_login']) : ''; ?>">Back to Sign In</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>