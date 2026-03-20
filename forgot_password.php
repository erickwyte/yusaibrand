<?php
session_start();
require_once 'db.php'; // Database connection ($pdo)
require_once 'vendor/autoload.php'; // PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$successMessage = '';
$errorMessage = '';

// Check for redirect URL in query parameter and store in session
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $redirect = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
    if (strpos($redirect, 'http') === false && !empty($redirect)) {
        $_SESSION['redirect_after_login'] = $redirect;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email exists in users table
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                
                // Store token in password_reset_tokens table
                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token) VALUES (?, ?)");
                $stmt->execute([$user['id'], $token]);

                // Send reset email
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'mail.yusaibrand.co.ke';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'info@yusaibrand.co.ke';
                    $mail->Password = 'XQM~FF!0,Zs!fK4-';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;

                    $mail->setFrom('info@yusaibrand.co.ke', 'YUSAI BRAND');
                    $mail->addAddress($email, $user['name']);
                    $mail->addReplyTo('info@yusaibrand.co.ke', 'Support Team');
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request';

                    $resetLink = "https://yusaibrand.co.ke/reset_password.php?token=$token";
                    $mail->Body = "
                        <h2>Password Reset Request</h2>
                        <p>Dear {$user['name']},</p>
                        <p>We received a request to reset your password. Click the link below to reset it:</p>
                        <p><a href='$resetLink' style='display: inline-block; padding: 10px 20px; background: #e74c3c; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                        <p>This link will expire in 30 minutes.</p>
                        <p>If you did not request this, please ignore this email or contact support@yusaibrand.co.ke.</p>
                        <p>Best regards,<br>YUSAI Brand Team</p>
                    ";
                    $mail->AltBody = "Dear {$user['name']},\n\nWe received a request to reset your password. Visit this link to reset it: $resetLink\n\nThis link will expire in 30 minutes.\n\nIf you did not request this, please ignore this email or contact support@yusaibrand.co.ke.\n\nBest regards,\nYUSAI Brand Team";

                    $mail->send();
                    $successMessage = 'Password reset email sent! Please check your inbox for instructions.';
                } catch (Exception $e) {
                    error_log("Failed to send password reset email: " . $mail->ErrorInfo);
                    $errorMessage = 'Failed to send reset email. Please try again later or contact support.';
                }
            } else {
                // Don't reveal if email exists for security
                $successMessage = 'Password reset email sent! Please check your inbox for instructions.';
            }
        } catch (PDOException $e) {
            error_log("Database error during password reset: " . $e->getMessage());
            $errorMessage = 'An error occurred. Please try again later.';
        }
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
        
        .security-tips {
            background: var(--primary-light);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid var(--primary);
        }
        
        .security-tips h3 {
            color: var(--secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .security-tips ul {
            padding-left: 20px;
            color: #555;
        }
        
        .security-tips li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .success-message {
            background: #e6ffed;
            border: 1px solid #b7eb8f;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            display: <?php echo $successMessage ? 'block' : 'none'; ?>;
        }
        
        .error-message {
            background: #ffe6e6;
            border: 1px solid #ff9999;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            display: <?php echo $errorMessage ? 'block' : 'none'; ?>;
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
        
        /* Responsive Design */
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
            <p>Enter your email address and we'll send you instructions to reset your password.</p>
        </div>
        
        <div class="form-side">
            <div class="form-container">
                <div class="form-header">
                    <div class="logo">
                        <i class="fas fa-fire"></i>
                        <span>YUSAI</span>
                    </div>
                    <h1>Forgot Password?</h1>
                    <p>Enter your email to reset your password</p>
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
                
                <form id="forgotPasswordForm" action="forgot_password.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email address" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i> Send Reset Instructions
                    </button>
                </form>
                
                <div class="back-to-login">
                    Remembered your password? <a href="log_in.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>">Sign in</a>
                </div>
                
                <div class="security-tips">
                    <h3><i class="fas fa-shield-alt"></i> Security Tips</h3>
                    <ul>
                        <li>We'll never ask for your password outside the login page</li>
                        <li>Password reset links expire after 30 minutes</li>
                        <li>Check for our official domain in the reset email</li>
                        <li>Contact support if you didn't request this reset</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>