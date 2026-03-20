<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Emergency Delivery - YUSAI Energy</title>
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
      margin: 0;
      padding: 0;
      background-color: var(--light-bg);
      color: var(--text-dark);
      line-height: 1.6;
    }

    .container {
      max-width: 800px;
      margin: 2rem auto;
      background-color: var(--card-bg);
      padding: 2.5rem;
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
      text-align: center;
    }

    h1 {
      color: var(--primary-blue);
      
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1rem;
    }

    h1::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 4px;
     
      border-radius: 2px;
    }

    p {
      line-height: 1.8;
      margin-bottom: 1.5rem;
      font-size: 1.1rem;
      text-align: center;
    }

    .highlight {
      background-color: rgba(44, 123, 229, 0.1);
      padding: 1.2rem;
      border-left: 4px solid var(--accent-blue);
      margin: 2rem 0;
      font-weight: 600;
      color: var(--primary-blue);
      border-radius: var(--border-radius);
      text-align: left;
    }

    .highlight i {
      color: var(--accent-blue);
      margin-right: 0.5rem;
    }

    .cta-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.8rem;
      width: 100%;
      max-width: 300px;
      margin: 1.5rem auto;
      padding: 1rem;
      background-color: var(--highlight-green);
      color: white;
      text-align: center;
      font-size: 1.2rem;
      font-weight: 600;
      border: none;
      border-radius: var(--border-radius);
      text-decoration: none;
      transition: var(--transition);
      box-shadow: 0 4px 12px rgba(0, 217, 126, 0.3);
    }

    .cta-button:hover {
      background-color: #00c571;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0, 217, 126, 0.4);
    }

    .note {
      text-align: center;
      font-size: 0.95rem;
      color: var(--text-light);
      margin-top: 1rem;
    }

    .features {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin: 2rem 0;
      text-align: left;
    }

    .feature {
      background: rgba(44, 123, 229, 0.05);
      padding: 1.2rem;
      border-radius: var(--border-radius);
      border: 1px solid rgba(44, 123, 229, 0.1);
    }

    .feature i {
      color: var(--accent-blue);
      font-size: 1.5rem;
      margin-bottom: 0.8rem;
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

      .features {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 480px) {
      .container {
        margin: 0.5rem auto;
        padding: 1.2rem;
        border-radius: 0;
      }

      h1 {
        font-size: 1.5rem;
        padding-bottom: 0.8rem;
      }

      h1::after {
        width: 60px;
        height: 3px;
      }

      p {
        font-size: 1rem;
        text-align: left;
      }

      .cta-button {
        padding: 0.8rem;
        font-size: 1.1rem;
      }
    }
  </style>
</head>
<body>
        <?php include 'include/header.php'; ?>
    <div class="empty">

    </div>

<div class="container">
  <h1 style="margin-bottom: 1.5rem; padding-bottom: 1rem;">
      <i class="fas fa-bolt"></i> Emergency Gas Delivery
    </h1>

  <p>Need gas delivered urgently? Our emergency service ensures you get your gas cylinder within 15 minutes in Meru County.</p>

  <div class="highlight">
    <i class="fas fa-clock"></i> Available daily from <strong>7:00 AM – 9:00 PM</strong> across Meru County. Contact us immediately for fastest service.
  </div>

  <div class="features">
    <div class="feature">
      <i class="fas fa-stopwatch"></i>
      <h3>Super Fast</h3>
      <p>Average delivery time of just 5-15 minutes in most areas</p>
    </div>
    <div class="feature">
      <i class="fas fa-shield-alt"></i>
      <h3>Safe & Reliable</h3>
      <p>Certified gas cylinders delivered by trained professionals</p>
    </div>
    <div class="feature">
      <i class="fas fa-headset"></i>
      <h3>24/7 Support</h3>
      <p>Direct WhatsApp connection to our support team</p>
    </div>
  </div>

  <a class="cta-button"
     href="https://wa.me/254719122571?text=Hello%20YUSAI%20Energy%20Team%2C%20I%20need%20EMERGENCY%20GAS%20DELIVERY.%20Here%20are%20my%20details%3A%20%0A-%20Name%3A%20%0A-%20Exact%20Location%3A%20%0A-%20Cylinder%20Size%3A%20"
     target="_blank">
    <i class="fab fa-whatsapp"></i> Request Now
  </a>

  <p class="note">Clicking the button will open WhatsApp with a pre-filled message. Please complete your details for faster service.</p>
</div>

</body>
</html>