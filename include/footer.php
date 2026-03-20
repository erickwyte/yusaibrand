<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<link rel="stylesheet" href="../css/footer.css">

<footer>
  <div class="footer-container">
    <!-- Brand and Description -->
    <div>
      <div class="footer-brand">
        <div class="footer-brand-icon"><i class="fas fa-fire"></i></div>
        <span class="footer-brand-name">YUSAI<span class="text-green" style="color: #4caf50;">BRAND</span></span>
      </div>
      <p class="footer-text">
        Reliable gas delivery with an innovative referral program — earn while meeting your cooking needs.
      </p>
      <div class="footer-socials">
  <a href="https://www.facebook.com/share/177k5ZNzya/" target="_blank" rel="noopener">
    <i class="fab fa-facebook-f"></i>
  </a>
 <a href="https://youtube.com/@yusufsaidi7996" target="_blank" rel="noopener" aria-label="Visit our YouTube channel">
  <i class="fab fa-youtube"></i>
</a>

  <a href="https://www.instagram.com/yusuf787209" target="_blank" rel="noopener">
    <i class="fab fa-instagram"></i>
  </a>
  <a href="https://whatsapp.com/channel/0029Vb6gZzAIXnltIENyVE41" target="_blank" rel="noopener">
  <i class="fab fa-whatsapp"></i>
</a>

  
</div>

    </div>

    <!-- Quick Links -->
    <div class="footer-column">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="products.php">Products</a></li>
        <li><a href="referral.php">Referral Program</a></li>
        <li><a href="contact_us.php">Contact Us</a></li>
        <li><a href="about_us.php">About Us</a></li>
      </ul>
    </div>

    <!-- Product Links -->
    <div class="footer-column">
      <h4>Explore</h4>
      <ul>
        <li><a href="products.php">Gas Cylinders</a></li>
        <li><a href="send_sell_request.php">Sell With Us</a></li>
        <li><a href="Emergency_delivery.php">Emergency Delivery</a></li>
        <li><a href="terms&conditions.php">terms&conditions</a></li>
        <li><a href="privacy_policy.php">privacy policy</a></li>
        <li><a href="FAQ.php">FAQ</a></li>
      </ul>
    </div>

    <!-- Contact Info -->
    <div class="footer-column">
      <h4>Contact Info</h4>
      <ul>
        <li><i class="fas fa-map-marker-alt"></i> Nchiru, MERU County</li>
        <li><i class="fas fa-phone-alt"></i> +254 719 122 571</li>
        <li><i class="fab fa-whatsapp"></i> +254 719 122 571</li>
        <li><i class="fas fa-envelope"></i> saidiyusuf203@gmail.com</li>
      </ul>
    </div>
  </div>

  <div class="footer-bottom">
    &copy; <?php echo date('Y'); ?> YUSAI BRAND COMPANY. All rights reserved. | Made for every home in Kenya
  </div>
</footer>
