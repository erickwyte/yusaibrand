<?php
// terms_and_conditions.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Terms and Conditions - YUSAI Energy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
      --primary-blue: #12263f;
      --accent-blue: #2c7be5;
      --highlight-green: #00d97e;
      --light-bg: #f5f8fa;
      --card-bg: #ffffff;
      --text-dark: #333;
      --text-light: #666;
      --border-radius: 8px;
      --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      --transition: all 0.3s ease;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: var(--light-bg);
      color: var(--text-dark);
      line-height: 1.6;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 800px;
      margin: 2rem auto;
      background: var(--card-bg);
      padding: 2.5rem;
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
    }

    h1 {
      text-align: center;
      color: var(--primary-blue);
      margin-bottom: 1.5rem;
      position: relative;
      padding-bottom: 1rem;
    }

    h1::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 4px;
      background: var(--highlight-green);
      border-radius: 2px;
    }

    h2 {
      color: var(--primary-blue);
      margin-top: 2rem;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid var(--accent-blue);
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    h2 i {
      color: var(--accent-blue);
      font-size: 1.2rem;
    }

    p {
      margin-bottom: 1.2rem;
    }

    a {
      color: var(--accent-blue);
      text-decoration: none;
      font-weight: 500;
    }

    a:hover {
      text-decoration: underline;
      color: var(--primary-blue);
    }

    .important-note {
      background-color: rgba(44, 123, 229, 0.1);
      padding: 1.2rem;
      border-radius: var(--border-radius);
      border-left: 4px solid var(--accent-blue);
      margin: 1.5rem 0;
    }

    footer {
      text-align: center;
      margin-top: 3rem;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(0, 0, 0, 0.1);
      color: var(--text-light);
      font-size: 0.9rem;
    }

    ul {
      margin-left: 1.5rem;
      margin-bottom: 1.5rem;
    }

    li {
      margin-bottom: 0.8rem;
      position: relative;
      padding-left: 1.5rem;
    }

    li::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0.7rem;
      width: 8px;
      height: 8px;
      background-color: var(--highlight-green);
      border-radius: 50%;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .container {
        margin: 1rem auto;
        padding: 1.5rem;
        
      }

      h1 {
        font-size: 1.8rem;
      }

      h2 {
        font-size: 1.3rem;
      }
    }

    @media (max-width: 480px) {
      .container {
        margin: 0.5rem auto;
        padding: 1.2rem;
        border-radius: 0;
      }

      h1 {
        font-size: 1.6rem;
        padding-bottom: 0.8rem;
      }

      h1::after {
        width: 60px;
        height: 3px;
      }

      h2 {
        font-size: 1.2rem;
      }

      li {
        padding-left: 1.2rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Terms and Conditions</h1>

    <div class="important-note">
      <p><strong>Important:</strong> By using YUSAI Energy's gas delivery services, you agree to these Terms and Conditions. Please read them carefully before placing an order.</p>
    </div>

    <h2><i class="fas fa-check-circle"></i> 1. Acceptance of Terms</h2>
    <p>By accessing or using our gas delivery services, you confirm that you accept these terms and agree to comply with them. If you do not agree, you must not use our services.</p>

    <h2><i class="fas fa-gas-pump"></i> 2. Service Usage</h2>
    <p>You agree to use our services only for lawful purposes related to ordering gas and related products. Specifically:</p>
    <ul>
      <li>All orders must be for legitimate personal or business use</li>
      <li>You must provide accurate delivery information</li>
      <li>You must be at least 18 years old to place an order</li>
      <li>You will not misuse our services or website</li>
    </ul>

    <h2><i class="fas fa-money-bill-wave"></i> 3. Payments and Pricing</h2>
    <p>All transactions are conducted in Kenyan Shillings (KES):</p>
    <ul>
      <li>We accept M-Pesa payments only</li>
      <li>Prices are subject to change without notice</li>
      <li>Payment must be completed before delivery is initiated</li>
      <li>All sales are final unless delivery cannot be completed</li>
    </ul>

    <h2><i class="fas fa-truck"></i> 4. Delivery Terms</h2>
    <p>Our delivery service operates under these conditions:</p>
    <ul>
      <li>Standard delivery time is 5-15 minutes in Meru County</li>
      <li>You must provide safe access for delivery personnel</li>
      <li>Someone must be available to receive the delivery</li>
      <li>We reserve the right to refuse unsafe delivery locations</li>
    </ul>

    <h2><i class="fas fa-shield-alt"></i> 5. Safety and Liability</h2>
    <p>Gas handling requires proper safety measures:</p>
    <ul>
      <li>You are responsible for proper storage and use after delivery</li>
      <li>We are not liable for misuse or improper installation</li>
      <li>Our liability is limited to the value of the gas delivered</li>
      <li>Report any issues immediately to our customer service</li>
    </ul>

    <h2><i class="fas fa-user-slash"></i> 6. Account Suspension</h2>
    <p>We reserve the right to suspend or terminate service to any customer who:</p>
    <ul>
      <li>Violates these terms and conditions</li>
      <li>Provides false information</li>
      <li>Abuses our delivery personnel</li>
      <li>Engages in fraudulent activities</li>
    </ul>

    <h2><i class="fas fa-calendar-alt"></i> 7. Changes to Terms</h2>
    <p>We may revise these terms periodically. The updated version will be effective immediately upon posting on our website.</p>

    <h2><i class="fas fa-headset"></i> 8. Contact Information</h2>
    <p>For questions about these terms, contact us:</p>
    <ul>
      <li><strong>Phone/WhatsApp:</strong> <a href="tel:+254719122571">+254 719 122 571</a></li>
      <li><strong>Email:</strong> <a href="mailto:saidiyusuf203@gmail.com">saidiyusuf203@gmail.com</a></li>
      <li><strong>Business Hours:</strong> 7:00 AM - 9:00 PM daily</li>
    </ul>

    <footer>
      &copy; <?= date("Y") ?> YUSAI Energy. All rights reserved.
      <p><small>Last updated: <?php echo date('F j, Y'); ?></small></p>
    </footer>
  </div>
</body>
</html>