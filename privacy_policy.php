<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Privacy Policy - YUSAI Energy</title>
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
      margin: 0;
      padding: 0;
      color: var(--text-dark);
      line-height: 1.6;
    }

    .container {
      max-width: 900px;
      margin: 2rem auto;
      background-color: var(--card-bg);
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
      margin-top: 2rem;
      color: var(--primary-blue);
      padding-bottom: 0.5rem;
      border-bottom: 2px solid var(--accent-blue);
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    h2 i {
      color: var(--accent-blue);
    }

    p {
      line-height: 1.8;
      margin-bottom: 1.2rem;
      color: var(--text-dark);
    }

    ul {
      margin-left: 0.5rem;
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

    a {
      color: var(--accent-blue);
      text-decoration: none;
      font-weight: 500;
    }

    a:hover {
      text-decoration: underline;
      color: var(--primary-blue);
    }

    .contact-info {
      background-color: rgba(44, 123, 229, 0.1);
      padding: 1.2rem;
      border-radius: var(--border-radius);
      border-left: 4px solid var(--accent-blue);
      margin-top: 1rem;
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
  <h1>Privacy Policy</h1>

  <p>At YUSAI Energy, we are committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our gas delivery services.</p>

  <h2><i class="fas fa-info-circle"></i> 1. Information We Collect</h2>
  <p>We may collect the following types of information:</p>
  <ul>
    <li><strong>Personal Details:</strong> Your name, contact information (phone number, email), and delivery address</li>
    <li><strong>Transaction Data:</strong> Details about your gas orders, payments, and delivery history</li>
    <li><strong>Device Information:</strong> IP address, browser type, and operating system when you use our website</li>
    <li><strong>Location Data:</strong> Approximate location for delivery purposes (with your consent)</li>
  </ul>

  <h2><i class="fas fa-user-shield"></i> 2. How We Use Your Information</h2>
  <p>Your information helps us provide and improve our services:</p>
  <ul>
    <li>Process and fulfill your gas delivery orders</li>
    <li>Communicate about your orders, deliveries, and account</li>
    <li>Improve our services and customer experience</li>
    <li>Ensure the security of our systems and prevent fraud</li>
    <li>Comply with legal obligations and regulations</li>
  </ul>

  <h2><i class="fas fa-share-alt"></i> 3. Information Sharing</h2>
  <p>We value your privacy and do not sell your personal information. We may share data with:</p>
  <ul>
    <li><strong>Delivery Personnel:</strong> Only the necessary information to complete your delivery</li>
    <li><strong>Payment Processors:</strong> To securely process your payments</li>
    <li><strong>Legal Authorities:</strong> When required by law or to protect our rights</li>
    <li><strong>Service Providers:</strong> Companies that help us operate our business (with strict confidentiality agreements)</li>
  </ul>

  <h2><i class="fas fa-cookie-bite"></i> 4. Cookies and Tracking</h2>
  <p>Our website uses cookies to:</p>
  <ul>
    <li>Remember your preferences and login information</li>
    <li>Analyze website traffic and usage patterns</li>
    <li>Improve our website functionality</li>
  </ul>
  <p>You can control cookies through your browser settings.</p>

  <h2><i class="fas fa-lock"></i> 5. Data Security</h2>
  <p>We implement robust security measures including:</p>
  <ul>
    <li>Encryption of sensitive data during transmission</li>
    <li>Secure storage of your information</li>
    <li>Regular security audits and updates</li>
    <li>Limited access to your personal data</li>
  </ul>

  <h2><i class="fas fa-user-cog"></i> 6. Your Rights</h2>
  <p>You have the right to:</p>
  <ul>
    <li>Access the personal information we hold about you</li>
    <li>Request correction of inaccurate data</li>
    <li>Request deletion of your personal data (subject to legal requirements)</li>
    <li>Opt-out of marketing communications</li>
  </ul>

  <h2><i class="fas fa-calendar-alt"></i> 7. Policy Updates</h2>
  <p>We may update this policy periodically. The updated version will be posted on our website with the effective date. We encourage you to review this policy regularly.</p>

  <h2><i class="fas fa-envelope"></i> 8. Contact Us</h2>
  <p>For any privacy-related questions or requests, please contact us:</p>
  <div class="contact-info">
    <p><strong>Email:</strong> <a href="mailto:saidiyusuf203@gmail.com">saidiyusuf203@gmail.com</a></p>
    <p><strong>Phone/WhatsApp:</strong> <a href="tel:+254719122571">+254 719 122 571</a></p>
    <p><strong>Business Hours:</strong> Mon-Sun, 7:00 AM - 9:00 PM</p>
  </div>
  <p><em>Last Updated: <?php echo date('F j, Y'); ?></em></p>
</div>

</body>
</html>