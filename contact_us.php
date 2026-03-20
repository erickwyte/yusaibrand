<?php
// send-message.php - Handles contact form submissions and stores messages in DB

session_start();
require_once 'db.php';




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = htmlspecialchars(trim($_POST['name']));
    $email   = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars(trim($_POST['subject']));
    $message = htmlspecialchars(trim($_POST['message']));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Invalid email address.";
        header("Location: contact_us.php");
        exit;
    }

    // Save message to DB
    $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $subject, $message]);


    // For popup message
    $_SESSION['popup_message'] = "✅ Thank you, {$name}! Your message has been sent successfully.";
    header("Location: contact_us.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us - YUSAI Energy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
 <link rel="stylesheet" href="css/contact_us.css">
 
   
  <!-- Favicons -->
 <link rel="icon" type="image/png" href="my-favicon/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="my-favicon/favicon.svg" />
<link rel="shortcut icon" href="my-favicon/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="my-favicon/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="yusaibrand" />
<link rel="manifest" href="my-favicon/site.webmanifest" />

<style>
    /* Popup Styles */
        .popup-overlay {
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
            transition: all 0.4s ease;
        }

        .popup-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .popup-container {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
            transform: translateY(50px) scale(0.9);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .popup-overlay.active .popup-container {
            transform: translateY(0) scale(1);
        }

        .popup-header {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            padding: 25px;
            text-align: center;
            position: relative;
        }

        .popup-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .popup-icon i {
            font-size: 40px;
            color: #4CAF50;
        }

        .popup-header h3 {
            color: white;
            font-size: 24px;
            margin: 0;
            font-weight: 600;
        }

        .popup-body {
            padding: 30px;
            text-align: center;
            font-size: 18px;
            line-height: 1.6;
            color: #333;
        }

        .popup-close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .popup-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .popup-footer {
            padding: 0 30px 30px;
            text-align: center;
        }

        .popup-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 35px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }

        .popup-btn:hover {
            background: #388E3C;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.5);
        }
</style>

</head>
<body>
  <header>
    <?php include 'include/header.php'; ?>
    <div class="empty">

    </div>
  </header>

  <div class="hero">
    <div class="container">
      <h1>Contact YUSAI BRAND COMPANY</h1>
      <p>We're here to serve! Reach out for fast gas delivery, empty cylinder pickup, or any inquiries. Our dedicated team is ready to assist you.</p>
    </div>
  </div>

  <div class="container">
    

    <div class="contact-methods">
      <!-- Call Section -->
      <div class="contact-section">
        <h3><i class="fas fa-phone-alt"></i> Call Us</h3>
        <p>Speak directly with our support team for immediate assistance:</p>
        <p><a href="tel:+254719122571"><i class="fas fa-phone"></i> +254 719 122 571</a></p>
        <p>Quick, courteous and ready to assist you!</p>
      </div>

      <!-- WhatsApp Section -->
      <div class="contact-section">
        <h3><i class="fab fa-whatsapp"></i> WhatsApp</h3>
        <p>Chat live with us for quick responses and support:</p>
        <p><a href="https://wa.me/254719122571" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Start WhatsApp Chat</a></p>
        <p>Average response time: under 15 minutes</p>
      </div>

<!-- Email -->
<div class="contact-section">
  <h3><i class="fas fa-envelope"></i> Email</h3>
  <p>For business and customer service inquiries:</p>
  
  <p>
    <a href="mailto:saidiyusuf203@gmail.com">
      <i class="fas fa-envelope"></i> 
      saidiyusuf203@gmail.com
    </a>
   
  </p>

  <p>We typically respond within 24 hours</p>
</div>



      
    </div>

    <!-- Separated Contact Form and Map Section -->
    <div class="contact-form-map">
      <!-- Contact Form -->
      <div class="contact-section">
        <h3><i class="fas fa-paper-plane"></i> Send Us a Message</h3>
        <form action="" method="POST" class="contact-form">
          <div class="form-group">
            <label for="name">Your Name *</label>
            <input type="text" id="name" name="name" required placeholder="Enter your name">
          </div>

          <div class="form-group">
            <label for="email">Your Email *</label>
            <input type="email" id="email" name="email" required placeholder="Enter your email">
          </div>

          <div class="form-group">
            <label for="subject">Subject *</label>
            <input type="text" id="subject" name="subject" required placeholder="Enter subject">
          </div>

          <div class="form-group">
            <label for="message">Message *</label>
            <textarea id="message" name="message" rows="5" required placeholder="Write your message here..."></textarea>
          </div>

          <button type="submit" class="submit-button">
            <i class="fas fa-paper-plane"></i> Send Message
          </button>
        </form>
      </div>

      <!-- Location -->
      <div class="contact-section">
       <h3><i class="fas fa-map-marker-alt"></i> Our Location</h3>
<p>We're based in <strong>Meru County</strong>, primarily around <strong>Meru University</strong> and surrounding areas including Makutano, Meru Town, Kianjai, Kirendine, California, St. Rita, Victory Apartment, and Shaquille. We offer fast and reliable door-to-door gas deliveries across these regions.</p>

        <div class="map-container">
          <iframe 
              src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d15955.768040030215!2d37.6525!3d0.1000!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x17885bb493426f37%3A0xe50a2c6a8933c111!2sMeru%20University%20of%20Science%20and%20Technology!5e0!3m2!1sen!2ske!4v1722661012345" 
              width="100%" 
              height="450" 
              style="border:0;" 
              allowfullscreen="" 
              loading="lazy" 
              referrerpolicy="no-referrer-when-downgrade">
          </iframe>

        </div>
        <p style="text-align: center; margin-top: 15px; font-style: italic; color: #666;">
          <i class="fas fa-info-circle"></i> We deliver directly to you. The map is just for regional reference.
        </p>
      </div>
    </div>
  </div>
  
<!-- Popup HTML -->
<div class="popup-overlay" id="popupOverlay">
    <div class="popup-container">
        <div class="popup-header">
            <button class="popup-close-btn" id="popupCloseBtn">&times;</button>
            <div class="popup-icon">
                <i class="fas fa-check"></i>
            </div>
            <h3>Success!</h3>
        </div>
        <div class="popup-body" id="popupMessage">
            <!-- Message will be inserted here -->
        </div>
        <div class="popup-footer">
            <button class="popup-btn" id="popupActionBtn">Continue</button>
        </div>
    </div>
</div>

<script>
    // Show popup if there's a message
    document.addEventListener('DOMContentLoaded', function() {
        const popupOverlay = document.getElementById('popupOverlay');
        const popupMessage = document.getElementById('popupMessage');
        
        <?php if (isset($_SESSION['popup_message'])): ?>
            // Set message content
            popupMessage.innerHTML = "<?= addslashes($_SESSION['popup_message']) ?>";
            
            // Show popup
            setTimeout(() => {
                popupOverlay.classList.add('active');
            }, 300);
            
            // Clear session message
            <?php unset($_SESSION['popup_message']); ?>
        <?php endif; ?>
        
        // Close button functionality
        document.getElementById('popupCloseBtn').addEventListener('click', function() {
            popupOverlay.classList.remove('active');
        });
        
        // Action button functionality
        document.getElementById('popupActionBtn').addEventListener('click', function() {
            popupOverlay.classList.remove('active');
        });
        
        // Close when clicking outside popup
        popupOverlay.addEventListener('click', function(e) {
            if (e.target === popupOverlay) {
                popupOverlay.classList.remove('active');
            }
        });
        
        // Auto-close after 5 seconds
        const autoClosePopup = setTimeout(() => {
            if (popupOverlay.classList.contains('active')) {
                popupOverlay.classList.remove('active');
            }
        }, 5000);
    });
</script>

  <?php include 'include/footer.php'; ?>
</body>
</html>