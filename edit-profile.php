<?php
require_once 'db.php';
// Add PHPMailer for better email handling
require_once 'vendor/autoload.php'; // Make sure to install PHPMailer via Composer
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: log_in.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';
$verificationPending = false;
$currentEmail = '';
$pendingEmail = '';

// Fetch user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Set email status variables
$currentEmail = $user['email'];
$pendingEmail = $user['new_email'] ?? '';
$verificationPending = !empty($pendingEmail);

// Function to send email using SMTP
function sendVerificationEmail($to, $name, $token) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'mail.yusaibrand.co.ke';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@yusaibrand.co.ke';
        $mail->Password   = 'XQM~FF!0,Zs!fK4-';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        
        // Recipients
        $mail->setFrom('info@yusaibrand.co.ke', 'YUSAI BRAND COMPANY');
        $mail->addAddress($to, $name);
        $mail->addReplyTo('support@yusaibrand.co.ke', 'Support Team');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email Address';
        
        $verifyLink = "https://yusaibrand.co.ke/verify_email.php?token=$token";
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4361ee; color: white; padding: 20px; text-align: center; }
                    .content { background: #f9f9f9; padding: 20px; }
                    .button { background: black; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
                    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 14px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>YUSAI BRAND COMPANY</h1>
                    </div>
                    <div class='content'>
                        <h2>Email Verification</h2>
                        <p>Hi $name,</p>
                        <p>Please click the button below to verify your email address:</p>
                        <p style='text-align: center;'>
                            <a href='$verifyLink' class='button'>Verify Email</a>
                        </p>
                        <p>Or copy and paste this link in your browser:<br>
                        <code>$verifyLink</code></p>
                        <p>If you didn't request this change, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " YUSAI BRAND COMPANY. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Hi $name,\n\nPlease verify your email address by clicking this link: $verifyLink\n\nIf you didn't request this change, please ignore this email.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// RESEND VERIFICATION trigger
if (isset($_GET['resend']) && $verificationPending) {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE users SET email_verification_token = ?, token_created_at = NOW() WHERE id = ?")
        ->execute([$token, $user_id]);

    if (sendVerificationEmail($pendingEmail, $user['name'], $token)) {
        $successMessage = "Verification email resent to " . htmlspecialchars($pendingEmail);
    } else {
        $errorMessage = "Failed to send verification email. Please try again later.";
    }
}

// HANDLE PROFILE UPDATES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];

        $emailChanged = $email !== $currentEmail && $email !== $pendingEmail;

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Invalid email format.";
        } else {
            // Check email uniqueness
            $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $emailCheck->execute([$email, $user_id]);
            if ($emailCheck->rowCount() > 0) {
                $errorMessage = "This email is already in use by another account.";
            } elseif (!empty($password) && $password !== $confirmPassword) {
                $errorMessage = "Passwords do not match.";
            } elseif ($verificationPending && $emailChanged) {
                $errorMessage = "You already have a pending email change. Please verify your new email first.";
            } else {
                // Profile image handling
                $imagePath = $user['profile_image'];
                if (!empty($_FILES['profile_image']['name'])) {
                    $uploadDir = 'Uploads/user_profile_images/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileTmp = $_FILES['profile_image']['tmp_name'];
                    $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                    $newFileName = 'user_' . $user_id . '_' . time() . '.' . $ext;
                    $targetPath = $uploadDir . $newFileName;

                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        if (move_uploaded_file($fileTmp, $targetPath)) {
                            // Delete old profile image if it exists
                            if ($imagePath && file_exists($imagePath)) {
                                @unlink($imagePath);
                            }
                            $imagePath = $targetPath;
                        } else {
                            $errorMessage = 'Image upload failed. Please try again.';
                        }
                    } else {
                        $errorMessage = 'Unsupported image type. Please use JPG, JPEG, PNG, or GIF.';
                    }
                }

                if (!$errorMessage) {
                    if ($emailChanged) {
                        $token = bin2hex(random_bytes(32));
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, new_email = ?, email_verification_token = ?, token_created_at = NOW(), profile_image = ? WHERE id = ?");
                        $stmt->execute([$name, $phone, $email, $token, $imagePath, $user_id]);

                        if (sendVerificationEmail($email, $name, $token)) {
                            $successMessage = "Profile saved. Please check your inbox at <strong>$email</strong> to verify your new email address.";
                            $verificationPending = true;
                            $pendingEmail = $email;
                        } else {
                            $errorMessage = "Profile saved but failed to send verification email. Please contact support.";
                        }
                    } else {
                        $query = "UPDATE users SET name = ?, phone = ?, profile_image = ?";
                        $params = [$name, $phone, $imagePath];

                        if (!empty($password)) {
                            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                            $query .= ", password = ?";
                            $params[] = $hashedPassword;
                        }

                        $query .= " WHERE id = ?";
                        $params[] = $user_id;

                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);

                        $successMessage = "Profile updated successfully!";
                    }

                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    $currentEmail = $user['email'];
                    $pendingEmail = $user['new_email'] ?? '';
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error processing form: " . $e->getMessage());
        $errorMessage = "An error occurred while updating your profile. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile | Yusaibrand</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/edit-profile.css">
  
  
  <!-- Favicons -->
  <link rel="icon" type="image/png" href="my-favicon/favicon-96x96.png" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="my-favicon/favicon.svg" />
  <link rel="shortcut icon" href="my-favicon/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="my-favicon/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-title" content="yusaibrand" />
  <link rel="manifest" href="my-favicon/site.webmanifest" />
  
  
</head>
<body>
    
      <?php include 'include/header.php'; ?>
      
  <div class="container">
    <div class="profile-header">
      <h1><i class="fas fa-user-circle"></i> Edit Your Profile</h1>
      <p>Manage your account information and security settings</p>
    </div>
    
    <?php if ($successMessage): ?>
      <div class="message success-message">
        <i class="fas fa-check-circle"></i>
        <span><?= $successMessage ?></span>
      </div>
    <?php elseif ($errorMessage): ?>
      <div class="message error-message">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= $errorMessage ?></span>
      </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" id="profileForm">
      <div class="profile-card">
        <div class="card-header">
          <h2><i class="fas fa-user"></i> Personal Information</h2>
        </div>
        
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="name">Full Name</label>
              <input type="text" class="form-control" name="name" id="name" 
                     value="<?= htmlspecialchars($user['name']) ?>" required
                     aria-describedby="nameHelp">
              <div class="password-note" id="nameHelp">Your full name as you want it to appear</div>
            </div>
            
            <div class="form-group">
              <label class="form-label" for="phone">Phone Number</label>
              <input type="tel" class="form-control" name="phone" id="phone" 
                     value="<?= htmlspecialchars($user['phone']) ?>" required
                     aria-describedby="phoneHelp">
              <div class="password-note" id="phoneHelp">We'll never share your phone number</div>
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input type="email" class="form-control" name="email" id="email" 
                   value="<?= $verificationPending ? htmlspecialchars($pendingEmail) : htmlspecialchars($currentEmail) ?>" required
                   aria-describedby="emailHelp">
            <div class="password-note" id="emailHelp">
              Changing your email requires verification
            </div>
          </div>
          
          <!-- Email Status Display -->
          <div class="email-status">
            <div class="current-email">
              <i class="fas fa-check-circle"></i>
              <span><strong>Current Email:</strong> <?= htmlspecialchars($currentEmail) ?></span>
            </div>
            
            <?php if ($verificationPending): ?>
              <div class="pending-email">
                <i class="fas fa-clock"></i>
                <span><strong>Pending Verification:</strong> <?= htmlspecialchars($pendingEmail) ?></span>
              </div>
              <a class="resend-link" href="edit-profile.php?resend=1">
                <i class="fas fa-paper-plane"></i> Resend Verification Email
              </a>
            <?php endif; ?>
          </div>
          
          <div class="profile-img-container">
            <?php if (!empty($user['profile_image'])): ?>
              <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile Image" class="profile-img-preview" id="profile-preview">
            <?php else: ?>
              <div class="profile-img-preview" id="profile-preview" style="background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-user" style="font-size: 3.5rem; color: white;"></i>
              </div>
            <?php endif; ?>
            
            <label class="img-upload-label">
              <i class="fas fa-upload"></i> Change Profile Picture
              <input type="file" name="profile_image" id="profile_image" accept="image/*" style="display: none;"
                     aria-label="Upload profile picture">
            </label>
          </div>
        </div>
      </div>
      
      <div class="profile-card">
        <div class="card-header">
          <h2><i class="fas fa-lock"></i> Security Settings</h2>
        </div>
        
        <div class="card-body">
          <div class="password-section">
            <div class="form-group">
              <label class="form-label" for="password">New Password</label>
              <input type="password" class="form-control" name="password" id="password" 
                     placeholder="Enter new password" autocomplete="new-password"
                     aria-describedby="passwordHelp">
              <div class="password-note" id="passwordHelp">
                Leave blank to keep current password
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label" for="confirmPassword">Confirm Password</label>
              <input type="password" class="form-control" name="confirmPassword" id="confirmPassword" 
                     placeholder="Confirm new password" autocomplete="new-password">
            </div>
          </div>
          
          <div class="status-card">
            <div class="status-icon">
              <i class="fas fa-shield-alt"></i>
            </div>
            <div>
              <h3>Security Tips</h3>
              <p>Use a strong, unique password and update it regularly. Never share your password with anyone.</p>
            </div>
          </div>
        </div>
      </div>
      
      <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
        <i class="fas fa-save"></i> Save Changes
      </button>
    </form>
    
    <footer>
      <p>© <?= date('Y') ?> Yusaibrand. All rights reserved. | Need help? Contact support@yusaibrand.co.ke</p>
    </footer>
  </div>

  <script>
    // Profile image preview
    document.getElementById('profile_image').addEventListener('change', function(e) {
      const preview = document.getElementById('profile-preview');
      const file = e.target.files[0];
      
      if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
          if (preview.tagName === 'IMG') {
            preview.src = e.target.result;
          } else {
            // Replace placeholder with image
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'profile-img-preview';
            img.alt = 'Profile Preview';
            img.id = 'profile-preview';
            preview.parentNode.replaceChild(img, preview);
          }
        }
        
        reader.readAsDataURL(file);
      }
    });
    
    // Form submission handling
    document.getElementById('profileForm').addEventListener('submit', function(e) {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      const submitBtn = document.getElementById('submitBtn');
      
      if (password && password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match. Please check and try again.');
        return;
      }
      
      // Show loading state
      submitBtn.classList.add('btn-loading');
      submitBtn.disabled = true;
      
      // Re-enable after 5 seconds in case submission fails
      setTimeout(() => {
        submitBtn.classList.remove('btn-loading');
        submitBtn.disabled = false;
      }, 5000);
    });
    
    // Input validation
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
      input.addEventListener('blur', function() {
        if (this.value.trim() === '' && this.hasAttribute('required')) {
          this.style.borderColor = 'var(--danger)';
        } else {
          this.style.borderColor = '';
        }
      });
    });
  </script>
</body>
</html>