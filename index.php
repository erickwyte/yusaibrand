<?php
session_start();
require_once 'db.php';

// Fetch slides from database
function getSlides($pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM slides 
        WHERE is_active = 1 
        ORDER BY sort_order ASC, created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

$slides = getSlides($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Basic Meta Tags -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="description" content="Yusai Brand Company - Premium eco-friendly products and sustainable solutions. Discover our natural refills, organic courses, and eco-conscious lifestyle products.">
  <meta name="keywords" content="eco-friendly, sustainable products, natural refills, organic courses, green living, Yusai Brand, environmentally conscious">
  <meta name="author" content="Yusai Brand Company">
  <meta name="robots" content="index, follow">

  <!-- Canonical URL -->
  <link rel="canonical" href="https://yusaibrand.co.ke/">

  <!-- Open Graph / Facebook -->
  <meta property="og:title" content="Yusai Brand Company - Sustainable Eco-Friendly Products">
  <meta property="og:description" content="Discover premium eco-friendly products and sustainable solutions for a greener lifestyle.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://yusaibrand.co.ke/">
  <meta property="og:image" content="https://yusaibrand.co.ke/images/social-preview.jpg">
  <meta property="og:image:alt" content="Yusai Brand Company - Premium Eco Products">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:site_name" content="Yusai Brand Company">
  <meta property="og:locale" content="en_US">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@YusaiBrand">
  <meta name="twitter:creator" content="@YusaiBrand">
  <meta name="twitter:title" content="Yusai Brand Company - Sustainable Eco-Friendly Products">
  <meta name="twitter:description" content="Discover premium eco-friendly products and sustainable solutions for a greener lifestyle.">
  <meta name="twitter:image" content="https://yusaibrand.co.ke/images/twitter-preview.jpg">
  <meta name="twitter:image:alt" content="Yusai Brand Company - Premium Eco Products">

  <!-- Favicons -->
  <link rel="icon" type="image/png" href="my-favicon/favicon-96x96.png" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="my-favicon/favicon.svg" />
  <link rel="shortcut icon" href="my-favicon/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="my-favicon/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-title" content="Yusai Brand" />
  <link rel="manifest" href="my-favicon/site.webmanifest" />

  <!-- Preconnect & Preload -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com">

  <!-- Fonts & Stylesheets -->
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-TT2mL+J13+7Afx+jNfTGeDBbnHTybkQ4lH2C4LPzqUowLceIhXpi1rU9U6hK5ZHz" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

  <title>Yusai Brand Company - Sustainable Eco-Friendly Products</title>
  
  <!-- Slideshow CSS -->
  <style>
    /* Slideshow Styles */
    .slideshow-section {
      padding: 40px 20px;
      margin: 40px 0;
      position: relative;
      overflow: hidden;
      background-color: #f5f5f5; /* Light grey background */
    }
    
    .slideshow-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.02);
      z-index: 1;
    }
    
    .slideshow-container {
      position: relative;
      max-width: 1200px;
      margin: 0 auto;
      overflow: hidden;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      z-index: 2;
      height: 600px; /* Increased height for better display */
    }
    
    .slideshow-wrapper {
      display: flex;
      transition: transform 0.5s ease;
      height: 100%;
      width: 100%;
    }
    
    .slide {
      min-width: 100%;
      position: relative;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    
    .slide-image-container {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }
    
    .slide-image {
      width: 100%;
      height: 100%;
      object-fit: contain; /* Changed from cover to contain */
      display: block;
      background-color: white; /* White background for images with transparency */
    }
    
    .slide-description {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.85), transparent);
      color: white;
      padding: 30px 25px 20px;
      text-align: center;
      z-index: 2;
    }
    
    .slide-description h3 {
      margin-bottom: 10px;
      font-size: 1.8rem;
      text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
      font-weight: 600;
    }
    
    .slide-description p {
      font-size: 1.1rem;
      max-width: 80%;
      margin: 0 auto;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
      line-height: 1.6;
    }
    
    .slide-navigation {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 100%;
      display: flex;
      justify-content: space-between;
      padding: 0 20px;
      z-index: 3;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .slideshow-container:hover .slide-navigation {
      opacity: 1;
    }
    
    .nav-arrow {
      background-color: rgba(0, 0, 0, 0.3);
      border: none;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      backdrop-filter: blur(5px);
    }
    
    .nav-arrow:hover {
      background-color: rgba(0, 0, 0, 0.5);
      transform: scale(1.1);
    }
    
    .nav-arrow i {
      color: white;
      font-size: 1.5rem;
    }
    
    .slide-dots {
      position: absolute;
      bottom: 20px;
      left: 0;
      right: 0;
      display: flex;
      justify-content: center;
      gap: 12px;
      z-index: 3;
    }
    
    .dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background-color: rgba(255, 255, 255, 0.5);
      cursor: pointer;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }
    
    .dot.active {
      background-color: white;
      transform: scale(1.2);
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
    }
    
    .dot:hover {
      background-color: rgba(255, 255, 255, 0.8);
    }
    
    .slideshow-header {
      text-align: center;
      margin-bottom: 30px;
      color: #333; /* Dark text for light background */
      z-index: 2;
      position: relative;
    }
    
    .slideshow-header h2 {
      font-size: 2.2rem;
      margin-bottom: 10px;
      color: #2c3e50;
    }
    
    .slideshow-header p {
      font-size: 1.1rem;
      color: #7f8c8d;
      max-width: 600px;
      margin: 0 auto;
    }
    
    .no-slides {
      text-align: center;
      padding: 60px 20px;
      background-color: white;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      max-width: 600px;
      margin: 0 auto;
    }
    
    .no-slides i {
      font-size: 4rem;
      color: #bdc3c7;
      margin-bottom: 20px;
    }
    
    .no-slides h3 {
      color: #7f8c8d;
      margin-bottom: 10px;
      font-size: 1.5rem;
    }
    
    .no-slides p {
      color: #95a5a6;
      margin-bottom: 20px;
    }
    
    /* Responsive Styles for Slideshow */
    @media (max-width: 992px) {
      .slideshow-container {
        height: 500px;
      }
      
      .slide-description h3 {
        font-size: 1.6rem;
      }
      
      .slide-description p {
        font-size: 1rem;
      }
    }
    
    @media (max-width: 768px) {
      .slideshow-section {
        padding: 30px 15px;
        margin: 30px 0;
      }
      
      .slideshow-container {
        max-width: 100%;
        border-radius: 10px;
        height: 400px;
      }
      
      .slide-description h3 {
        font-size: 1.4rem;
      }
      
      .slide-description p {
        font-size: 0.95rem;
        max-width: 90%;
      }
      
      .slideshow-header h2 {
        font-size: 1.8rem;
      }
      
      .slideshow-header p {
        font-size: 1rem;
      }
      
      .nav-arrow {
        width: 40px;
        height: 40px;
      }
    }
    
    @media (max-width: 576px) {
      .slideshow-container {
        height: 350px;
      }
      
      .slide-description {
        padding: 20px 15px 15px;
      }
      
      .slide-description h3 {
        font-size: 1.2rem;
      }
      
      .slideshow-header h2 {
        font-size: 1.6rem;
      }
      
      .no-slides {
        padding: 40px 15px;
      }
    }
    
    @media (max-width: 400px) {
      .slideshow-container {
        height: 300px;
      }
    }
    
    /* Hide scrollbar for slideshow */
    .slideshow-container {
      -ms-overflow-style: none;  /* IE and Edge */
      scrollbar-width: none;  /* Firefox */
    }
    
    .slideshow-container::-webkit-scrollbar {
      display: none; /* Chrome, Safari and Opera */
    }
  </style>
</head>

<body>
  <?php include 'include/header.php'; ?>
  
  <?php
  // Display popup message if exists
  if (isset($_SESSION['message'])) {
      $message = $_SESSION['message'];
      $messageType = $_SESSION['messageType'];

      $color = [
          'success' => '#4CAF50',
          'error'   => '#f44336',
          'warning' => '#ff9800'
      ][$messageType] ?? '#2196F3';

      echo "
      <div id='popup-message' style='
          position: fixed;
          top: -100px;
          left: 50%;
          transform: translateX(-50%);
          background: {$color};
          color: white;
          padding: 15px 30px;
          border-radius: 5px;
          font-size: 16px;
          box-shadow: 0 4px 8px rgba(0,0,0,0.2);
          z-index: 9999;
          opacity: 0;
          transition: all 0.5s ease;
      '>
          {$message}
      </div>
      <script>
          document.addEventListener('DOMContentLoaded', function () {
              let popup = document.getElementById('popup-message');
              popup.style.top = '20px';
              popup.style.opacity = '1';
              setTimeout(function () {
                  popup.style.top = '-100px';
                  popup.style.opacity = '0';
              }, 4000);
          });
      </script>
      ";

      unset($_SESSION['message']);
      unset($_SESSION['messageType']);
  }
  ?>

    <div class="empty">

    </div>

<section class="hero">
  <div class="hero-overlay">
    <div class="hero-text">
      <h2>Quick & Reliable Gas Delivery</h2>
      <p>Refill your gas cylinders from the comfort of your home. Invite friends and earn tokens. Safe, secure and fast delivery!</p>
      <div class="cta">
        <a href="products.php" class="order">Order Gas</a>
        <a href="referral.php" class="refer">Refer & Earn</a>
      </div>
    </div>
  </div>
</section>

<!-- Slideshow Section -->
<section class="slideshow-section">
  <div class="slideshow-header">
    <h2>Our Featured Products & Services</h2>
    <p>Discover our premium offerings through this visual showcase</p>
  </div>
  
  <?php if (count($slides) > 0): ?>
  
  <div class="slideshow-container" id="slideshow-container">
    <div class="slideshow-wrapper" id="slideshow-wrapper">
      <?php foreach ($slides as $index => $slide): ?>
      <div class="slide" data-index="<?php echo $index; ?>">
        <div class="slide-image-container">
          <img src="uploads/slides_photos/<?php echo htmlspecialchars($slide['image_filename']); ?>" 
               alt="<?php echo htmlspecialchars($slide['title']); ?>" 
               class="slide-image"
              >
        </div>
        <div class="slide-description">
          <h3><?php echo htmlspecialchars($slide['title']); ?></h3>
          <p><?php echo htmlspecialchars($slide['description']); ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    
    <!-- Navigation Arrows -->
    <div class="slide-navigation">
      <button class="nav-arrow prev-arrow">
        <i class="fas fa-chevron-left"></i>
      </button>
      <button class="nav-arrow next-arrow">
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
    
    <!-- Dots Indicator -->
    <div class="slide-dots" id="slide-dots">
      <?php foreach ($slides as $index => $slide): ?>
      <div class="dot <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></div>
      <?php endforeach; ?>
    </div>
  </div>
  
  <?php else: ?>
  
  <div class="no-slides">
    <i class="fas fa-images"></i>
    <h3>No Slides Available</h3>
    <p>Check back soon for featured products and services.</p>
  </div>
  
  <?php endif; ?>
</section>

  <!-- Features Section 
<section class="features-section">
    <div class="features-container">
        <h2 class="features-title">Why Yusai Gas?</h2>
        <div class="features-grid">
            <!-- Feature 1
            <div class="feature-card">
                <div class="feature-icon bg-blue">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3 class="feature-name">Fast Delivery</h3>
                <p class="feature-description">Get your gas cylinder delivered within 2 hours of ordering. We prioritize your cooking needs!</p>
                
            </div>

            <!-- Feature 2 
            <div class="feature-card">
                <div class="feature-icon bg-green">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <h3 class="feature-name">Earn with Referrals</h3>
                <p class="feature-description">Refer friends and earn bonuses on their orders. Get paid for two levels of referrals!</p>
                <a href="referral.php"  class="feature-link">See referral program →</a>
            </div>

            <!-- Feature 3 
            <div class="feature-card">
                <div class="feature-icon bg-orange">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="feature-name">Safety First</h3>
                <p class="feature-description">Our cylinders undergo rigorous safety checks. We prioritize your family's safety above all.</p>
                
            </div>
        </div>
    </div>
</section>
-->

<!-- Empty Cylinder WhatsApp Section -->
<section class="whatsapp-section" id="empty-cylinders">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Have Empty Gas Cylinders?</h2>
      <p class="section-subtitle">Contact us via WhatsApp for cylinder pickup or exchange</p>
    </div>

    <div class="whatsapp-content">
      <div class="whatsapp-info">
        <div class="whatsapp-icon">
          <i class="fab fa-whatsapp"></i>
        </div>
        <div class="whatsapp-details">
          <h3>Contact Admin on WhatsApp</h3>
          <p>If you have empty gas cylinders that need to be picked up or exchanged, reach out to our admin directly via WhatsApp.</p>
          <div class="whatsapp-benefits">
            <div class="benefit-item">
              <i class="fas fa-check-circle"></i>
              <span>Quick response within minutes</span>
            </div>
            <div class="benefit-item">
              <i class="fas fa-check-circle"></i>
              <span>Schedule convenient pickup time</span>
            </div>
            <div class="benefit-item">
              <i class="fas fa-check-circle"></i>
              <span>Get cylinder exchange options</span>
            </div>
            <div class="benefit-item">
              <i class="fas fa-check-circle"></i>
              <span>Direct communication with admin</span>
            </div>
          </div>
        </div>
      </div>
      
      <div class="whatsapp-action">
        <a href="https://wa.me/254719122571" class="whatsapp-btn" target="_blank">
          <i class="fab fa-whatsapp"></i>
          Chat on WhatsApp
        </a>
        <p class="whatsapp-note">Click the button above to start a conversation with our admin about your empty cylinders</p>
      </div>
    </div>
  </div>
</section>




<!-- Newsletter Section -->
<section class="newsletter">
  <div class="newsletter-container">
    <h2 class="newsletter-title">Stay Updated!</h2>
    <p class="newsletter-text">Subscribe to get exclusive offers, product updates, and latest news delivered to your inbox.</p>
   <form class="newsletter-form" action="email_subscribers.php" method="POST">
  <input type="email" name="email" placeholder="Enter your email" required>
  <button type="submit">Subscribe</button>
</form>

  </div>
</section>



  <!-- footer  ---------------->

<?php include 'include/footer.php'; ?>

<!-- Slideshow JavaScript -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const slideshowWrapper = document.getElementById('slideshow-wrapper');
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const totalSlides = slides.length;
    let currentSlide = 0;
    let autoSlideInterval;
    
    // Initialize slideshow
    function initSlideshow() {
      if (totalSlides === 0) return;
      
      updateSlidePosition();
      setupEventListeners();
      startAutoSlide();
    }
    
    // Update slide position
    function updateSlidePosition() {
      slideshowWrapper.style.transform = `translateX(-${currentSlide * 100}%)`;
      
      // Update active dot
      dots.forEach(dot => {
        dot.classList.toggle('active', parseInt(dot.dataset.index) === currentSlide);
      });
    }
    
    // Go to specific slide
    function goToSlide(index) {
      currentSlide = index;
      updateSlidePosition();
      resetAutoSlide();
    }
    
    // Next slide
    function nextSlide() {
      currentSlide = (currentSlide + 1) % totalSlides;
      updateSlidePosition();
      resetAutoSlide();
    }
    
    // Previous slide
    function prevSlide() {
      currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
      updateSlidePosition();
      resetAutoSlide();
    }
    
    // Auto slide
    function startAutoSlide() {
      clearInterval(autoSlideInterval);
      autoSlideInterval = setInterval(nextSlide, 5000);
    }
    
    // Reset auto slide timer
    function resetAutoSlide() {
      if (!slideshowContainer.matches(':hover')) {
        startAutoSlide();
      }
    }
    
    // Stop auto slide
    function stopAutoSlide() {
      clearInterval(autoSlideInterval);
    }
    
    // Setup event listeners
    function setupEventListeners() {
      const slideshowContainer = document.getElementById('slideshow-container');
      const prevArrow = document.querySelector('.prev-arrow');
      const nextArrow = document.querySelector('.next-arrow');
      
      // Navigation arrows
      if (prevArrow) {
        prevArrow.addEventListener('click', (e) => {
          e.stopPropagation();
          prevSlide();
        });
      }
      
      if (nextArrow) {
        nextArrow.addEventListener('click', (e) => {
          e.stopPropagation();
          nextSlide();
        });
      }
      
      // Dot navigation
      dots.forEach(dot => {
        dot.addEventListener('click', (e) => {
          const index = parseInt(e.target.dataset.index);
          goToSlide(index);
        });
      });
      
      // Keyboard navigation
      document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
          prevSlide();
        } else if (e.key === 'ArrowRight') {
          nextSlide();
        }
      });
      
      // Touch/swipe for mobile
      let touchStartX = 0;
      let touchEndX = 0;
      
      slideshowWrapper.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
        stopAutoSlide();
      });
      
      slideshowWrapper.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
      });
      
      function handleSwipe() {
        const swipeThreshold = 50;
        
        if (touchEndX < touchStartX - swipeThreshold) {
          nextSlide();
        }
        
        if (touchEndX > touchStartX + swipeThreshold) {
          prevSlide();
        }
      }
      
      // Mouse wheel scrolling
      slideshowWrapper.addEventListener('wheel', (e) => {
        e.preventDefault();
        if (e.deltaY > 0) {
          nextSlide();
        } else {
          prevSlide();
        }
      });
      
      // Pause auto slide on hover
      slideshowContainer.addEventListener('mouseenter', stopAutoSlide);
      slideshowContainer.addEventListener('mouseleave', startAutoSlide);
      
      // Handle window resize
      let resizeTimeout;
      window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
          // Force redraw for smooth transition
          slideshowWrapper.style.transition = 'none';
          updateSlidePosition();
          setTimeout(() => {
            slideshowWrapper.style.transition = 'transform 0.5s ease';
          }, 50);
        }, 150);
      });
    }
    
    // Initialize everything
    if (totalSlides > 0) {
      initSlideshow();
    }
  });
</script>

</body>
</html>