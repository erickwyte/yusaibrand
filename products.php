<?php
session_start();
require_once 'db.php';

// Start output buffering for better control
ob_start();

// Set caching headers
header("Cache-Control: public, max-age=3600"); // 1 hour cache
header("Pragma: cache");

try {
    // Use lighter query - only fetch needed columns
    $stmt = $pdo->prepare("SELECT id, name, description, price, image, type FROM products ORDER BY type, id");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process categories from PHP instead of array_column (faster)
    $categories = [];
    foreach ($products as $product) {
        $categories[$product['type']] = true;
    }
    $categories = array_keys($categories);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error = "Unable to load products. Please try again later.";
}

$error_message = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>YUSAI Products - Contact for Prices</title>
  
  <!-- Preload critical resources -->
  <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" crossorigin="anonymous">
  <link rel="preload" href="css/products.css" as="style">
  <link rel="preconnect" href="https://cdnjs.cloudflare.com">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  
  <!-- Load stylesheets with media queries for non-blocking -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
        integrity="sha384-TT2mL+J13+7Afx+jNfTGeDBbnHTybkQ4lH2C4LPzqUowLceIhXpi1rU9U6hK5ZHz" 
        crossorigin="anonymous"
        media="print" onload="this.media='all'">
  
  <!-- Inline critical CSS -->
  <style>
    /* Critical CSS - Loads immediately */
    :root {
      --primary-color: #2c7be5;
      --secondary-color: #00d97e;
      --dark-color: #12263f;
      --whatsapp-green: #25D366;
      --border-radius: 8px;
    }
    
    body {
      margin: 0;
      padding: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: #f5f7fa;
    }
    
    /* Header placeholder to prevent CLS */
    header {
      min-height: 70px;
      background: white;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .shop-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 15px;
    }
    
    /* Critical product card structure */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .product-card {
      background: white;
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    /* Loading skeleton */
    .skeleton {
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
      background-size: 200% 100%;
      animation: loading 1.5s infinite;
    }
    
    @keyframes loading {
      0% { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }
  </style>
  
  <!-- Deferred styles -->
  <link rel="stylesheet" href="css/products.css" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="css/products.css"></noscript>
    
  <!-- Favicons -->
  <link rel="icon" type="image/png" href="my-favicon/favicon-96x96.png" sizes="96x96">
  <link rel="icon" type="image/svg+xml" href="my-favicon/favicon.svg">
  <link rel="shortcut icon" href="my-favicon/favicon.ico">
  
  <!-- Preload main image if known -->
  <?php if (!empty($products[0]['image'])): ?>
    <link rel="preload" href="uploads/products/<?= htmlspecialchars($products[0]['image']) ?>" as="image">
  <?php endif; ?>
</head>

<body>
  <!-- Header with skeleton -->
  <div id="header-placeholder" class="skeleton" style="height: 70px;"></div>
  
  <!-- Content loaded by JS -->
  <main id="main-content" aria-busy="true">
    <div class="shop-container">
      <div class="section-title skeleton" style="height: 40px; width: 200px; margin: 20px auto;"></div>
      
      <div class="whatsapp-banner skeleton" style="height: 60px; margin-bottom: 30px;"></div>
      
      <!-- Category tabs skeleton -->
      <div class="category-tabs">
        <div class="skeleton" style="height: 40px; width: 100px; border-radius: 20px;"></div>
        <div class="skeleton" style="height: 40px; width: 100px; border-radius: 20px;"></div>
        <div class="skeleton" style="height: 40px; width: 100px; border-radius: 20px;"></div>
      </div>
      
      <!-- Product grid skeleton -->
      <div class="product-grid">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <div class="product-card">
            <div class="skeleton" style="height: 200px;"></div>
            <div class="product-info" style="padding: 20px;">
              <div class="skeleton" style="height: 24px; margin-bottom: 10px;"></div>
              <div class="skeleton" style="height: 60px; margin-bottom: 15px;"></div>
              <div class="skeleton" style="height: 40px; border-radius: var(--border-radius);"></div>
            </div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </main>

  <!-- Hidden template for dynamic loading -->
  <template id="product-template">
    <div class="product-card">
      <div class="product-image-container">
        <img loading="lazy" decoding="async" width="280" height="200">
        <div class="product-badge">
          <i class="fas fa-info-circle"></i> Contact for Price
        </div>
      </div>
      <div class="product-info">
        <h3></h3>
        <p class="product-description"></p>
        <div class="price-estimate">
          <div class="estimated-price">
            <i class="fas fa-tags"></i>
            <span class="price-label">Estimated Price:</span>
            <span class="price-value"></span>
          </div>
          <div class="price-note">
            <i class="fas fa-exclamation-circle"></i>
            <span>Final price may vary. Contact us for exact pricing.</span>
          </div>
        </div>
        <div class="contact-options">
          <a href="#" class="btn-whatsapp" target="_blank">
            <i class="fab fa-whatsapp"></i> WhatsApp for Price & Order
          </a>
          <button class="btn-inquiry">
            <i class="fas fa-question-circle"></i> Quick Inquiry
          </button>
        </div>
      </div>
    </div>
  </template>

  <!-- Inline scripts for faster initial load -->
  <script>
    // Load page content immediately
    document.addEventListener('DOMContentLoaded', function() {
      // Remove skeletons and load real content
      const mainContent = document.getElementById('main-content');
      mainContent.innerHTML = '';
      
      // Create container for dynamic content
      const container = document.createElement('div');
      container.className = 'shop-container';
      container.innerHTML = `
        <div class="section-title">Our Products</div>
        <div class="whatsapp-banner">
          <i class="fab fa-whatsapp"></i>
          <p>Contact us on WhatsApp for pricing and ordering: <a href="https://wa.me/254719122571" target="_blank">+254 719 122 571</a></p>
        </div>
        
        <?php if ($error_message): ?>
          <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
        <?php elseif (isset($error)): ?>
          <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php else: ?>
          <!-- Category tabs will be loaded here -->
          <div id="category-tabs-container"></div>
          <!-- Product grid will be loaded here -->
          <div id="products-container"></div>
        <?php endif; ?>
      `;
      
      mainContent.appendChild(container);
      mainContent.setAttribute('aria-busy', 'false');
      
      // Load header asynchronously
      loadHeader();
      
      // Load products with priority
      setTimeout(loadProducts, 0);
    });
    
    // Load header via AJAX
    async function loadHeader() {
      try {
        const response = await fetch('include/header.php');
        const html = await response.text();
        document.getElementById('header-placeholder').outerHTML = html;
        
        // Initialize cart count after header loads
        updateCartCount();
      } catch (error) {
        console.error('Failed to load header:', error);
      }
    }
    
    // Load products asynchronously
    async function loadProducts() {
      const tabsContainer = document.getElementById('category-tabs-container');
      const productsContainer = document.getElementById('products-container');
      
      <?php if (!empty($categories)): ?>
        // Generate category tabs
        let tabsHTML = '<div class="category-tabs" role="tablist">';
        <?php foreach ($categories as $index => $cat): ?>
          tabsHTML += `
            <button class="tab-btn ${<?= $index ?> === 0 ? 'active' : ''}"
                    data-category="<?= htmlspecialchars($cat) ?>"
                    role="tab">
              <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $cat))) ?>
            </button>
          `;
        <?php endforeach; ?>
        tabsHTML += '</div>';
        tabsContainer.innerHTML = tabsHTML;
        
        // Generate product grid
        let productsHTML = '';
        <?php foreach ($categories as $index => $cat): ?>
          productsHTML += `
            <div class="product-grid ${<?= $index ?> === 0 ? 'active' : ''}" 
                 id="<?= htmlspecialchars($cat) ?>" 
                 role="tabpanel">
          `;
          
          <?php 
          // Group products by category for efficiency
          $categoryProducts = array_filter($products, function($p) use ($cat) {
            return $p['type'] === $cat;
          });
          ?>
          
          <?php foreach ($categoryProducts as $product): ?>
            <?php
            $imagePath = (!empty($product['image']) && file_exists('uploads/products/' . $product['image'])) 
              ? 'uploads/products/' . $product['image'] 
              : 'images/default.jpg';
            $whatsappMessage = urlencode("Hello! I'm interested in {$product['name']} - {$product['description']}");
            ?>
            
            productsHTML += `
              <div class="product-card">
                <div class="product-image-container">
                  <img src="<?= htmlspecialchars($imagePath) ?>"
                       alt="<?= htmlspecialchars($product['name']) ?>"
                       loading="lazy"
                       decoding="async"
                       width="280"
                       height="200"
                       onerror="this.src='images/default.jpg'">
                  <div class="product-badge">
                    <i class="fas fa-info-circle"></i> Contact for Price
                  </div>
                </div>
                <div class="product-info">
                  <h3><?= htmlspecialchars($product['name']) ?></h3>
                  <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                  <div class="price-estimate">
                    <div class="estimated-price">
                      <i class="fas fa-tags"></i>
                      <span class="price-label">Estimated Price:</span>
                      <span class="price-value">KSh <?= number_format($product['price'], 2) ?></span>
                    </div>
                    <div class="price-note">
                      <i class="fas fa-exclamation-circle"></i>
                      <span>Final price may vary. Contact us for exact pricing.</span>
                    </div>
                  </div>
                  <div class="contact-options">
                    <a href="https://wa.me/254719122571?text=<?= $whatsappMessage ?>"
                       class="btn-whatsapp" target="_blank">
                      <i class="fab fa-whatsapp"></i> WhatsApp for Price & Order
                    </a>
                    <button class="btn-inquiry"
                            data-product-name="<?= htmlspecialchars($product['name']) ?>"
                            data-product-desc="<?= htmlspecialchars($product['description']) ?>">
                      <i class="fas fa-question-circle"></i> Quick Inquiry
                    </button>
                  </div>
                </div>
              </div>
            `;
          <?php endforeach; ?>
          
          productsHTML += '</div>';
        <?php endforeach; ?>
        
        productsContainer.innerHTML = productsHTML;
        
        // Initialize tab functionality
        initTabs();
        initInquiryModal();
        
        // Lazy load images
        initLazyLoading();
        
      <?php endif; ?>
    }
    
    // Initialize tabs
    function initTabs() {
      const tabButtons = document.querySelectorAll('.tab-btn');
      const tabGrids = document.querySelectorAll('.product-grid');
      
      tabButtons.forEach(button => {
        button.addEventListener('click', () => {
          tabButtons.forEach(btn => btn.classList.remove('active'));
          tabGrids.forEach(grid => grid.classList.remove('active'));
          
          button.classList.add('active');
          const category = button.getAttribute('data-category');
          document.getElementById(category).classList.add('active');
        });
      });
    }
    
    // Initialize lazy loading for images
    function initLazyLoading() {
      if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              const img = entry.target;
              img.src = img.dataset.src;
              img.classList.remove('lazy');
              imageObserver.unobserve(img);
            }
          });
        });
        
        document.querySelectorAll('img[loading="lazy"]').forEach(img => {
          img.dataset.src = img.src;
          img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjgwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PC9zdmc+';
          imageObserver.observe(img);
        });
      }
    }
    
    // Initialize inquiry modal
    function initInquiryModal() {
      const inquiryButtons = document.querySelectorAll('.btn-inquiry');
      
      inquiryButtons.forEach(button => {
        button.addEventListener('click', () => {
          const productName = button.getAttribute('data-product-name');
          const productDesc = button.getAttribute('data-product-desc');
          
          // Create modal if it doesn't exist
          let modal = document.getElementById('inquiryModal');
          if (!modal) {
            modal = document.createElement('div');
            modal.id = 'inquiryModal';
            modal.className = 'inquiry-modal';
            modal.innerHTML = `
              <div class="modal-content">
                <div class="modal-header">
                  <h3>Product Inquiry</h3>
                  <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                  <p id="modalProductName"></p>
                  <p id="modalProductDesc"></p>
                  <div class="inquiry-actions">
                    <a href="https://wa.me/254719122571" class="btn-whatsapp-modal" target="_blank">
                      <i class="fab fa-whatsapp"></i> Contact on WhatsApp
                    </a>
                    <button class="btn-cancel">Cancel</button>
                  </div>
                </div>
              </div>
            `;
            document.body.appendChild(modal);
            
            // Add modal close handlers
            modal.querySelector('.close-modal').addEventListener('click', () => {
              modal.classList.remove('active');
            });
            
            modal.querySelector('.btn-cancel').addEventListener('click', () => {
              modal.classList.remove('active');
            });
            
            modal.addEventListener('click', (e) => {
              if (e.target === modal) {
                modal.classList.remove('active');
              }
            });
          }
          
          // Set modal content
          modal.querySelector('#modalProductName').textContent = `Product: ${productName}`;
          modal.querySelector('#modalProductDesc').textContent = `Description: ${productDesc}`;
          
          // Show modal
          modal.classList.add('active');
        });
      });
    }
    
    // Cart functionality
    function getCart() {
      return JSON.parse(localStorage.getItem('cart')) || [];
    }
    
    function updateCartCount() {
      const cart = getCart();
      const countEl = document.querySelector('.cart-count');
      if (countEl) {
        const totalItems = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
        countEl.textContent = totalItems;
      }
    }
    
    // Load external CSS if print media didn't trigger onload
    window.addEventListener('load', function() {
      const links = document.querySelectorAll('link[media="print"]');
      links.forEach(link => {
        if (link.media === 'print') {
          link.media = 'all';
        }
      });
    });
  </script>
  
  <!-- Deferred styles and scripts -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600&display=swap" media="print" onload="this.media='all'">
  
  <noscript>
    <style>
      /* Fallback for users without JavaScript */
      .skeleton { display: none !important; }
      #main-content { display: block !important; }
    </style>
  </noscript>
</body>
</html>
<?php
// End output buffering and send content
ob_end_flush();
?>
<style>
    :root {
      --primary-color: #2c7be5;
      --secondary-color: #00d97e;
      --dark-color: #12263f;
      --light-color: #f9fbfd;
      --gray-color: #95aac9;
      --border-radius: 8px;
      --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      --transition: all 0.3s ease;
      --whatsapp-green: #25D366;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: var(--dark-color);
      background-color: #f5f7fa;
    }

    .error-message {
      color: #e44d26;
      text-align: center;
      margin: 20px 0;
      font-weight: bold;
      padding: 15px;
      background-color: #ffeeee;
      border-radius: var(--border-radius);
      max-width: 800px;
      margin-left: auto;
      margin-right: auto;
    }

    .shop-container {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 1.5rem;
    }

    .section-title {
      font-size: 2.5rem;
      color: var(--primary-color);
      margin-bottom: 1.5rem;
      text-align: center;
      position: relative;
      padding-bottom: 1rem;
    }

    .section-title::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 4px;
      background: var(--secondary-color);
      border-radius: 2px;
    }

    /* WhatsApp Banner */
    .whatsapp-banner {
      background: linear-gradient(135deg, #25D366, #128C7E);
      color: white;
      padding: 1.2rem 2rem;
      border-radius: var(--border-radius);
      margin-bottom: 2.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      box-shadow: 0 5px 15px rgba(37, 211, 102, 0.2);
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% { box-shadow: 0 5px 15px rgba(37, 211, 102, 0.2); }
      50% { box-shadow: 0 5px 20px rgba(37, 211, 102, 0.4); }
      100% { box-shadow: 0 5px 15px rgba(37, 211, 102, 0.2); }
    }

    .whatsapp-banner i {
      font-size: 1.8rem;
    }

    .whatsapp-banner a {
      color: white;
      font-weight: bold;
      text-decoration: none;
      border-bottom: 2px solid white;
      padding-bottom: 2px;
      transition: all 0.3s ease;
    }

    .whatsapp-banner a:hover {
      color: #e1ffe9;
      border-bottom-color: #e1ffe9;
    }

    /* Category Tabs */
    .category-tabs {
      display: flex;
      gap: 5px;
      padding: 12px 20px;
      margin-bottom: 20px;
      background-color: #ffffff;
      flex-wrap: nowrap;
      overflow-x: auto;
      scrollbar-width: none;
      -ms-overflow-style: none;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      border-radius: var(--border-radius);
    }

    .category-tabs::-webkit-scrollbar {
      display: none;
    }

    .tab-btn {
      padding: 10px 20px;
      border: none;
      background-color: #e8ecef;
      color: #2c3e50;
      border-radius: 12px;
      cursor: pointer;
      white-space: nowrap;
      flex-shrink: 0;
      transition: var(--transition);
      font-weight: 500;
      font-size: 14px;
    }

    .tab-btn:hover {
      background-color: #d5dce3;
      transform: translateY(-1px);
    }

    .tab-btn.active {
      background-color: var(--dark-color);
      color: #ffffff;
      box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
    }

    /* Product Grid */
    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 2rem;
      margin-bottom: 3rem;
      display: none;
    }

    .product-grid.active {
      display: grid;
      animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Product Card */
    .product-card {
      background: white;
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: var(--box-shadow);
      transition: var(--transition);
      border: 1px solid #e2e8f0;
    }

    .product-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 30px rgba(0,0,0,0.15);
      border-color: var(--primary-color);
    }

    .product-image-container {
      position: relative;
      height: 200px;
      background: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .product-card img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }

    .product-badge {
      position: absolute;
      top: 15px;
      right: 15px;
      background: rgba(255, 193, 7, 0.95);
      color: #856404;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 5px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .product-info {
      padding: 1.5rem;
    }

    .product-info h3 {
      font-size: 1.3rem;
      margin-bottom: 0.75rem;
      color: var(--dark-color);
      font-weight: 600;
    }

    .product-description {
      color: #4a5568;
      margin-bottom: 1.2rem;
      font-size: 0.95rem;
      line-height: 1.5;
      min-height: 70px;
    }

    /* Price Estimate */
    .price-estimate {
      background: #f8f9fa;
      border-radius: var(--border-radius);
      padding: 1rem;
      margin-bottom: 1.5rem;
      border-left: 4px solid #ffc107;
    }

    .estimated-price {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 0.75rem;
    }

    .estimated-price i {
      color: #ffc107;
      font-size: 1.2rem;
    }

    .price-label {
      font-weight: 500;
      color: #495057;
    }

    .price-value {
      font-weight: 700;
      color: var(--primary-color);
      font-size: 1.2rem;
    }

    .price-note {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      font-size: 0.85rem;
      color: #6c757d;
    }

    .price-note i {
      color: #6c757d;
      font-size: 0.9rem;
      margin-top: 2px;
    }

    /* Contact Options */
    .contact-options {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .btn-whatsapp {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 0.85rem;
      background: linear-gradient(135deg, #25D366, #128C7E);
      color: white;
      border: none;
      border-radius: var(--border-radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      text-align: center;
    }

    .btn-whatsapp:hover {
      background: linear-gradient(135deg, #128C7E, #0d6e5c);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
      color: white;
    }

    .btn-inquiry {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 0.75rem;
      background: #f8f9fa;
      color: var(--dark-color);
      border: 2px solid #dee2e6;
      border-radius: var(--border-radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-inquiry:hover {
      background: #e9ecef;
      border-color: var(--primary-color);
      color: var(--primary-color);
    }

    /* Inquiry Modal */
    .inquiry-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .inquiry-modal.active {
      display: flex;
      animation: fadeIn 0.3s ease;
    }

    .modal-content {
      background: white;
      border-radius: var(--border-radius);
      width: 90%;
      max-width: 500px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.5rem;
      border-bottom: 1px solid #dee2e6;
      background: var(--dark-color);
      color: white;
      border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .modal-header h3 {
      margin: 0;
      color: white;
    }

    .close-modal {
      background: none;
      border: none;
      color: white;
      font-size: 1.8rem;
      cursor: pointer;
      line-height: 1;
      padding: 0;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: var(--transition);
    }

    .close-modal:hover {
      color: #ff6b6b;
      transform: rotate(90deg);
    }

    .modal-body {
      padding: 2rem;
    }

    .modal-body p {
      margin-bottom: 1rem;
      color: #495057;
    }

    .inquiry-actions {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 1.5rem;
    }

    .btn-whatsapp-modal {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 0.85rem;
      background: linear-gradient(135deg, #25D366, #128C7E);
      color: white;
      border: none;
      border-radius: var(--border-radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      text-align: center;
    }

    .btn-whatsapp-modal:hover {
      background: linear-gradient(135deg, #128C7E, #0d6e5c);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
    }

    .btn-cancel {
      padding: 0.75rem;
      background: #6c757d;
      color: white;
      border: none;
      border-radius: var(--border-radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-cancel:hover {
      background: #545b62;
    }

    footer {
      text-align: center;
      padding: 1.5rem;
      margin-top: 3rem;
      border-top: 1px solid #ccc;
      background: var(--dark-color);
      color: white;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      }
      
      .section-title {
        font-size: 2rem;
      }
      
      .whatsapp-banner {
        flex-direction: column;
        text-align: center;
        padding: 1rem;
        gap: 0.5rem;
      }
      
      .contact-options {
        flex-direction: column;
      }
    }

    @media (max-width: 480px) {
      .shop-container {
        margin: 1rem auto;
        padding: 0 0.75rem;
      }
      
      .product-card img {
        height: 180px;
      }
      
      .category-tabs {
        padding: 10px 15px;
      }
      
      .tab-btn {
        padding: 8px 16px;
        font-size: 13px;
      }
      
      .modal-content {
        width: 95%;
        margin: 1rem;
      }
    }

    /* Notification styles */
    .notification {
      position: fixed;
      bottom: 70px;
      right: 20px;
      padding: 15px 25px;
      border-radius: var(--border-radius);
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
      transform: translateY(100px);
      opacity: 0;
      transition: all 0.3s ease;
      z-index: 1000;
    }

    .notification.success {
      background: var(--primary-color);
      color: white;
    }

    .notification.show {
      transform: translateY(0);
      opacity: 1;
    }

    .notification-content {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .notification-content i {
      font-size: 1.5rem;
    }
</style>