<?php
session_start();

// Check if user is admin (you can implement proper authentication)
$isAdmin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// Set caching headers
header("Cache-Control: public, max-age=3600");
header("Pragma: cache");

// Start output buffering
ob_start("ob_gzhandler");

require_once 'db.php';

try {
    // Optimized query - only fetch what's needed
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.category, p.description, p.price, p.featured, p.created_at,
               p.whatsapp_number, p.whatsapp_link, p.price_display,
               (SELECT image_url FROM product_images 
                WHERE product_id = p.id 
                ORDER BY is_primary DESC 
                LIMIT 1) as product_image
        FROM black_market_products p
        WHERE p.status = 'active'
        ORDER BY p.featured DESC, p.created_at DESC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories
    $catStmt = $pdo->prepare("
        SELECT id, name FROM black_market_categories 
        WHERE is_active = 1 
        ORDER BY display_order
    ");
    $catStmt->execute();
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get product counts
    $countStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured
        FROM black_market_products
        WHERE status = 'active'
    ");
    $countStmt->execute();
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error = "Unable to load products. Please try again later.";
}

// Get base URL
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yusai Black Market - Exclusive Goods</title>
    
    <!-- Preload resources -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    
    <style>
        :root {
            --primary: #2c7be5;
            --secondary: #00d97e;
            --dark: #12263f;
            --light: #f9fbfd;
            --whatsapp: #25D366;
            --whatsapp-business: #075E54;
            --danger: #e63757;
            --warning: #f6c343;
            --border-radius: 12px;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
        /* WhatsApp Modal Styles */
        .whatsapp-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            backdrop-filter: blur(3px);
        }
        
        .whatsapp-modal-content {
            background: white;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 400px;
            animation: slideIn 0.3s ease;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .whatsapp-modal-header {
            background: linear-gradient(135deg, var(--whatsapp), #128C7E);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .whatsapp-modal-header i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .whatsapp-modal-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .whatsapp-options {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .whatsapp-option-btn {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            background: white;
            color: var(--dark);
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }
        
        .whatsapp-option-btn:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: var(--shadow);
        }
        
        .whatsapp-option-btn.whatsapp:hover {
            border-color: var(--whatsapp);
            background: rgba(37, 211, 102, 0.05);
        }
        
        .whatsapp-option-btn.business:hover {
            border-color: var(--whatsapp-business);
            background: rgba(7, 94, 84, 0.05);
        }
        
        .whatsapp-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }
        
        .whatsapp-icon.whatsapp {
            background: var(--whatsapp);
        }
        
        .whatsapp-icon.business {
            background: var(--whatsapp-business);
        }
        
        .whatsapp-option-text h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
            color: var(--dark);
        }
        
        .whatsapp-option-text p {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0;
        }
        
        .whatsapp-modal-footer {
            padding: 0 1.5rem 1.5rem;
            text-align: center;
        }
        
        .whatsapp-remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .whatsapp-remember input {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .whatsapp-cancel-btn {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 0.875rem;
            text-decoration: underline;
            padding: 0.5rem;
        }
        
        .whatsapp-cancel-btn:hover {
            color: var(--dark);
        }
        
        /* Update WhatsApp info display */
        .whatsapp-info {
            background: #f0f8f0;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .whatsapp-switch {
            background: none;
            border: none;
            color: var(--whatsapp);
            cursor: pointer;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .whatsapp-switch:hover {
            text-decoration: underline;
        }
        
        /* WhatsApp Preferences Badge */
        .whatsapp-preference-badge {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            background: white;
            border: 2px solid var(--whatsapp);
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-hover);
            z-index: 1000;
            animation: slideInLeft 0.3s ease;
        }
        
        @keyframes slideInLeft {
            from {
                transform: translateX(-100px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .whatsapp-preference-badge i {
            color: var(--whatsapp);
            font-size: 1.25rem;
        }
        
        .whatsapp-preference-badge span {
            font-size: 0.875rem;
            color: var(--dark);
        }
        
        .whatsapp-change-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            cursor: pointer;
            margin-left: 0.5rem;
        }
        
        .whatsapp-change-btn:hover {
            background: #1c65c7;
        }
        
        /* Add to existing styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.5;
            color: var(--dark);
            background: #f8fafc;
            min-height: 100vh;
        }
        
        /* Admin Header */
        .admin-header {
            background: linear-gradient(135deg, var(--dark), #1a1a2e);
            color: white;
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow);
        }
        
        .admin-header .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-title {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .admin-actions {
            display: flex;
            gap: 1rem;
        }
        
        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* Admin Panel */
        .admin-panel {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .admin-stat-card {
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .admin-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .admin-stat-icon.total { background: #e8f4ff; color: var(--primary); }
        .admin-stat-icon.featured { background: #f0e8ff; color: #8c68cd; }
        
        .admin-stat-info h3 {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        
        .admin-stat-number {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        /* Admin Actions */
        .admin-action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #1c65c7;
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-success {
            background: var(--secondary);
            color: white;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.1);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-card i {
            font-size: 2rem;
            color: var(--secondary);
        }
        
        /* WhatsApp Banner */
        .whatsapp-banner {
            background: linear-gradient(135deg, var(--whatsapp), #128C7E);
            color: white;
            padding: 1.5rem;
            margin: 2rem 0;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .whatsapp-banner i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .whatsapp-banner a {
            color: white;
            font-weight: bold;
            text-decoration: none;
            border-bottom: 2px solid white;
            padding-bottom: 2px;
        }
        
        /* Search & Filter */
        .search-filter {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin: 2rem 0;
            box-shadow: var(--shadow);
        }
        
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.1);
        }
        
        /* Category Tabs */
        .category-tabs {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            scrollbar-width: none;
        }
        
        .category-tabs::-webkit-scrollbar {
            display: none;
        }
        
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: #f1f5f9;
            color: #475569;
            border-radius: 50px;
            cursor: pointer;
            white-space: nowrap;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 6px rgba(44, 123, 229, 0.3);
        }
        
        /* Sort Select */
        .sort-select {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            background: white;
            color: var(--dark);
            font-size: 1rem;
            margin-top: 1rem;
            cursor: pointer;
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        /* Product Card */
        .product-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        
        .product-image {
            position: relative;
            height: 200px;
            background: #f8fafc;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        /* Admin Actions on Product Card */
        .product-admin-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
            z-index: 10;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .product-card:hover .product-admin-actions {
            opacity: 1;
        }
        
        .admin-action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .admin-action-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .btn-edit { color: var(--primary); }
        .btn-delete { color: var(--danger); }
        
        /* Status Badges */
        .status-badge {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: var(--primary);
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .featured-badge {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
        }
        
        /* Product Content */
        .product-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-category {
            font-size: 0.875rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .product-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--dark);
            line-height: 1.3;
        }
        
        .product-description {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.25rem;
            flex: 1;
        }
        
        /* Price Section */
        .price-section {
            margin-bottom: 1.5rem;
        }
        
        .price-label {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        
        .price-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* Action Buttons - Column Layout */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: auto;
        }
        
        .btn-whatsapp {
            background: var(--whatsapp);
            color: white;
            border: none;
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
            text-align: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-whatsapp:hover {
            background: #128C7E;
            transform: translateY(-1px);
        }
        
        .btn-view {
            background: #f1f5f9;
            color: var(--dark);
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-view:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        
        /* Footer */
        footer {
            text-align: center;
            padding: 2rem 1rem;
            margin-top: 3rem;
            border-top: 1px solid #e2e8f0;
            background: var(--dark);
            color: white;
        }
        
        /* Cart Count */
        .cart-count {
            background: var(--secondary);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -8px;
            right: -8px;
        }
        
        /* Admin Link */
        .admin-link {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-hover);
            z-index: 100;
            transition: all 0.2s;
        }
        
        .admin-link:hover {
            background: #1c65c7;
            transform: translateY(-2px);
        }
        
        /* No Products */
        .no-products {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .no-products i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Notification */
        .notification {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--primary);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-hover);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
            max-width: 90%;
        }
        
        .notification.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        
        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-hover);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 1;
        }
        
        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--border-radius);
        }
        
        .close-modal:hover {
            background: #f8f9fa;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.1);
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger);
        }
        
        .error-message {
            color: var(--danger);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .admin-header {
                padding: 1rem;
            }
            
            .admin-title {
                font-size: 1rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1rem;
            }
            
            .product-image {
                height: 180px;
            }
            
            .product-content {
                padding: 1.25rem;
            }
            
            .admin-stats {
                grid-template-columns: 1fr;
            }
            
            .admin-action-buttons {
                flex-direction: column;
            }
            
            .admin-link {
                bottom: 1rem;
                right: 1rem;
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
            
            .whatsapp-preference-badge {
                bottom: 1rem;
                left: 1rem;
                right: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .tab-btn {
                padding: 0.625rem 1.25rem;
                font-size: 0.875rem;
            }
            
            .search-filter {
                padding: 1rem;
            }
            
            .whatsapp-banner {
                padding: 1.25rem;
            }
            
            .whatsapp-options {
                padding: 1rem;
            }
            
            .whatsapp-option-btn {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    <?php if ($isAdmin): ?>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="header-content">
            <div class="admin-title">
                <i class="fas fa-shield-alt"></i>
                Admin Panel
            </div>
            <div class="admin-actions">
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>
    <?php endif; ?>
    
    <?php include 'include/header.php'; ?>
    <div class="empty"></div>
    
    <!-- Main Container -->
    <main class="main-container">
        <?php if ($isAdmin): ?>
        <!-- Admin Panel -->
        <div class="admin-panel">
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <div class="admin-stat-icon total">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="admin-stat-info">
                        <h3>Total Products</h3>
                        <div class="admin-stat-number"><?= $counts['total'] ?? 0 ?></div>
                    </div>
                </div>
                
                <div class="admin-stat-card">
                    <div class="admin-stat-icon featured">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="admin-stat-info">
                        <h3>Featured</h3>
                        <div class="admin-stat-number"><?= $counts['featured'] ?? 0 ?></div>
                    </div>
                </div>
            </div>
            
            <div class="admin-action-buttons">
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Product
                </button>
                <a href="manage-categories.php" class="btn btn-success">
                    <i class="fas fa-tags"></i> Manage Categories
                </a>
                <a href="view-all.php" class="btn" style="background: #f1f5f9; color: var(--dark);">
                    <i class="fas fa-list"></i> View All Products
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Search & Filter -->
        <div class="search-filter">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search products...">
            </div>
            
            <div class="category-tabs">
                <button class="tab-btn active" data-category="all">
                    <i class="fas fa-th-large"></i> All Items
                </button>
                <?php foreach ($categories as $category): ?>
                    <button class="tab-btn" data-category="<?= htmlspecialchars($category['name']) ?>">
                        <?= htmlspecialchars($category['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <select class="sort-select" id="sortSelect">
                <option value="newest">Newest First</option>
                <option value="price_low">Price: Low to High</option>
                <option value="price_high">Price: High to Low</option>
                <option value="featured">Featured First</option>
            </select>
        </div>
        
        <!-- Products Grid -->
        <div class="products-grid" id="productsGrid">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): 
                    $imageUrl = !empty($product['product_image']) ? htmlspecialchars($product['product_image']) : 'images/default-product.jpg';
                    $fullImageUrl = $baseUrl . '/' . $imageUrl;
                    $whatsappNumber = $product['whatsapp_number'] ?? '254719122571';
                    
                    $whatsappMessage = "Hello! I'm interested in this product:\n\n";
                    $whatsappMessage .= "📦 Product: " . $product['name'] . "\n";
                    $whatsappMessage .= "📝 Description: " . (strlen($product['description']) > 100 ? substr($product['description'], 0, 100) . '...' : $product['description']) . "\n";
                    $whatsappMessage .= "🏷️ Category: " . $product['category'] . "\n";
                    if ($product['price'] > 0) {
                        $whatsappMessage .= "💰 Estimated Price: KSh " . number_format($product['price'], 0) . "\n\n";
                    }
                    $whatsappMessage .= "Please send me more details and the exact price.";
                    
                    $viewLink = "view-product.php?id=" . $product['id'];
                    $isNew = strtotime($product['created_at']) > strtotime('-3 days');
                ?>
                    <div class="product-card" 
                         data-category="<?= htmlspecialchars($product['category']) ?>"
                         data-price="<?= $product['price'] ?>"
                         data-date="<?= htmlspecialchars($product['created_at']) ?>"
                         data-featured="<?= $product['featured'] ?>"
                         data-id="<?= $product['id'] ?>">
                        
                        <?php if ($isAdmin): ?>
                        <div class="product-admin-actions">
                            <button class="admin-action-btn btn-edit" title="Edit Product" onclick="editProduct(<?= $product['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="admin-action-btn btn-delete" title="Delete Product" onclick="deleteProduct(<?= $product['id'] ?>, '<?= htmlspecialchars(addslashes($product['name'])) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <div class="product-image">
                            <img src="<?= $imageUrl ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 loading="lazy"
                                 onerror="this.src='images/default-product.jpg'">
                            
                            <?php if ($product['featured']): ?>
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Featured
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($isNew): ?>
                                <div class="status-badge">
                                    <i class="fas fa-bolt"></i> NEW
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-content">
                            <div class="product-category">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($product['category']) ?>
                            </div>
                            
                            <h3 class="product-title">
                                <?= htmlspecialchars($product['name']) ?>
                            </h3>
                            
                            <p class="product-description">
                                <?= htmlspecialchars(strlen($product['description']) > 100 ? substr($product['description'], 0, 100) . '...' : $product['description']) ?>
                            </p>
                            
                            <?php if (!empty($whatsappNumber)): ?>
                            <div class="whatsapp-info">
                                <div>
                                    <i class="fab fa-whatsapp"></i>
                                    Contact: <?= htmlspecialchars($whatsappNumber) ?>
                                </div>
                                <button class="whatsapp-switch" onclick="changeWhatsAppApp('<?= $whatsappNumber ?>', '<?= urlencode($whatsappMessage) ?>', '<?= urlencode($fullImageUrl) ?>')">
                                    <i class="fas fa-exchange-alt"></i> Change App
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <div class="price-section">
                                <div class="price-label">Estimated Price</div>
                                <div class="price-value">KSh <?= number_format($product['price'], 0) ?></div>
                            </div>
                            
                            <div class="action-buttons">
                                <button class="btn-whatsapp" 
                                        onclick="openWhatsAppChoice('<?= $whatsappNumber ?>', '<?= urlencode($whatsappMessage) ?>', '<?= urlencode($fullImageUrl) ?>')">
                                    <i class="fab fa-whatsapp"></i> WhatsApp for Price
                                </button>
                                
                                <a href="<?= htmlspecialchars($viewLink) ?>" class="btn btn-view">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products">
                    <i class="fas fa-box-open"></i>
                    <h3>No products available</h3>
                    <p><?= $isAdmin ? 'Add your first product using the button above' : 'Check back soon for new items' ?></p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- WhatsApp Choice Modal -->
    <div class="whatsapp-modal" id="whatsappModal">
        <div class="whatsapp-modal-content">
            <div class="whatsapp-modal-header">
                <i class="fab fa-whatsapp"></i>
                <h3>Choose WhatsApp App</h3>
            </div>
            
            <div class="whatsapp-options">
                <button class="whatsapp-option-btn whatsapp" onclick="openWhatsAppRegular()">
                    <div class="whatsapp-icon whatsapp">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="whatsapp-option-text">
                        <h4>WhatsApp</h4>
                        <p>Open in regular WhatsApp app</p>
                    </div>
                </button>
                
                <button class="whatsapp-option-btn business" onclick="openWhatsAppBusiness()">
                    <div class="whatsapp-icon business">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="whatsapp-option-text">
                        <h4>WhatsApp Business</h4>
                        <p>Open in WhatsApp Business app</p>
                    </div>
                </button>
            </div>
            
            <div class="whatsapp-modal-footer">
                <div class="whatsapp-remember">
                    <input type="checkbox" id="rememberChoice">
                    <label for="rememberChoice">Remember my choice</label>
                </div>
                <button class="whatsapp-cancel-btn" onclick="closeWhatsAppModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <!-- Add Product Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Product</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="process-product.php" class="modal-body" id="addProductForm" onsubmit="return validateWhatsAppNumber()">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label class="form-label required-field">Product Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label required-field">Category</label>
                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['name']) ?>">
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label required-field">Description</label>
                    <textarea name="description" class="form-control" rows="4" required placeholder="Detailed product description"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price (KSh)</label>
                    <input type="number" name="price" class="form-control" step="1" placeholder="0 for WhatsApp only">
                    <small style="color: #64748b; font-size: 0.875rem;">Enter price in Kenyan Shillings</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label required-field">WhatsApp Number</label>
                    <input type="tel" name="whatsapp_number" id="whatsapp_number" 
                           class="form-control" 
                           placeholder="254XXXXXXXXX"
                           pattern="^254[0-9]{9}$"
                           title="Enter WhatsApp number starting with 254 followed by 9 digits"
                           required>
                    <div class="error-message" id="whatsapp-error">Please enter a valid WhatsApp number starting with 254</div>
                    <small style="color: #64748b; font-size: 0.875rem;">Enter WhatsApp number with country code (254 for Kenya, e.g., 254712345678)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Price Display</label>
                    <select name="price_display" class="form-control">
                        <option value="whatsapp">WhatsApp Only</option>
                        <option value="price">Price Only</option>
                        <option value="both">Both (Price + WhatsApp)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Product Images</label>
                    <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="featured" value="1">
                        Mark as Featured
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
 
    <footer>
        <p>&copy; <span id="year"></span> Yusai Black Market. All rights reserved.</p>
    </footer>
    
    <!-- WhatsApp Preference Badge -->
    <div id="whatsappPreferenceBadge" style="display: none;" class="whatsapp-preference-badge">
        <i class="fab fa-whatsapp"></i>
        <span>Opening with: <strong id="currentApp">WhatsApp</strong></span>
        <button class="whatsapp-change-btn" onclick="showWhatsAppModal()">Change</button>
    </div>
    
    <script>
        // WhatsApp App Manager
        class WhatsAppManager {
            constructor() {
                this.preferredApp = localStorage.getItem('whatsappPreferredApp') || 'auto';
                this.modalOpen = false;
                this.currentNumber = '';
                this.currentMessage = '';
                this.currentImage = '';
                
                // Show preference badge if user has made a choice
                if (this.preferredApp !== 'auto') {
                    this.showPreferenceBadge();
                }
            }
            
            showPreferenceBadge() {
                const badge = document.getElementById('whatsappPreferenceBadge');
                const currentApp = document.getElementById('currentApp');
                
                const appNames = {
                    'regular': 'WhatsApp',
                    'business': 'WhatsApp Business',
                    'web': 'Web WhatsApp'
                };
                
                currentApp.textContent = appNames[this.preferredApp] || 'Auto-detect';
                badge.style.display = 'flex';
            }
            
            openWhatsAppChoice(phone, message, image) {
                this.currentNumber = phone;
                this.currentMessage = message;
                this.currentImage = image;
                this.modalOpen = true;
                
                document.getElementById('whatsappModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
            
            closeWhatsAppModal() {
                document.getElementById('whatsappModal').style.display = 'none';
                document.body.style.overflow = 'auto';
                this.modalOpen = false;
            }
            
            showWhatsAppModal() {
                if (!this.modalOpen) {
                    this.openWhatsAppChoice(this.currentNumber, this.currentMessage, this.currentImage);
                }
            }
            
            openRegularWhatsApp() {
                const url = `https://wa.me/${this.currentNumber}?text=${this.currentMessage}`;
                this.rememberChoice('regular');
                this.openUrl(url);
            }
            
            openBusinessWhatsApp() {
                // WhatsApp Business uses the same URL scheme on Android
                // On iOS, it should handle the URL scheme automatically
                const url = `https://wa.me/${this.currentNumber}?text=${this.currentMessage}`;
                const urlScheme = `whatsapp://send?phone=${this.currentNumber}&text=${this.currentMessage}`;
                
                this.rememberChoice('business');
                
                // Try URL scheme first, fallback to web
                this.openUrl(urlScheme, url);
            }
            
            openAutoDetect() {
                const userAgent = navigator.userAgent || navigator.vendor || window.opera;
                const isAndroid = /android/i.test(userAgent);
                const isIOS = /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;
                
                if (this.preferredApp === 'regular') {
                    this.openRegularWhatsApp();
                } else if (this.preferredApp === 'business') {
                    this.openBusinessWhatsApp();
                } else if (this.preferredApp === 'web') {
                    this.openWebWhatsApp();
                } else {
                    // Auto-detect
                    if (isAndroid) {
                        // Try Business first on Android
                        this.openBusinessWhatsApp();
                    } else if (isIOS) {
                        // iOS handles the choice automatically
                        this.openRegularWhatsApp();
                    } else {
                        // Desktop - use web
                        this.openWebWhatsApp();
                    }
                }
            }
            
            openWebWhatsApp() {
                const url = `https://web.whatsapp.com/send?phone=${this.currentNumber}&text=${this.currentMessage}`;
                this.rememberChoice('web');
                this.openUrl(url);
            }
            
            openUrl(primaryUrl, fallbackUrl = null) {
                // Close modal first
                this.closeWhatsAppModal();
                
                // Try to open the URL
                window.location.href = primaryUrl;
                
                // If it fails (no app installed), fall back
                setTimeout(() => {
                    if (document.hasFocus() && fallbackUrl) {
                        window.location.href = fallbackUrl;
                    } else if (document.hasFocus()) {
                        // Try web version as last resort
                        window.open(`https://web.whatsapp.com/send?phone=${this.currentNumber}&text=${this.currentMessage}`, '_blank');
                    }
                }, 500);
            }
            
            rememberChoice(appType) {
                const rememberCheckbox = document.getElementById('rememberChoice');
                
                if (rememberCheckbox && rememberCheckbox.checked) {
                    this.preferredApp = appType;
                    localStorage.setItem('whatsappPreferredApp', appType);
                    this.showPreferenceBadge();
                }
            }
            
            clearPreference() {
                this.preferredApp = 'auto';
                localStorage.removeItem('whatsappPreferredApp');
                document.getElementById('whatsappPreferenceBadge').style.display = 'none';
            }
            
            detectInstalledApps(callback) {
                // This is a basic detection - could be expanded
                const isAndroid = /android/i.test(navigator.userAgent);
                const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                
                // For now, we'll assume both apps could be installed
                // In a production app, you might want to implement more sophisticated detection
                callback({
                    hasRegular: true,
                    hasBusiness: true,
                    platform: isAndroid ? 'android' : isIOS ? 'ios' : 'desktop'
                });
            }
        }
        
        // Initialize WhatsApp Manager
        const whatsAppManager = new WhatsAppManager();
        
        // Global functions for onclick handlers
        function openWhatsAppChoice(phone, message, image) {
            whatsAppManager.openWhatsAppChoice(phone, message, image);
        }
        
        function openWhatsAppRegular() {
            whatsAppManager.openRegularWhatsApp();
        }
        
        function openWhatsAppBusiness() {
            whatsAppManager.openBusinessWhatsApp();
        }
        
        function closeWhatsAppModal() {
            whatsAppManager.closeWhatsAppModal();
        }
        
        function showWhatsAppModal() {
            whatsAppManager.showWhatsAppModal();
        }
        
        function changeWhatsAppApp(phone, message, image) {
            whatsAppManager.openWhatsAppChoice(phone, message, image);
        }
        
        // Page initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Update year
            document.getElementById('year').textContent = new Date().getFullYear();
            
            // Initialize cart count
            updateCartCount();
            
            // Initialize search
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterProducts();
                }, 300);
            });
            
            // Initialize sort
            document.getElementById('sortSelect').addEventListener('change', filterProducts);
            
            // Initialize tabs
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    filterProducts();
                });
            });
            
            // WhatsApp number validation and formatting
            const whatsappInput = document.getElementById('whatsapp_number');
            if (whatsappInput) {
                whatsappInput.addEventListener('input', function(e) {
                    const errorDiv = document.getElementById('whatsapp-error');
                    let value = e.target.value.replace(/\D/g, '');
                    
                    // Hide error while typing
                    errorDiv.style.display = 'none';
                    
                    // Auto-format for Kenyan numbers
                    if (value.length === 9 && value.startsWith('7')) {
                        value = '254' + value;
                    } else if (value.length === 10 && value.startsWith('07')) {
                        value = '254' + value.substring(1);
                    }
                    
                    e.target.value = value;
                });
                
                // Validate on blur
                whatsappInput.addEventListener('blur', function() {
                    validateWhatsAppNumber();
                });
            }
            
            // Close WhatsApp modal on ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && whatsAppManager.modalOpen) {
                    closeWhatsAppModal();
                }
            });
            
            // Close WhatsApp modal when clicking outside
            const whatsappModal = document.getElementById('whatsappModal');
            if (whatsappModal) {
                whatsappModal.addEventListener('click', function(event) {
                    if (event.target === whatsappModal) {
                        closeWhatsAppModal();
                    }
                });
            }
        });
        
        // WhatsApp number validation
        function validateWhatsAppNumber() {
            const whatsappInput = document.getElementById('whatsapp_number');
            const errorDiv = document.getElementById('whatsapp-error');
            
            if (!whatsappInput) return true;
            
            // Remove non-digits
            const cleanNumber = whatsappInput.value.replace(/\D/g, '');
            
            // Check if empty
            if (!cleanNumber) {
                errorDiv.textContent = "WhatsApp number is required";
                errorDiv.style.display = 'block';
                whatsappInput.focus();
                return false;
            }
            
            // Check if starts with 254
            if (!cleanNumber.startsWith('254')) {
                errorDiv.textContent = "WhatsApp number must start with 254 (Kenyan number)";
                errorDiv.style.display = 'block';
                whatsappInput.focus();
                return false;
            }
            
            // Check total length (254 + 9 digits = 12 digits)
            if (cleanNumber.length !== 12) {
                errorDiv.textContent = "WhatsApp number must be 12 digits total (254 followed by 9 digits)";
                errorDiv.style.display = 'block';
                whatsappInput.focus();
                return false;
            }
            
            // Hide error if valid
            errorDiv.style.display = 'none';
            return true;
        }
        
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
            document.body.style.overflow = 'auto';
        }
        
        // Filter and sort products
        function filterProducts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const sortBy = document.getElementById('sortSelect').value;
            const activeCategory = document.querySelector('.tab-btn.active').dataset.category;
            const products = Array.from(document.querySelectorAll('.product-card'));
            
            let visibleCount = 0;
            
            products.forEach(card => {
                const cardCategory = card.dataset.category;
                const name = card.querySelector('.product-title').textContent.toLowerCase();
                const desc = card.querySelector('.product-description').textContent.toLowerCase();
                const cat = card.querySelector('.product-category').textContent.toLowerCase();
                
                let shouldShow = true;
                
                // Filter by category
                if (activeCategory !== 'all' && cardCategory.toLowerCase() !== activeCategory.toLowerCase()) {
                    shouldShow = false;
                }
                
                // Filter by search
                if (searchTerm && shouldShow) {
                    if (!name.includes(searchTerm) && !desc.includes(searchTerm) && !cat.includes(searchTerm)) {
                        shouldShow = false;
                    }
                }
                
                card.style.display = shouldShow ? 'block' : 'none';
                if (shouldShow) visibleCount++;
            });
            
            // Sort visible products
            const visibleProducts = products.filter(card => card.style.display !== 'none');
            visibleProducts.sort((a, b) => {
                const priceA = parseFloat(a.dataset.price) || 0;
                const priceB = parseFloat(b.dataset.price) || 0;
                const dateA = new Date(a.dataset.date);
                const dateB = new Date(b.dataset.date);
                const featuredA = parseInt(a.dataset.featured) || 0;
                const featuredB = parseInt(b.dataset.featured) || 0;
                
                switch(sortBy) {
                    case 'price_low': return priceA - priceB;
                    case 'price_high': return priceB - priceA;
                    case 'newest': return dateB - dateA;
                    case 'featured':
                        if (featuredA && !featuredB) return -1;
                        if (!featuredA && featuredB) return 1;
                        return dateB - dateA;
                    default: return 0;
                }
            });
            
            // Reorder
            const grid = document.getElementById('productsGrid');
            visibleProducts.forEach(card => grid.appendChild(card));
        }
        
        // Edit product function
        function editProduct(productId) {
            showNotification('Edit feature coming soon...');
            // You can implement AJAX call to load product data and show edit modal
        }
        
        // Delete product function
        function deleteProduct(productId, productName) {
            if (confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
                // You can implement AJAX call to delete product
                showNotification(`Deleting ${productName}...`);
            }
        }
        
        // Cart functionality
        function updateCartCount() {
            try {
                const cart = JSON.parse(localStorage.getItem('cart')) || [];
                const blackMarketCart = JSON.parse(localStorage.getItem('blackMarketCart')) || [];
                
                const cartItems = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
                const blackMarketItems = blackMarketCart.reduce((sum, item) => sum + (item.quantity || 0), 0);
                const totalItems = cartItems + blackMarketItems;
                
                document.querySelectorAll('.cart-count').forEach(el => {
                    el.textContent = totalItems;
                });
            } catch (e) {
                // Silently handle errors
                document.querySelectorAll('.cart-count').forEach(el => {
                    el.textContent = '0';
                });
            }
        }
        
        // Show notification
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => notification.classList.add('show'), 10);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Lazy load images
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    }
                });
            });
            
            // Mark images for lazy loading
            document.querySelectorAll('img[loading="lazy"]').forEach(img => {
                img.dataset.src = img.src;
                img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PC9zdmc+';
                observer.observe(img);
            });
        }
        
        // Listen for storage events to update cart count
        window.addEventListener('storage', function(event) {
            if (event.key === 'cart' || event.key === 'blackMarketCart') {
                updateCartCount();
            }
        });
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    closeModal();
                }
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>