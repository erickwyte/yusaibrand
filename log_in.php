<?php
session_start();
require_once 'db.php'; // Database connection ($pdo)

$loginError = "";

// Define allowed redirect URLs for security
$allowedRedirects = [
    'index.php',
    'profile.php',
    'transactions/checkout.php',
    // Add other valid pages here
];

// Check for redirect URL in query parameter and store in session
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    // Sanitize the redirect URL
    $redirect = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
    
    // Normalize the path (remove leading/trailing slashes, ensure relative to root)
    $redirect = ltrim($redirect, '/');
    
    // Validate against allowed redirects
    if (in_array($redirect, $allowedRedirects)) {
        $_SESSION['redirect_after_login'] = $redirect;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrPhone = trim($_POST['emailOrPhone']);
    $password = $_POST['password'];

    // Validate input
    if (empty($emailOrPhone) || empty($password)) {
        $loginError = "Please enter both email/phone and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
            $stmt->execute([$emailOrPhone, $emailOrPhone]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Save session info
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Record successful login in history
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, login_status) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user['id'], $ipAddress, $userAgent, 'success']);
                } catch (PDOException $e) {
                    error_log("Failed to record login history: " . $e->getMessage());
                }

                // Determine redirect URL based on user role
                $redirectUrl = 'index.php'; // Default for regular users
                
                // Check if user is a regular admin
                if ($user['role'] === 'admin') {
                    $redirectUrl = 'admin/admin_dashboard.php';
                }
                
                // Check if user is a black market admin
                $stmt = $pdo->prepare("SELECT * FROM black_market_admins WHERE user_id = ? AND is_active = TRUE");
                $stmt->execute([$user['id']]);
                $blackMarketAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($blackMarketAdmin) {
                    $redirectUrl = 'admin/black-market-admin.php';
                }

                // Override with session redirect if set and valid
                if (isset($_SESSION['redirect_after_login']) && in_array($_SESSION['redirect_after_login'], $allowedRedirects)) {
                    $redirectUrl = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']); // Clear to prevent reuse
                }

                // Ensure redirect URL is relative to root
                header("Location: /$redirectUrl");
                exit;
            } else {
                $loginError = "Invalid login credentials.";
                
                // Record failed login attempt if user exists
                if ($user) {
                    $ipAddress = $_SERVER['REMOTE_ADDR'];
                    $userAgent = $_SERVER['HTTP_USER_AGENT'];
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO login_history (user_id, ip_address, user_agent, login_status) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$user['id'], $ipAddress, $userAgent, 'failed']);
                    } catch (PDOException $e) {
                        error_log("Failed to record failed login attempt: " . $e->getMessage());
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Database error during login: " . $e->getMessage());
            $loginError = "An error occurred. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - YUSAI Brand Referral</title>
  <meta name="description" content="Log in to your YUSAI account to manage referrals, profile, and earnings.">
  <link rel="stylesheet" href="css/log_in.css">
  
  <!-- Favicons -->
  <link rel="icon" type="image/png" href="my-favicon/favicon-96x96.png" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="my-favicon/favicon.svg" />
  <link rel="shortcut icon" href="my-favicon/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="my-favicon/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-title" content="yusaibrand" />
  <link rel="manifest" href="my-favicon/site.webmanifest" />
</head>
<body>
  <div class="container">
    <div class="logo">
      <!-- <img src="images/brand logo.jpg" alt="YUSAI Brand Logo"> -->
    </div>

    <h1>Login to Your Account</h1>
    <p class="intro-paragraph">Access your YUSAI dashboard to manage referrals, network, and track earnings.</p>

    <?php if (!empty($loginError)): ?>
      <p style="color: red; text-align: center; font-weight: bold;"><?= htmlspecialchars($loginError) ?></p>
    <?php endif; ?>

    <form id="loginForm" action="log_in.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST">
      <div class="form-group">
        <label for="emailOrPhone">Email or Phone Number</label>
        <input type="text" id="emailOrPhone" name="emailOrPhone" placeholder="Enter email or phone" value="<?php echo isset($emailOrPhone) ? htmlspecialchars($emailOrPhone) : ''; ?>" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required>
      </div>

      <div class="form-options">
        <div class="remember-me">
          <label><input type="checkbox" name="rememberMe"> Remember Me</label>
        </div>
        <div class="forgot-password">
          <a href="forgot_password.php">Forgot Password?</a>
        </div>
      </div>

      <button type="submit" class="form-submit-button">Log In</button>
    </form>

    <div class="register-link">
      Don't have an account? <a href="sign_up.php">Register Here</a>
    </div>

    <footer>
      &copy; <span id="current-year"></span> YUSAI Brand Company. All rights reserved.
    </footer>
  </div>

  <script>
    document.getElementById("current-year").textContent = new Date().getFullYear();
  </script>
</body>
</html>