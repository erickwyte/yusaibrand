<?php
require_once 'db.php';
session_start();
$signupError = "";
$referralCodeFromLink = "";

// Check for referral code in URL
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $ref = trim($_GET['ref']);
    // Extract user ID from referral code (remove 'YUSAI' prefix)
    $userId = preg_replace('/^YUSAI/', '', $ref);
    if (is_numeric($userId)) {
        // Validate referral code exists in the database
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if ($stmt->rowCount()) {
            $referralCodeFromLink = $ref;
        } else {
            $signupError = "Invalid referral code.";
        }
    } else {
        $signupError = "Invalid referral code format.";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $referralCode = trim($_POST["referralCode"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirmPassword"];

    // 1. Check if passwords match
    if ($password !== $confirmPassword) {
        $signupError = "Passwords do not match.";
    } else {
        // 2. Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $signupError = "Email already registered.";
        } else {
            // 3. Determine role based on admin_emails table
            $role = 'user'; // Default
            $checkAdmin = $pdo->prepare("SELECT id FROM admin_emails WHERE email = ?");
            $checkAdmin->execute([$email]);
            if ($checkAdmin->rowCount() > 0) {
                $role = 'admin';
            }

            // 4. Hash the password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // 5. Check for valid referral code or use default YUSAI1
            $referrer_id = null;
            
            if (!empty($referralCode)) {
                // Remove 'YUSAI' prefix if present
                $referralId = preg_replace('/^YUSAI/', '', $referralCode);
                if (is_numeric($referralId)) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                    $stmt->execute([$referralId]);
                    if ($stmt->rowCount()) {
                        $referrer_id = $stmt->fetchColumn();
                    }
                }
            }
            
            // If no valid referral code provided, use default YUSAI1
            if (empty($referrer_id)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = 1");
                $stmt->execute();
                if ($stmt->rowCount()) {
                    $referrer_id = $stmt->fetchColumn();
                }
            }

            // 6. Insert new user with role
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, referrer_id, role) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $hashedPassword, $referrer_id, $role]);

            // 7. Redirect to login
            header("Location: log_in.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/sign_up.css">
  <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600&display=svg" rel="stylesheet">
  <title>Sign Up - YUSAI Brand</title>
  <style>
    .form-group input[type="checkbox"] {
      margin-right: 4px;
      transform: scale(1.2);
      cursor: pointer;
      width: 20px;
    }

    .form-group label {
      font-size: 14px;
      line-height: 1.5;
      align-items: center;
      gap: 2px;
      color: #333;
    }

    .form-group a {
      color: #007BFF; /* Or your brand color */
      text-decoration: none;
    }

    .form-group a:hover {
      text-decoration: underline;
    }

    /* Style for readonly referral code */
    .form-group input[readonly] {
      background-color: #f8f9fa;
      cursor: not-allowed;
    }
    
    .default-referral-note {
      font-size: 12px;
      color: #666;
      margin-top: 4px;
      font-style: italic;
    }
  </style>
</head>
<body>

<?php if ($signupError): ?>
  <div style="color: red; margin: 10px 0; padding: 10px; background: #ffe6e6; border-radius: 4px; text-align: center;">
    <?= htmlspecialchars($signupError) ?>
  </div>
<?php endif; ?>

<form id="signupForm" method="POST" action="sign_up.php">
  <div class="logo">
    <!-- <img src="images/default.jpg" alt="YUSAI Brand Logo"> -->
  </div>

  <h1>YUSAI BRAND</h1>
  <h1>Create Your Account</h1>
  <p class="intro-paragraph">Join YUSAI today to start referring, building your network, and tracking your earnings effortlessly.</p>

  <div class="form-group">
    <label for="name">Full Name</label>
    <input type="text" id="name" name="name" placeholder="e.g. Jane Doe" required />
  </div>

  <div class="form-group">
    <label for="email">Email Address</label>
    <input type="email" id="email" name="email" placeholder="e.g. jane@example.com" required />
  </div>

  <div class="form-group">
    <label for="phone">Phone Number</label>
    <input type="tel" id="phone" name="phone" placeholder="e.g. 0712 345 678" required />
  </div>

  <div class="form-group">
    <label for="referralCode">Referral ID (optional)</label>
    <input type="text" id="referralCode" name="referralCode" placeholder="User ID of who referred you"
           value="<?= htmlspecialchars($referralCodeFromLink) ?>" 
           <?= $referralCodeFromLink ? 'readonly' : '' ?> />
  
  </div>

  <div class="form-group">
    <label for="password">Create Password</label>
    <input type="password" id="password" name="password" placeholder="Choose a strong password" required />
  </div>

  <div class="form-group">
    <label for="confirmPassword">Confirm Password</label>
    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Re-enter your password" required />
  </div>

  <div class="form-group">
    <label>
      <input type="checkbox" name="terms" required />
      I agree to the <a href="terms&conditions.php" target="_blank">Terms and Conditions</a> and <a href="privacy_policy.php" target="_blank">Privacy Policy</a>.
    </label>
  </div>

  <button type="submit" class="submit-button">Sign Up</button>

  <div class="register-link">
    Already have an account? <a href="log_in.php">Log In Here</a>
  </div>

  <footer>
    &copy; <span id="current-year"></span> YUSAI Brand Company. All rights reserved.
  </footer>
</form>

<script>
  // Set current year in footer
  document.getElementById('current-year').textContent = new Date().getFullYear();
  
  // Optional: Clear the referral code field if it's empty and user focuses on it
  document.getElementById('referralCode').addEventListener('focus', function() {
    if (this.value === 'YUSAI1') {
      this.value = '';
    }
  });
</script>

</body>
</html>