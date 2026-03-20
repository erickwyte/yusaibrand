<?php
session_start();
require_once '../db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new product
    if (isset($_POST['add_product'])) {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $short_description = $_POST['short_description'] ?? '';
        $category = $_POST['category'] ?? '';
        $subcategory = $_POST['subcategory'] ?? '';
        $price = $_POST['price'] ?? 0;
        $price_display = $_POST['price_display'] ?? 'whatsapp';
        $featured = isset($_POST['featured']) ? 1 : 0;
        
        // Get WhatsApp number from form - REQUIRED FIELD
        $whatsapp_number = $_POST['whatsapp_number'] ?? '';
        
        // Validate WhatsApp number
        if (empty($whatsapp_number)) {
            $error = "WhatsApp number is required!";
        } else {
            // Clean WhatsApp number (remove spaces, dashes, plus sign)
            $whatsapp_number = preg_replace('/[^0-9]/', '', $whatsapp_number);
            
            // Ensure WhatsApp number starts with country code
            if (substr($whatsapp_number, 0, 2) === '07' || substr($whatsapp_number, 0, 1) === '7') {
                // Convert Kenyan number to international format
                if (substr($whatsapp_number, 0, 1) === '7') {
                    $whatsapp_number = '254' . $whatsapp_number;
                } else if (substr($whatsapp_number, 0, 2) === '07') {
                    $whatsapp_number = '254' . substr($whatsapp_number, 1);
                }
            }
            
            // Create WhatsApp link
            $whatsapp_link = !empty($whatsapp_number) ? "https://wa.me/{$whatsapp_number}" : null;
            
            // If price is empty and price_display is 'price', set to 'whatsapp'
            if (empty($price) && $price_display === 'price') {
                $price_display = 'whatsapp';
            }
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO black_market_products 
                    (name, description, short_description, category, subcategory, price, 
                     whatsapp_number, whatsapp_link, price_display, status, featured)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
                ");
                $stmt->execute([
                    $name, $description, $short_description, $category, $subcategory, $price,
                    $whatsapp_number, $whatsapp_link, $price_display, $featured
                ]);
                
                $productId = $pdo->lastInsertId();
                
                // Handle image uploads
                if (!empty($_FILES['images']['name'][0])) {
                    $uploadDir = '../uploads/black-market_products/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
                        if ($_FILES['images']['error'][$index] === 0) {
                            $fileName = uniqid() . '_' . basename($_FILES['images']['name'][$index]);
                            $uploadPath = $uploadDir . $fileName;
                            
                            if (move_uploaded_file($tmpName, $uploadPath)) {
                                $isPrimary = $index === 0 ? 1 : 0;
                                $imgStmt = $pdo->prepare("
                                    INSERT INTO product_images (product_id, image_url, is_primary, display_order)
                                    VALUES (?, ?, ?, ?)
                                ");
                                $imgStmt->execute([$productId, 'uploads/black-market_products/' . $fileName, $isPrimary, $index]);
                            }
                        }
                    }
                }
                
                $_SESSION['success'] = "Product added successfully!";
                header('Location: black-market-admin.php');
                exit;
                
            } catch (PDOException $e) {
                error_log("Add Product Error: " . $e->getMessage());
                $error = "Failed to add product: " . $e->getMessage();
            }
        }
    }
    
    // Handle edit product
    if (isset($_POST['edit_product'])) {
        $productId = $_POST['product_id'];
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = $_POST['price'] ?? 0;
        $price_display = $_POST['price_display'] ?? 'whatsapp';
        $featured = isset($_POST['featured']) ? 1 : 0;
        
        // Get WhatsApp number from form - REQUIRED FIELD
        $whatsapp_number = $_POST['whatsapp_number'] ?? '';
        
        // Validate WhatsApp number
        if (empty($whatsapp_number)) {
            $error = "WhatsApp number is required!";
        } else {
            // Clean WhatsApp number (remove spaces, dashes, plus sign)
            $whatsapp_number = preg_replace('/[^0-9]/', '', $whatsapp_number);
            
            // Ensure WhatsApp number starts with country code
            if (substr($whatsapp_number, 0, 2) === '07' || substr($whatsapp_number, 0, 1) === '7') {
                // Convert Kenyan number to international format
                if (substr($whatsapp_number, 0, 1) === '7') {
                    $whatsapp_number = '254' . $whatsapp_number;
                } else if (substr($whatsapp_number, 0, 2) === '07') {
                    $whatsapp_number = '254' . substr($whatsapp_number, 1);
                }
            }
            
            // Create WhatsApp link
            $whatsapp_link = !empty($whatsapp_number) ? "https://wa.me/{$whatsapp_number}" : null;
            
            // If price is empty and price_display is 'price', set to 'whatsapp'
            if (empty($price) && $price_display === 'price') {
                $price_display = 'whatsapp';
            }
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE black_market_products 
                    SET name = ?, description = ?, price = ?, 
                        whatsapp_number = ?, whatsapp_link = ?, 
                        price_display = ?, featured = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $description, $price, 
                    $whatsapp_number, $whatsapp_link, 
                    $price_display, $featured, $productId
                ]);
                
                $_SESSION['success'] = "Product updated successfully!";
                header('Location: black-market-admin.php');
                exit;
            } catch (PDOException $e) {
                $error = "Failed to update product: " . $e->getMessage();
            }
        }
    }
    
    // Handle add category
    if (isset($_POST['add_category'])) {
        $name = $_POST['name'] ?? '';
        $icon = $_POST['icon'] ?? 'fas fa-tag';
        
        if (!empty($name)) {
            $slug = strtolower(str_replace(' ', '-', $name));
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO black_market_categories (name, slug, icon, is_active)
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([$name, $slug, $icon]);
                
                $_SESSION['success'] = "Category added successfully!";
                header('Location: black-market-admin.php');
                exit;
            } catch (PDOException $e) {
                $error = "Failed to add category: " . $e->getMessage();
            }
        }
    }
}

// Handle delete product
if (isset($_GET['delete'])) {
    $productId = $_GET['delete'];
    try {
        // Delete images first
        $imgStmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
        $imgStmt->execute([$productId]);
        
        // Delete product
        $delStmt = $pdo->prepare("DELETE FROM black_market_products WHERE id = ?");
        $delStmt->execute([$productId]);
        
        $_SESSION['success'] = "Product deleted successfully!";
        header('Location: black-market-admin.php');
        exit;
    } catch (PDOException $e) {
        $error = "Failed to delete product: " . $e->getMessage();
    }
}

// Handle delete category
if (isset($_GET['delete_category'])) {
    $categoryId = $_GET['delete_category'];
    try {
        // Check if category has products
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM black_market_products WHERE category = (SELECT name FROM black_market_categories WHERE id = ?)");
        $checkStmt->execute([$categoryId]);
        $productCount = $checkStmt->fetchColumn();
        
        if ($productCount > 0) {
            $error = "Cannot delete category. It has $productCount products assigned to it.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM black_market_categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            
            $_SESSION['success'] = "Category deleted successfully!";
            header('Location: black-market-admin.php');
            exit;
        }
    } catch (PDOException $e) {
        $error = "Failed to delete category: " . $e->getMessage();
    }
}

// Check for success message
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Get all products for listing
try {
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
    $query = "
        SELECT p.*, 
               (SELECT COUNT(*) FROM product_images WHERE product_id = p.id) as image_count,
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM black_market_products p
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($category)) {
        $query .= " AND p.category = ?";
        $params[] = $category;
    }
    
    $query .= " ORDER BY p.featured DESC, p.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for filter and dropdown
    $catStmt = $pdo->prepare("
        SELECT DISTINCT category 
        FROM black_market_products 
        WHERE category IS NOT NULL AND category != ''
        ORDER BY category
    ");
    $catStmt->execute();
    $productCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all categories from categories table
    $allCatStmt = $pdo->prepare("
        SELECT * FROM black_market_categories 
        WHERE is_active = 1 
        ORDER BY name
    ");
    $allCatStmt->execute();
    $allCategories = $allCatStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get product counts
    $countStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured
        FROM black_market_products
    ");
    $countStmt->execute();
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Admin Product List Error: " . $e->getMessage());
    $error = "Unable to load products.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Black Market Admin - Exclusive Goods Management</title>
    
    <!-- Preload resources -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2c7be5;
            --secondary: #00d97e;
            --dark: #12263f;
            --light: #f9fbfd;
            --whatsapp: #25D366;
            --danger: #e63757;
            --warning: #f6c343;
            --border-radius: 12px;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        
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
        
        /* Header */
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        /* Main Container */
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.total { background: #e8f4ff; color: var(--primary); }
        .stat-icon.featured { background: #f0e8ff; color: #8c68cd; }
        
        .stat-info h3 {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        /* Tabs */
        .admin-tabs {
            display: flex;
            gap: 0.5rem;
            background: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            overflow-x: auto;
            scrollbar-width: none;
        }
        
        .admin-tabs::-webkit-scrollbar {
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab-btn:hover {
            background: #e2e8f0;
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 6px rgba(44, 123, 229, 0.3);
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        /* Buttons */
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
        
        .btn-success {
            background: var(--secondary);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            box-shadow: var(--shadow);
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #475569;
        }
        
        .filter-input {
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.1);
        }
        
        /* Products Table */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8fafc;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid #e2e8f0;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        /* Product Image */
        .product-image-small {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        
        /* Display Badge */
        .display-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            background: #e8f4ff;
            color: var(--primary);
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .btn-edit { background: #e8f4ff; color: var(--primary); }
        .btn-delete { background: #ffe8ec; color: var(--danger); }
        .btn-view { background: #f0f8ff; color: var(--primary); }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        /* WhatsApp Badge */
        .whatsapp-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #e0f8ec;
            color: var(--whatsapp);
        }
        
        /* Categories Grid */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .category-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        
        .category-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .category-name {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .category-slug {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 1rem;
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
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 800px;
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
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
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
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        /* WhatsApp Input */
        .whatsapp-input-group {
            position: relative;
        }
        
        .whatsapp-input-group .input-prefix {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--whatsapp);
            font-size: 1rem;
        }
        
        .whatsapp-input-group .form-control {
            padding-left: 40px;
        }
        
        /* Add required field styling */
        .required-field::after {
            content: " *";
            color: var(--danger);
        }
        
        .whatsapp-input-group input:invalid {
            border-color: var(--danger);
        }
        
        .whatsapp-input-group input:invalid:focus {
            box-shadow: 0 0 0 3px rgba(230, 55, 87, 0.1);
        }
        
        .error-message {
            color: var(--danger);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        /* Image Upload */
        .image-upload-container {
            border: 2px dashed #e2e8f0;
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .image-upload-container:hover {
            border-color: var(--primary);
            background: #f8fafc;
        }
        
        .image-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .preview-image {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: var(--border-radius);
            border: 1px solid #e2e8f0;
        }
        
        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        
        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #e0f8ec;
            color: var(--secondary);
            border: 1px solid #c3f0d9;
        }
        
        .alert-error {
            background: #ffe8ec;
            color: var(--danger);
            border: 1px solid #f8d7da;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .filter-bar {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .admin-container {
                padding: 1rem;
            }
            
            .admin-header {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div class="header-content">
            <div class="header-title">
                <i class="fas fa-shield-alt"></i>
                Black Market Admin
            </div>
            <div class="header-actions">
                <a href="../index.php" class="btn btn-primary">
                    <i class="fas fa-store"></i> View Store
                </a>
                
            </div>
        </div>
    </header>
    
    <!-- Main Container -->
    <main class="admin-container">
        <!-- Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Products</h3>
                    <div class="stat-number"><?= $counts['total'] ?? 0 ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon featured">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3>Featured</h3>
                    <div class="stat-number"><?= $counts['featured'] ?? 0 ?></div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="admin-tabs">
            <button class="tab-btn active" data-tab="products">
                <i class="fas fa-box"></i> Products
            </button>
            <button class="tab-btn" data-tab="categories">
                <i class="fas fa-tags"></i> Categories
            </button>
        </div>
        
        <!-- Products Tab -->
        <div class="tab-content active" id="productsTab">
            <div class="section-header">
                <h2>Product Management</h2>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input type="text" class="filter-input" id="searchInput" 
                           placeholder="Search products..." 
                           value="<?= htmlspecialchars($search) ?>"
                           onkeyup="filterProducts()">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select class="filter-input" id="categoryFilter" onchange="filterProducts()">
                        <option value="">All Categories</option>
                        <?php foreach ($productCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" 
                                    <?= $category === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group" style="align-self: end;">
                    <button class="btn btn-success" style="width: 100%;" onclick="filterProducts()">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
            
            <!-- Products Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price (KSh)</th>
                            <th>WhatsApp</th>
                            <th>Display</th>
                            <th>Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($product['primary_image'])): ?>
                                            <img src="../<?= htmlspecialchars($product['primary_image']) ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                                 class="product-image-small"
                                                 onerror="this.src='../images/default-product.jpg'">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #f8fafc; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['name']) ?></td>
                                    <td><?= htmlspecialchars($product['category']) ?></td>
                                    <td>
                                        <?php if ($product['price'] > 0): ?>
                                            KSh <?= number_format($product['price'], 0) ?>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($product['whatsapp_number'])): ?>
                                            <span class="whatsapp-badge">
                                                <i class="fab fa-whatsapp"></i>
                                                <?= htmlspecialchars($product['whatsapp_number']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="display-badge">
                                            <?= ucfirst($product['price_display']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($product['featured']): ?>
                                            <i class="fas fa-star" style="color: var(--warning);"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-view" 
                                                    onclick="viewProduct(<?= $product['id'] ?>)"
                                                    title="View Product">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-edit" 
                                                    onclick="editProduct(<?= $product['id'] ?>)"
                                                    title="Edit Product">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action btn-delete" 
                                                    onclick="deleteProduct(<?= $product['id'] ?>)"
                                                    title="Delete Product">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-box-open"></i>
                                    <h3>No products found</h3>
                                    <p>Add your first product using the button above</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Categories Tab -->
        <div class="tab-content" id="categoriesTab">
            <div class="section-header">
                <h2>Category Management</h2>
                <button class="btn btn-primary" onclick="openAddCategoryModal()">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
            
            <div class="categories-grid">
                <?php if (!empty($allCategories)): ?>
                    <?php foreach ($allCategories as $cat): ?>
                        <div class="category-card">
                            <div class="category-icon">
                                <i class="<?= htmlspecialchars($cat['icon'] ?? 'fas fa-tag') ?>"></i>
                            </div>
                            <div class="category-name">
                                <?= htmlspecialchars($cat['name']) ?>
                            </div>
                            <div class="category-slug">
                                Slug: <?= htmlspecialchars($cat['slug']) ?>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-danger btn-small" 
                                        onclick="deleteCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')"
                                        style="width: 100%;">
                                    <i class="fas fa-trash"></i> Delete Category
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-tags"></i>
                        <h3>No categories found</h3>
                        <p>Add your first category using the button above</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Add Product Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Product</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="modal-body" id="addProductForm" onsubmit="return validateWhatsAppNumber()">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required-field">Product Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required-field">Category</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Short Description</label>
                        <textarea name="short_description" class="form-control" rows="2" 
                                  placeholder="Brief description for product cards (max 100 characters)"></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label required-field">Full Description</label>
                        <textarea name="description" class="form-control" rows="4" required 
                                  placeholder="Detailed product description"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Price (KSh)</label>
                        <input type="number" name="price" class="form-control" step="1" 
                               placeholder="0 for WhatsApp only">
                        <small style="color: #64748b; font-size: 0.875rem;">Enter price in Kenyan Shillings</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required-field">WhatsApp Number</label>
                        <div class="whatsapp-input-group">
                            <span class="input-prefix">
                                <i class="fab fa-whatsapp"></i>
                            </span>
                            <input type="tel" name="whatsapp_number" id="whatsapp_number" 
                                   class="form-control" 
                                   placeholder="254XXXXXXXXX"
                                   pattern="^254[0-9]{9}$"
                                   title="Enter WhatsApp number starting with 254 followed by 9 digits"
                                   required>
                        </div>
                        <div class="error-message" id="whatsapp-error">Please enter a valid WhatsApp number starting with 254</div>
                        <small style="color: #64748b; font-size: 0.875rem;">Enter WhatsApp number with country code (254 for Kenya, e.g., 254712345678)</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required-field">Price Display</label>
                        <select name="price_display" class="form-control" required id="priceDisplaySelect">
                            <option value="whatsapp">WhatsApp Only</option>
                            <option value="price">Price Only</option>
                            <option value="both">Both (Price + WhatsApp)</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label required-field">Product Images</label>
                        <div class="image-upload-container" onclick="document.getElementById('imagesInput').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #94a3b8; margin-bottom: 1rem;"></i>
                            <p>Click to upload images</p>
                            <p style="color: #94a3b8; font-size: 0.875rem; margin-top: 0.5rem;">
                                First image will be primary. Recommended: 800x600px
                            </p>
                        </div>
                        <input type="file" id="imagesInput" name="images[]" 
                               multiple accept="image/*" style="display: none;" 
                               onchange="previewImages(this)" required>
                        <div class="image-preview" id="imagePreview"></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="featured" id="featuredCheckbox" value="1">
                            <label for="featuredCheckbox" class="form-label" style="margin-bottom: 0;">
                                Mark as Featured
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <button type="submit" name="add_product" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Category Modal -->
    <div class="modal" id="addCategoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Category</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" class="modal-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label required-field">Category Name</label>
                        <input type="text" name="name" class="form-control" required 
                               placeholder="e.g., Electronics, Fashion, Vehicles">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Icon</label>
                        <select name="icon" class="form-control">
                            <option value="fas fa-tag">Default Tag</option>
                            <option value="fas fa-mobile-alt">Mobile</option>
                            <option value="fas fa-laptop">Laptop</option>
                            <option value="fas fa-tshirt">Clothing</option>
                            <option value="fas fa-car">Vehicle</option>
                            <option value="fas fa-home">Real Estate</option>
                            <option value="fas fa-gem">Collectibles</option>
                            <option value="fas fa-concierge-bell">Services</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <button type="submit" name="add_category" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                        <i class="fas fa-save"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Product Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Product</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" class="modal-body" id="editForm">
                <!-- Form will be populated by JavaScript -->
            </form>
        </div>
    </div>
    
    <script>
        // Function to get product data
        async function getProductData(productId) {
            try {
                const response = await fetch(`get-product.php?id=${productId}`);
                if (!response.ok) throw new Error('Network response was not ok');
                return await response.json();
            } catch (error) {
                console.error('Error fetching product:', error);
                throw error;
            }
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const tab = btn.dataset.tab;
                    
                    // Update active button
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    
                    // Show active tab
                    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                    document.getElementById(tab + 'Tab').classList.add('active');
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
            
            // Form submission validation
            const addProductForm = document.getElementById('addProductForm');
            if (addProductForm) {
                addProductForm.addEventListener('submit', function(e) {
                    if (!validateWhatsAppNumber()) {
                        e.preventDefault();
                        return false;
                    }
                    return true;
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
        
        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
            document.body.style.overflow = 'auto';
        }
        
        // Image preview
        function previewImages(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-image';
                        img.title = `Image ${index + 1}`;
                        preview.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                });
            }
        }
        
        // Filter products
        function filterProducts() {
            const search = document.getElementById('searchInput').value.trim();
            const category = document.getElementById('categoryFilter').value;
            
            let url = 'black-market-admin.php?';
            const params = [];
            
            if (search) params.push(`search=${encodeURIComponent(search)}`);
            if (category) params.push(`category=${encodeURIComponent(category)}`);
            
            window.location.href = url + params.join('&');
        }
        
        // Edit product
        async function editProduct(productId) {
            try {
                const product = await getProductData(productId);
                
                const form = document.getElementById('editForm');
                form.innerHTML = `
                    <input type="hidden" name="product_id" value="${product.id}">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required-field">Product Name</label>
                            <input type="text" name="name" class="form-control" 
                                   value="${escapeHtml(product.name)}" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field">Category</label>
                            <select name="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['name']) ?>" 
                                            ${product.category === '<?= htmlspecialchars($cat['name']) ?>' ? 'selected' : ''}>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label required-field">Description</label>
                            <textarea name="description" class="form-control" rows="4" required>${escapeHtml(product.description || '')}</textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Price (KSh)</label>
                            <input type="number" name="price" class="form-control" step="1" 
                                   value="${product.price || 0}">
                            <small style="color: #64748b; font-size: 0.875rem;">Price in Kenyan Shillings</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field">WhatsApp Number</label>
                            <div class="whatsapp-input-group">
                                <span class="input-prefix">
                                    <i class="fab fa-whatsapp"></i>
                                </span>
                                <input type="tel" name="whatsapp_number" id="edit_whatsapp_number" 
                                       class="form-control" 
                                       value="${product.whatsapp_number || ''}"
                                       pattern="^254[0-9]{9}$"
                                       title="Enter WhatsApp number starting with 254 followed by 9 digits"
                                       required>
                            </div>
                            <div class="error-message" id="edit_whatsapp-error">Please enter a valid WhatsApp number starting with 254</div>
                            <small style="color: #64748b; font-size: 0.875rem;">Enter WhatsApp number with country code (254 for Kenya)</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Price Display</label>
                            <select name="price_display" class="form-control">
                                <option value="whatsapp" ${product.price_display === 'whatsapp' ? 'selected' : ''}>WhatsApp Only</option>
                                <option value="price" ${product.price_display === 'price' ? 'selected' : ''}>Price Only</option>
                                <option value="both" ${product.price_display === 'both' ? 'selected' : ''}>Both</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="featured" id="editFeaturedCheckbox" value="1" ${product.featured ? 'checked' : ''}>
                                <label for="editFeaturedCheckbox" class="form-label" style="margin-bottom: 0;">
                                    Mark as Featured
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" name="edit_product" class="btn btn-primary" style="width: 100%; padding: 1rem;" onclick="return validateEditWhatsAppNumber()">
                            <i class="fas fa-save"></i> Update Product
                        </button>
                    </div>
                `;
                
                // Add WhatsApp validation for edit form
                const editWhatsappInput = document.getElementById('edit_whatsapp_number');
                const editErrorDiv = document.getElementById('edit_whatsapp-error');
                
                if (editWhatsappInput) {
                    editWhatsappInput.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\D/g, '');
                        
                        // Auto-format for Kenyan numbers
                        if (value.length === 9 && value.startsWith('7')) {
                            value = '254' + value;
                        } else if (value.length === 10 && value.startsWith('07')) {
                            value = '254' + value.substring(1);
                        }
                        
                        e.target.value = value;
                        editErrorDiv.style.display = 'none';
                    });
                }
                
                document.getElementById('editModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load product data. Please try again.');
            }
        }
        
        // WhatsApp validation for edit form
        function validateEditWhatsAppNumber() {
            const editWhatsappInput = document.getElementById('edit_whatsapp_number');
            const editErrorDiv = document.getElementById('edit_whatsapp-error');
            
            if (!editWhatsappInput) return true;
            
            const cleanNumber = editWhatsappInput.value.replace(/\D/g, '');
            
            if (!cleanNumber) {
                editErrorDiv.textContent = "WhatsApp number is required";
                editErrorDiv.style.display = 'block';
                editWhatsappInput.focus();
                return false;
            }
            
            if (!cleanNumber.startsWith('254')) {
                editErrorDiv.textContent = "WhatsApp number must start with 254";
                editErrorDiv.style.display = 'block';
                editWhatsappInput.focus();
                return false;
            }
            
            if (cleanNumber.length !== 12) {
                editErrorDiv.textContent = "WhatsApp number must be 12 digits total";
                editErrorDiv.style.display = 'block';
                editWhatsappInput.focus();
                return false;
            }
            
            return true;
        }
        
        // View product
        function viewProduct(productId) {
            window.open(`../view-product.php?id=${productId}`, '_blank');
        }
        
        // Delete product
        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                window.location.href = `black-market-admin.php?delete=${productId}`;
            }
        }
        
        // Delete category
        function deleteCategory(categoryId, categoryName) {
            if (confirm(`Are you sure you want to delete the category "${categoryName}"? Products in this category won't be deleted but will lose their category.`)) {
                window.location.href = `black-market-admin.php?delete_category=${categoryId}`;
            }
        }
        
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
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>