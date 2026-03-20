<?php
$productImage = !empty($product['primary_image']) ? htmlspecialchars($product['primary_image']) : 'images/default-product.jpg';
$priceDisplay = $product['price_display'] ?? 'whatsapp';
$price = isset($product['price']) ? number_format($product['price'], 2) : '0.00';
$whatsappLink = $product['whatsapp_link'] ?? '#';
?>

<div class="product-card" 
     data-category="<?= htmlspecialchars($product['category']) ?>"
     data-price="<?= $product['price'] ?? 0 ?>"
     data-date="<?= htmlspecialchars($product['created_at']) ?>">
    
    <?php if ($product['featured'] ?? false): ?>
        <span class="product-badge">
            <i class="fas fa-star"></i> Featured
        </span>
    <?php endif; ?>
    
    <a href="view-product.php?id=<?= $product['id'] ?>">
        <img src="<?= $productImage ?>" 
             alt="<?= htmlspecialchars($product['name']) ?>"
             class="product-image"
             onerror="this.src='images/default-product.jpg'">
    </a>
    
    <div class="product-info">
        <div class="product-category">
            <i class="fas fa-tag"></i>
            <?= htmlspecialchars(ucfirst($product['category'])) ?>
        </div>
        
        <h3 class="product-name">
            <a href="view-product.php?id=<?= $product['id'] ?>" 
               style="color: inherit; text-decoration: none;">
                <?= htmlspecialchars($product['name']) ?>
            </a>
        </h3>
        
        <p class="product-description">
            <?= htmlspecialchars($product['short_description'] ?? substr($product['description'], 0, 100) . '...') ?>
        </p>
        
        <div class="product-price-section">
            <div>
                <?php if (in_array($priceDisplay, ['price', 'both']) && $product['price'] > 0): ?>
                    <div class="product-price">
                        $<?= $price ?>
                    </div>
                <?php elseif ($priceDisplay === 'whatsapp'): ?>
                    <div class="price-note">
                        <i class="fab fa-whatsapp"></i> Price on WhatsApp
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="action-buttons">
                <?php if ($priceDisplay === 'whatsapp' || $priceDisplay === 'both'): ?>
                    <a href="<?= htmlspecialchars($whatsappLink) ?>" 
                       target="_blank" 
                       class="btn-whatsapp">
                        <i class="fab fa-whatsapp"></i>
                        <?= $priceDisplay === 'both' ? 'Inquire' : 'Chat' ?>
                    </a>
                <?php endif; ?>
                
                <?php if ($priceDisplay === 'price' || $priceDisplay === 'both'): ?>
                    <a href="view-product.php?id=<?= $product['id'] ?>" class="btn-view">
                        <i class="fas fa-eye"></i>
                        View
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>