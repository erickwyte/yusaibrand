<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FAQs - YUSAI Energy</title>
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
      background: var(--light-bg);
      color: var(--text-dark);
      line-height: 1.6;
    }

    .container {
      max-width: 900px;
      margin: 2rem auto;
      padding: 2rem;
      background-color: var(--card-bg);
      border-radius: var(--border-radius);
      box-shadow: var(--box-shadow);
    }

    h1 {
      text-align: center;
      color: var(--primary-blue);
      margin-bottom: 2rem;
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

    .faq-category {
      margin-top: 2.5rem;
    }

    .faq-category h2 {
      color: var(--primary-blue);
      margin-bottom: 1.2rem;
      font-size: 1.3rem;
      border-left: 4px solid var(--accent-blue);
      padding-left: 1rem;
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    .faq-item {
      margin: 1.2rem 0;
      padding: 1.2rem 1.5rem;
      border-radius: var(--border-radius);
      background-color: var(--card-bg);
      box-shadow: var(--box-shadow);
      transition: var(--transition);
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .faq-item:hover {
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    .faq-question {
      font-weight: 600;
      cursor: pointer;
      position: relative;
      padding-right: 2rem;
      color: var(--primary-blue);
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    .faq-question i {
      color: var(--accent-blue);
      font-size: 1.1rem;
    }

    .faq-answer {
      margin-top: 1rem;
      display: none;
      padding-left: 1.8rem;
      color: var(--text-light);
      border-left: 2px solid var(--highlight-green);
      padding-left: 1rem;
      margin-left: 0.5rem;
    }

    .faq-question::after {
      content: '\f067';
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      position: absolute;
      right: 0;
      font-size: 1rem;
      color: var(--accent-blue);
      transition: var(--transition);
    }

    .faq-item.active .faq-question::after {
      content: '\f068';
    }

    .faq-item.active .faq-answer {
      display: block;
      animation: fadeIn 0.3s ease-out;
    }

    .faq-item.active {
      border-left: 3px solid var(--highlight-green);
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

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-5px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .container {
        margin: 1rem auto;
        padding: 1.5rem;
      }

      h1 {
        font-size: 1.8rem;
        margin-bottom: 1.5rem;
      }

      .faq-category {
        margin-top: 2rem;
      }

      .faq-category h2 {
        font-size: 1.1rem;
      }

      .faq-item {
        padding: 1rem;
      }

      .faq-question {
        font-size: 1rem;
      }
    }

    @media (max-width: 480px) {
      .container {
        margin: 0.5rem auto;
        padding: 1rem;
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

      .faq-category h2 {
        font-size: 1rem;
      }

      .faq-question {
        padding-right: 1.5rem;
      }

      .faq-question::after {
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>



<div class="container">
  <h1>Frequently Asked Questions</h1>

  <div class="faq-category">
    <h2><i class="fas fa-truck"></i> Orders & Delivery</h2>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-clock"></i> How long does delivery take?</div>
      <div class="faq-answer">Most deliveries take between 5 to 15 minutes depending on your location. We pride ourselves on fast, reliable service.</div>
    </div>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-exclamation-triangle"></i> Do you offer emergency delivery?</div>
      <div class="faq-answer">Yes! Contact us directly via WhatsApp at <a href="https://wa.me/254719122571">+254 719 122 571</a> for immediate assistance.</div>
    </div>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-map-marker-alt"></i> Which areas do you serve?</div>
      <div class="faq-answer">We serve all of Meru County with plans to expand soon. Contact us to confirm service in your specific location.</div>
    </div>
  </div>

  <div class="faq-category">
    <h2><i class="fas fa-money-bill-wave"></i> Payments</h2>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-credit-card"></i> Which payment methods do you accept?</div>
      <div class="faq-answer">We accept M-Pesa payments for all orders. Business customers may arrange for invoice payments.</div>
    </div>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-lock"></i> Is payment required before delivery?</div>
      <div class="faq-answer">Yes, payment must be confirmed before we dispatch your order. You'll receive payment confirmation via SMS or WhatsApp.</div>
    </div>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-money-bill-alt"></i> Can I pay on delivery?</div>
      <div class="faq-answer">For security reasons, we require payment before delivery. Regular customers may qualify for credit terms.</div>
    </div>
  </div>

  <div class="faq-category">
    <h2><i class="fas fa-gas-pump"></i> Products & Services</h2>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-box-open"></i> What products can I order?</div>
      <div class="faq-answer">We specialize in gas cylinder refills and also purchase empty cylinders. All standard gas accessories are available.</div>
    </div>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-certificate"></i> Are your products certified?</div>
      <div class="faq-answer">Yes, we only deal with certified gas products that meet all Kenyan safety standards.</div>
    </div>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-question-circle"></i> Can I request a product not listed?</div>
      <div class="faq-answer">Yes, contact us directly via WhatsApp or phone to inquire about special requests.</div>
    </div>
  </div>

  <div class="faq-category">
    <h2><i class="fas fa-user-circle"></i> Account & Support</h2>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-user-plus"></i> Do I need to register to place an order?</div>
      <div class="faq-answer">No registration is required for one-time orders. Accounts help track order history and access offers.</div>
    </div>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-headset"></i> How do I contact support?</div>
      <div class="faq-answer">Reach us 24/7 via WhatsApp at <a href="https://wa.me/254719122571">+254 719 122 571</a>, call us, or email info@yusufjahomeenergy.com.</div>
    </div>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-exchange-alt"></i> Can I cancel or change an order?</div>
      <div class="faq-answer">You may cancel or modify orders within 5 minutes of placement due to our fast delivery times.</div>
    </div>
  </div>

  <div class="faq-category">
    <h2><i class="fas fa-shield-alt"></i> Safety & Quality</h2>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-check-circle"></i> Are your delivery personnel trained?</div>
      <div class="faq-answer">All staff undergo rigorous safety training and carry proper identification.</div>
    </div>

    <div class="faq-item">
      <div class="faq-question"><i class="fas fa-user-secret"></i> Do you share my contact information?</div>
      <div class="faq-answer">No. Your information is kept strictly confidential and only used for essential communications.</div>
    </div>
  </div>
</div>

<script>
  // Toggle FAQ items with animation
  document.querySelectorAll('.faq-question').forEach(q => {
    q.addEventListener('click', () => {
      const item = q.parentElement;
      item.classList.toggle('active');
      
      // Close other open items in the same category
      const category = item.parentElement;
      category.querySelectorAll('.faq-item').forEach(otherItem => {
        if (otherItem !== item && otherItem.classList.contains('active')) {
          otherItem.classList.remove('active');
        }
      });
    });
  });

  // Open first item in each category by default on mobile
  if (window.innerWidth <= 768) {
    document.querySelectorAll('.faq-category').forEach(category => {
      const firstItem = category.querySelector('.faq-item');
      if (firstItem) firstItem.classList.add('active');
    });
  }
</script>

</body>
</html>