<?php
session_start();
require_once 'db.php';
$productId = $_GET['id'] ?? 0;
try {
    // Get product details
    $stmt = $pdo->prepare("
        SELECT p.*,
               (SELECT COUNT(*) FROM product_images WHERE product_id = p.id) as image_count
        FROM black_market_products p
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header('Location: black-market.php?error=Product not found');
        exit;
    }

    // Get product images
    $imgStmt = $pdo->prepare("
        SELECT * FROM product_images
        WHERE product_id = ?
        ORDER BY is_primary DESC, display_order
    ");
    $imgStmt->execute([$productId]);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get product attributes
    $attrStmt = $pdo->prepare("
        SELECT * FROM product_attributes
        WHERE product_id = ?
        ORDER BY display_order
    ");
    $attrStmt->execute([$productId]);
    $attributes = $attrStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get related products
    $relatedStmt = $pdo->prepare("
        SELECT p.*,
               (SELECT image_url FROM product_images
                WHERE product_id = p.id AND is_primary = 1
                LIMIT 1) as primary_image
        FROM black_market_products p
        WHERE p.category = ?
          AND p.id != ?
          AND p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $relatedStmt->execute([$product['category'], $productId]);
    $relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Update view count
    $viewStmt = $pdo->prepare("UPDATE black_market_products SET views = views + 1 WHERE id = ?");
    $viewStmt->execute([$productId]);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    header('Location: black-market.php?error=Unable to load product');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($product['name']) ?> - Black Market</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <style>
        :root {
            --light-bg: #f5f7fa;
            --card-bg: #ffffff;
            --darker-bg: #eef2f7;
            --accent: #ff6b00;
            --accent-hover: #ff8533;
            --success: #28a745;
            --danger: #dc3545;
            --text-primary: #2d3748;
            --text-muted: #718096;
            --border: #e2e8f0;
            --whatsapp: #25D366;
            --telegram: #0088cc;
            --shadow: rgba(0, 0, 0, 0.08);
            --related-bg: #ffffff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.7;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
            margin-bottom: 40px;
        }

        .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.3s;
            padding: 10px 20px;
            background: var(--card-bg);
            border-radius: 10px;
            border: 1px solid var(--border);
        }

        .back-link:hover { 
            color: var(--accent); 
            border-color: var(--accent);
            transform: translateY(-2px);
        }

        .stats {
            display: flex;
            gap: 25px;
            font-size: 0.95rem;
            color: var(--text-muted);
        }

        .stat { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            padding: 8px 16px;
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        @media (max-width: 992px) {
            .product-grid { grid-template-columns: 1fr; gap: 30px; }
        }

        /* Swiper Gallery */
        .gallery-wrapper {
            background: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px var(--shadow);
            position: relative;
            border: 1px solid var(--border);
        }

        .swiper {
            width: 100%;
            height: 500px;
        }

        .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: var(--darker-bg);
        }

        .swiper-thumbs {
            margin-top: 15px;
            height: 90px;
            padding: 0 20px;
        }

        .swiper-thumbs .swiper-slide {
            opacity: 0.5;
            border: 2px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--darker-bg);
        }

        .swiper-thumbs .swiper-slide-thumb-active,
        .swiper-thumbs .swiper-slide:hover {
            opacity: 1;
            border-color: var(--accent);
            transform: translateY(-2px);
        }

        .swiper-thumbs img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Product Info */
        .product-info {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .category-badge {
            align-self: flex-start;
            background: linear-gradient(135deg, var(--accent), #ff8c42);
            color: white;
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(255, 107, 0, 0.2);
        }

        .title {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.2;
            color: #1a202c;
        }

        .price-box {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 16px;
            border: 2px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .price-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--success));
        }

        .price {
            font-size: 2.8rem;
            font-weight: 900;
            color: var(--success);
            margin: 10px 0;
        }

        .price-currency {
            font-size: 1.8rem;
            color: var(--text-muted);
        }

        .price-note {
            color: var(--text-muted);
            font-style: italic;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }

        .description {
            font-size: 1.1rem;
            color: var(--text-primary);
            line-height: 1.9;
            background: var(--card-bg);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .description h3 {
            color: var(--accent);
            margin-bottom: 15px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .attributes {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .attributes h3 {
            margin-bottom: 20px;
            font-size: 1.4rem;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .attr-row {
            display: flex;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            transition: background 0.3s;
        }

        .attr-row:hover {
            background: var(--darker-bg);
            border-radius: 8px;
            padding-left: 10px;
            padding-right: 10px;
        }

        .attr-row:last-child { border-bottom: none; }

        .attr-key { 
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .attr-key i {
            color: var(--accent);
            width: 20px;
        }
        
        .attr-value { 
            font-weight: 600; 
            text-align: right;
            color: var(--text-primary);
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            padding: 18px;
            border-radius: 12px;
            font-size: 1.15rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.4s ease;
            text-decoration: none;
            text-align: center;
            border: none;
            cursor: pointer;
        }

        .btn-whatsapp {
            background: var(--whatsapp);
            color: white;
        }

        .btn-whatsapp:hover {
            background: #1ebf58;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(37, 211, 102, 0.3);
        }

        .btn-telegram {
            background: var(--telegram);
            color: white;
        }

        .btn-telegram:hover {
            background: #007ab8;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 136, 204, 0.3);
        }

        .btn-phone {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-phone:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-3px);
            background: rgba(255, 107, 0, 0.05);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #ff3742;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 71, 87, 0.3);
        }

        /* Contact Options */
        .contact-options {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        .contact-options h3 {
            margin-bottom: 20px;
            font-size: 1.4rem;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Seller Info */
        .seller-info {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .seller-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--success));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
        }

        .seller-details h4 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .seller-details p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Related Products */
        .related-section {
            margin-top: 80px;
            padding: 40px;
            background: var(--related-bg);
            border-radius: 20px;
            box-shadow: 0 4px 20px var(--shadow);
            border: 1px solid var(--border);
        }

        .section-title {
            font-size: 2rem;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #1a202c;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .related-product-card {
            background: var(--card-bg);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-color: var(--accent);
        }

        .related-product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--darker-bg);
        }

        .related-product-info {
            padding: 20px;
        }

        .related-product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-primary);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 3em;
        }

        .related-product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 15px;
        }

        .related-product-category {
            display: inline-block;
            padding: 4px 12px;
            background: var(--darker-bg);
            color: var(--text-muted);
            border-radius: 20px;
            font-size: 0.85rem;
        }

        /* Status Badge */
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        /* Lightbox */
        .swiper-zoom-container {
            cursor: zoom-in;
        }

        /* Swiper Navigation */
        .swiper-button-next,
        .swiper-button-prev {
            background: rgba(255, 255, 255, 0.9);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .swiper-button-next:after,
        .swiper-button-prev:after {
            font-size: 20px;
            color: var(--text-primary);
        }
    </style>
</head>
<body style="background-color: #f5f7fa;">
    <?php include 'include/header.php'; ?>

    <div class="container">
        <div class="header-bar">
            <a href="black-market.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Marketplace
            </a>
            <div class="stats">
                <div class="stat"><i class="fas fa-eye"></i> <?= number_format($product['views'] + 1) ?> views</div>
                <div class="stat"><i class="fas fa-calendar-alt"></i> <?= date('M d, Y', strtotime($product['created_at'])) ?></div>
                <?php if($product['stock_quantity'] > 0): ?>
                    <div class="stat"><i class="fas fa-box"></i> <?= $product['stock_quantity'] ?> in stock</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="product-grid">
            <!-- Image Gallery with Swiper -->
            <div class="gallery-wrapper">
                <?php if($product['stock_quantity'] <= 0 && $product['stock_quantity'] !== null): ?>
                    <div class="status-badge" style="background:var(--danger);">
                        <i class="fas fa-times-circle"></i> Out of Stock
                    </div>
                <?php elseif($product['stock_quantity'] > 0): ?>
                    <div class="status-badge">
                        <i class="fas fa-check-circle"></i> Available
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($images)): ?>
                    <div class="swiper main-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($images as $image): ?>
                                <div class="swiper-slide">
                                    <div class="swiper-zoom-container">
                                        <img src="<?= htmlspecialchars($image['image_url']) ?>"
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             onerror="this.src='images/default-product.jpg'">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div class="swiper thumbs-swiper swiper-thumbs">
                            <div class="swiper-wrapper">
                                <?php foreach ($images as $image): ?>
                                    <div class="swiper-slide">
                                        <img src="<?= htmlspecialchars($image['image_url']) ?>"
                                             onerror="this.src='images/default-product.jpg'">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="width:100%; height:500px; display:flex; align-items:center; justify-content:center; background:var(--darker-bg);">
                        <i class="fas fa-image" style="font-size: 3rem; color: var(--text-muted);"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Product Info -->
            <div class="product-info">
                <div class="category-badge">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst($product['category'])) ?>
                </div>

                <h1 class="title"><?= htmlspecialchars($product['name']) ?></h1>

                <div class="price-box">
                    <?php if ($product['price'] > 0): ?>
                        <div class="price">
                            <span class="price-currency">KSH</span> 
                            <?= number_format($product['price'], 2) ?>
                        </div>
                        <?php if ($product['price_display'] === 'both'): ?>
                            <div class="price-note">
                                <i class="fas fa-handshake"></i> Price negotiable via contact
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="price-note">
                            <i class="fas fa-comments-dollar"></i> Contact seller for pricing
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($product['description'])): ?>
                    <div class="description">
                        <h3>
                            <i class="fas fa-align-left"></i> Description
                        </h3>
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($attributes)): ?>
                    <div class="attributes">
                        <h3><i class="fas fa-list-alt"></i> Product Specifications</h3>
                        <?php foreach ($attributes as $attr): ?>
                            <div class="attr-row">
                                <span class="attr-key">
                                    <i class="fas fa-chevron-right"></i>
                                    <?= htmlspecialchars($attr['attribute_key']) ?>
                                </span>
                                <span class="attr-value"><?= htmlspecialchars($attr['attribute_value']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Contact Options -->
                <div class="contact-options">
                    <h3><i class="fas fa-comment-dots"></i> Contact Seller</h3>
                    <div class="actions">
                        <?php if ($product['whatsapp_link']): ?>
                            <a href="<?= htmlspecialchars($product['whatsapp_link']) ?>" target="_blank" class="btn btn-whatsapp">
                                <i class="fab fa-whatsapp"></i>
                                <?= $product['price'] > 0 ? 'Chat on WhatsApp' : 'Inquire on WhatsApp' ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($product['telegram_link']): ?>
                            <a href="<?= htmlspecialchars($product['telegram_link']) ?>" target="_blank" class="btn btn-telegram">
                                <i class="fab fa-telegram"></i>
                                Message on Telegram
                            </a>
                        <?php endif; ?>

                        <?php if ($product['phone_number']): ?>
                            <a href="tel:<?= htmlspecialchars($product['phone_number']) ?>" class="btn btn-phone">
                                <i class="fas fa-phone-alt"></i>
                                Call Seller
                            </a>
                        <?php endif; ?>

                        <button class="btn btn-outline" onclick="shareProduct()">
                            <i class="fas fa-share-alt"></i> Share Product
                        </button>

                        <?php if (isset($_SESSION['user_id']) && $product['user_id'] == $_SESSION['user_id']): ?>
                            <button class="btn btn-danger" onclick="window.location.href='edit-product.php?id=<?= $productId ?>'">
                                <i class="fas fa-edit"></i> Edit Product
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Seller Information -->
                <?php if (isset($product['seller_name']) || isset($product['user_id'])): ?>
                    <div class="seller-info">
                        <div class="seller-avatar">
                            <?= strtoupper(substr($product['seller_name'] ?? 'Seller', 0, 1)) ?>
                        </div>
                        <div class="seller-details">
                            <h4><?= htmlspecialchars($product['seller_name'] ?? 'Verified Seller') ?></h4>
                            <p><i class="fas fa-star" style="color: #ffd700;"></i> Trusted Seller</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="related-section">
                <h2 class="section-title">
                    <i class="fas fa-th-large"></i> You Might Also Like
                </h2>
                <div class="related-grid">
                    <?php foreach ($relatedProducts as $related): ?>
                        <a href="view-product.php?id=<?= $related['id'] ?>" class="related-product-card">
                            <img src="<?= htmlspecialchars($related['primary_image'] ?? 'images/default-product.jpg') ?>" 
                                 alt="<?= htmlspecialchars($related['name']) ?>"
                                 class="related-product-image"
                                 onerror="this.src='images/default-product.jpg'">
                            <div class="related-product-info">
                                <h3 class="related-product-title">
                                    <?= htmlspecialchars($related['name']) ?>
                                </h3>
                                <div class="related-product-price">
                                    <?php if ($related['price'] > 0): ?>
                                        KSH <?= number_format($related['price'], 2) ?>
                                    <?php else: ?>
                                        Contact for Price
                                    <?php endif; ?>
                                </div>
                                <div class="related-product-category">
                                    <?= htmlspecialchars(ucfirst($related['category'])) ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Main gallery with thumbnails
        const thumbsSwiper = new Swiper('.thumbs-swiper', {
            spaceBetween: 10,
            slidesPerView: 5,
            watchSlidesProgress: true,
            breakpoints: {
                640: { slidesPerView: 6 },
                768: { slidesPerView: 7 }
            }
        });

        const mainSwiper = new Swiper('.main-swiper', {
            loop: true,
            spaceBetween: 10,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            thumbs: {
                swiper: thumbsSwiper,
            },
            zoom: true,
        });

        function shareProduct() {
            const productName = "<?= addslashes($product['name']) ?>";
            const productPrice = "<?= $product['price'] > 0 ? 'KSH ' . number_format($product['price'], 2) : 'Price on Contact' ?>";
            const shareText = `Check out "${productName}" - ${productPrice} on Black Market`;
            
            if (navigator.share) {
                navigator.share({
                    title: productName,
                    text: shareText,
                    url: window.location.href
                });
            } else {
                navigator.clipboard.writeText(`${shareText}\n${window.location.href}`);
                showNotification('Product link copied to clipboard!');
            }
        }

        function showNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--success);
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                font-weight: 600;
                z-index: 1000;
                animation: slideIn 0.3s ease;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>