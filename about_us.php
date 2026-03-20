<?php
session_start();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>About Us - Yusai Gas Services</title>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
  />
  <link rel="stylesheet" href="css/about_us.css">
 
   
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
    <div class="empty">

    </div>


  <main class="about-container">
    <!-- Hero Section -->
    <section class="about-hero">
      <h1>Your Trusted Gas Delivery Service</h1>
      <p>Providing safe, fast, and reliable gas refilling services with unmatched commitment to quality and customer satisfaction</p>
    </section>
   <!-- Safety Section -->
<section class="about-section">
  <h2 class="section-title">Gas Cylinder Safety Guidelines</h2>

  <div class="safety-cards">

    <!-- Card 1: Gas Leak Detection -->
    <div class="safety-card">
      <img src="images/leak-detection.jpg" alt="Detecting Gas Leakage" class="card-image">
      <h3>How to Detect a Gas Cylinder Leak</h3>
      <ul>
        <li>Strong, pungent odor like rotten eggs – an odorant is added to help detect leaks</li>
        <li>Hissing sound near the valve, regulator, or hose</li>
        <li>Bubbles forming when soapy water is applied on the valve or pipe connections</li>
        <li>Gas usage meter or indicator showing flow even when stove is off</li>
        <li>Sudden drop in gas levels without actual usage</li>
      </ul>
    </div>

    <!-- Card 2: Responding to Leak -->
    <div class="safety-card">
      <img src="images/leak-response.avif" alt="Responding to a Gas Leak" class="card-image">
      <h3>What To Do If You Suspect a Gas Leak</h3>
      <ul>
        <li>Turn off the gas cylinder knob and regulator immediately</li>
        <li>Open all windows and doors for ventilation</li>
        <li>Do NOT light matches, turn on lights, or use electrical appliances</li>
        <li>Move the cylinder to a well-ventilated area if safe to do so</li>
        <li>Call your gas supplier or customer care immediately</li>
      </ul>
    </div>

    <!-- Card 3: Fire Emergency -->
    <div class="safety-card">
      <img src="images/fire-emergency.jpg" alt="Gas Fire Emergency" class="card-image">
      <h3>What To Do in Case of a Fire</h3>
      <ul>
        <li>Turn off the gas knob and regulator if it’s safe to approach</li>
        <li>Never pour water on a gas or oil fire</li>
        <li>Use a dry powder fire extinguisher if available</li>
        <li>Evacuate the building and alert others nearby</li>
        <li>Call emergency services or the fire department immediately</li>
        <li>Keep a safe distance from the cylinder and warn neighbors</li>
      </ul>
    </div>

  </div>
</section>


    <!-- Fast Delivery Section -->
    <section class="about-section">
      <h2 class="section-title">Lightning-Fast Delivery</h2>
      
      <div class="safety-content">
        <h2>Why Our Fast Delivery Matters</h2>
        <p>We've revolutionized our delivery system to ensure you never have to pause your daily life. Our advanced routing algorithms and strategically located distribution centers enable us to reach you faster than any competitor.</p>
      </div>

      <div class="delivery-benefits">
        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-clock"></i>
          </div>
          <div class="benefit-content">
            <h3>Time-Saving</h3>
            <p>No more waiting for hours or planning your day around gas delivery</p>
          </div>
        </div>
        
        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-shield-alt"></i>
          </div>
          <div class="benefit-content">
            <h3>Reliable Service</h3>
            <p>Consistent on-time delivery with professional handlers</p>
          </div>
        </div>
        
        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-users"></i>
          </div>
          <div class="benefit-content">
            <h3>24/7 Availability</h3>
            <p>Emergency delivery services available around the clock</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Services Section -->
    <section class="about-section">
      <h2 class="section-title">Our Services</h2>
      
      <div class="services-container">
        <div class="service-card">
          <div class="service-header">
            <i class="fas fa-gas-pump"></i>
            <h3>Gas Refilling</h3>
          </div>
          <div class="service-body">
            <ul>
              <li>Residential cylinder refilling</li>
             
              <li>Specialty gas solutions</li>
              <li>Cylinder exchange program</li>
              <li>Emergency refill services</li>
            </ul>
          </div>
        </div>
        
        <div class="service-card">
          <div class="service-header">
            <i class="fas fa-truck"></i>
            <h3>Delivery Services</h3>
          </div>
          <div class="service-body">
            <ul>
              <li>Same-day delivery guarantee</li>
              <li>Scheduled automatic refills</li>
              <li>Express emergency delivery</li>
            
              <li>Professional installation service</li>
            </ul>
          </div>
        </div>
        
        <div class="service-card">
          <div class="service-header">
            <i class="fas fa-tools"></i>
            <h3>Maintenance & Safety</h3>
          </div>
          <div class="service-body">
            <ul>
              <li>Free safety inspections</li>
              <li>Cylinder maintenance programs</li>
              <li>Valve replacement services</li>
              <li>Leak detection services</li>
              <li>Safety training workshops</li>
            </ul>
          </div>
        </div>
      </div>
    </section>

 
  <!-- Level System -->
        <section class="level-system">
            <div class="level-header">
                <h2>Referral System Levels</h2>
                <p class="level-subtitle">Progress through tiers to unlock greater rewards and bonuses</p>
            </div>
            
            <div class="level-body">
                <!-- Level Indicator -->
                <div class="level-indicator">
                    <div class="level-step active">
                        <div class="level-circle">1</div>
                        <div class="level-name">EXPLORER</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">2</div>
                        <div class="level-name">CONNECTOR</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">3</div>
                        <div class="level-name">CATALYST</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">4</div>
                        <div class="level-name">STRATEGIST</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">5</div>
                        <div class="level-name">VISIONARY</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">6</div>
                        <div class="level-name">EMPIRE BUILDER</div>
                    </div>
                </div>
                
                <!-- Referral Table -->
                <div class="referral-table-container">
                    <table class="referral-table">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th>Requirements</th>
                                <th>Bonuses & Rewards</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="level-column">EXPLORER</td>
                                <td>Sign Up + 1 L1 Referral</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">CONNECTOR</td>
                                <td>5 L1 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 6 per L2</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">CATALYST</td>
                                <td>10 L1 + 25 L2 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 8 per L2</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">STRATEGIST</td>
                                <td>20 L1 + 50 L2 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 10 per L2</span>
                                    <span class="bonus-highlight">Branded T-shirt</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">VISIONARY</td>
                                <td>50 L1 + 200 L2 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 10 per L2</span>
                                    <span class="bonus-highlight">KSh 1,000 Monthly Bonus</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">EMPIRE BUILDER</td>
                                <td>100+ L1 + 500+ L2 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 10 per L2</span>
                                    <span class="bonus-highlight">KSh 2,500 Monthly Bonus</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    <!-- CTA Section -->
    <section class="cta-container">
      <h2>Experience Safe & Fast Gas Delivery</h2>
      <p>Join thousands of satisfied customers who trust us for their gas needs</p>
      <a href="products.php" class="cta-button">Order Gas Now <i class="fas fa-arrow-right"></i></a>
    </section>
  </main>


  
  <!-- footer  ---------------->

<?php include 'include/footer.php'; ?>

  
</body>
</html>