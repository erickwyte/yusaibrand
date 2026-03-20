<?php
session_start();
require_once '../db.php';


// Check if user is logged in
if (!isset($_SESSION['user_id'])) { 
    header('Location: ../log_in.php');
    exit;
}

// Check if logged-in user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../access_denied.php'); // Redirect to access denied page
    exit;
}



// Handle new category creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $newCategory = trim($_POST['new_category']);
    if (!empty($newCategory)) {
        try {
            $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
            $check->execute([$newCategory]);
            if ($check->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO categories (name) VALUES (?)")
                    ->execute([$newCategory]);
                header("Location: admin_products.php?success=" . urlencode("Category added successfully."));
                exit;
            } else {
                header("Location: admin_products.php?error=" . urlencode("Category already exists."));
                exit;
            }
        } catch (PDOException $e) {
            header("Location: admin_products.php?error=" . urlencode("Database error: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: admin_products.php?error=" . urlencode("Category name cannot be empty."));
        exit;
    }
}

// Handle category renaming
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_category'])) {
    $old = trim($_POST['old_category']);
    $new = trim($_POST['new_category_name']);
    if (!empty($old) && !empty($new)) {
        try {
            $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
            $check->execute([$new]);
            if ($check->fetchColumn() == 0) {
                $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE name = ?");
                $stmt->execute([$new, $old]);
                // Update products to use the new category name
                $pdo->prepare("UPDATE products SET type = ? WHERE type = ?")
                    ->execute([$new, $old]);
                header("Location: admin_products.php?success=" . urlencode("Category renamed."));
                exit;
            } else {
                header("Location: admin_products.php?error=" . urlencode("New category name already exists."));
                exit;
            }
        } catch (PDOException $e) {
            header("Location: admin_products.php?error=" . urlencode("Database error: " . $e->getMessage()));
            exit;
        }
    } else {
        header("Location: admin_products.php?error=" . urlencode("Both old and new category names are required."));
        exit;
    }
}

// Handle category deletion
if (isset($_GET['delete_category'])) {
    $category = trim($_GET['delete_category']);
    if (!empty($category)) {
        try {
            // Check if category has products
            $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE type = ?");
            $check->execute([$category]);
            if ($check->fetchColumn() == 0) {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE name = ?");
                $stmt->execute([$category]);
                header("Location: admin_products.php?success=" . urlencode("Category deleted."));
                exit;
            } else {
                header("Location: admin_products.php?error=" . urlencode("Cannot delete category with products."));
                exit;
            }
        } catch (PDOException $e) {
            header("Location: admin_products.php?error=" . urlencode("Database error: " . $e->getMessage()));
            exit;
        }
    }
}

// Handle product creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $type = trim($_POST['type']);
    $price = floatval($_POST['price']);
    $imageName = '';

    if (empty($name) || empty($desc) || empty($type) || $price <= 0) {
        header("Location: admin_products.php?error=" . urlencode("All fields are required and price must be greater than 0."));
        exit;
    }

    try {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image']['tmp_name'];
            $original = basename($_FILES['image']['name']);
            $ext = pathinfo($original, PATHINFO_EXTENSION);
            $imageName = uniqid('prod_', true) . '.' . $ext;
            if (!move_uploaded_file($tmp, '../uploads/products/' . $imageName)) {
                header("Location: admin_products.php?error=" . urlencode("Failed to upload image."));
                exit;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO products (name, description, type, price, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $desc, $type, $price, $imageName]);

        header("Location: admin_products.php?success=" . urlencode("Product added successfully."));
        exit;
    } catch (PDOException $e) {
        header("Location: admin_products.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit;
    }
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $type = trim($_POST['type']);
    $price = floatval($_POST['price']);
    $imageName = $_POST['existing_image'];

    if (empty($name) || empty($desc) || empty($type) || $price <= 0) {
        header("Location: admin_products.php?error=" . urlencode("All fields are required and price must be greater than 0."));
        exit;
    }

    try {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image']['tmp_name'];
            $original = basename($_FILES['image']['name']);
            $ext = pathinfo($original, PATHINFO_EXTENSION);
            $imageName = uniqid('prod_', true) . '.' . $ext;
            if (!move_uploaded_file($tmp, '../uploads/products/' . $imageName)) {
                header("Location: admin_products.php?error=" . urlencode("Failed to upload image."));
                exit;
            }
        }

        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, type = ?, price = ?, image = ? WHERE id = ?");
        $stmt->execute([$name, $desc, $type, $price, $imageName, $id]);

        header("Location: admin_products.php?success=" . urlencode("Product updated successfully."));
        exit;
    } catch (PDOException $e) {
        header("Location: admin_products.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit;
    }
}

// Handle product deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_products.php?success=" . urlencode("Product deleted."));
        exit;
    } catch (PDOException $e) {
        header("Location: admin_products.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit;
    }
}

// Fetch all categories
try {
    // Create categories table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    )");
    
    $allCategories = $pdo->query("SELECT name FROM categories")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    header("Location: admin_products.php?error=" . urlencode("Database error: " . $e->getMessage()));
    exit;
}

// Fetch all products
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$query = "SELECT * FROM products WHERE 1";
$params = [];

if ($search) {
    $query .= " AND name LIKE ?";
    $params[] = "%$search%";
}
if ($category) {
    $query .= " AND type = ?";
    $params[] = $category;
}
$query .= " ORDER BY id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header("Location: admin_products.php?error=" . urlencode("Database error: " . $e->getMessage()));
    exit;
}

// Fetch product to edit
$editProduct = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        header("Location: admin_products.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yusai Admin - Manage Products</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 70px;
            --primary: #007bff;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --dark: #343a40;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f2f5;
            color: #333;
        }
        
       
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            padding: 20px;
            margin-top:60px;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        

        
        .success, .error {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-weight: bold;
        }
        
        .success {
            background: #e6ffed;
            color: #2d6a4f;
        }
        
        .error {
            background: #ffe5e5;
            color: #b02a37;
        }
        
        form {
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        form input, form textarea, form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            transition: border-color 0.3s;
        }
        
        form input:focus, form textarea:focus, form select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        form textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #0069d9;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: var(--warning);
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(0.85rem, 2.5vw, 0.95rem);
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        thead {
            background: var(--primary);
            color: white;
        }
        
        tbody tr:hover {
            background-color: rgba(0,123,255,0.03);
        }
        
        img.thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        
        .action-links {
            display: flex;
            gap: 10px;
        }
        
        .action-links a {
            text-decoration: none;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .action-links a.edit {
            color: var(--primary);
        }
        
        .action-links a.edit:hover {
            background: rgba(0,123,255,0.1);
        }
        
        .action-links a.delete {
            color: var(--danger);
        }
        
        .action-links a.delete:hover {
            background: rgba(220,53,69,0.1);
        }
        
        .search-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .search-filter input, .search-filter select {
            flex: 1;
            min-width: 200px;
        }
        
       
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .category-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .category-item {
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-actions {
            display: flex;
            gap: 8px;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
             .main-content {
                margin-left:0;
                 padding: 0;
                 
            }
           
            
            .mobile-toggle {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .search-filter {
                flex-direction: column;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .category-list {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            h1 {
                font-size: 1.4rem;
            }
            
            h2 {
                font-size: 1.2rem;
            }
            
            form input, form select, form textarea, .btn {
                font-size: 0.9rem;
            }
            
            .action-links {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container">
            <div class="navbar">
                <h1>Manage Products</h1>
                
            </div>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

       
            <!-- Edit Product Form -->
            <?php if ($editProduct): ?>
                <form method="POST" enctype="multipart/form-data">
                    <h2>Edit Product</h2>
                    <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
                    <input type="hidden" name="existing_image" value="<?= $editProduct['image'] ?>">
                    
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($editProduct['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" required><?= htmlspecialchars($editProduct['description']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <select name="type" required>
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $editProduct['type'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($cat)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Price (KSh)</label>
                        <input type="number" step="0.01" name="price" value="<?= $editProduct['price'] ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Product Image</label>
                        <input type="file" name="image" accept="image/*">
                        <?php if ($editProduct['image']): ?>
                            <div style="margin-top: 10px;">
                                <img src="../uploads/products/<?= $editProduct['image'] ?>" alt="Current Image" width="100">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-success" name="edit_product">
                            <i class="fas fa-save"></i> Update Product
                        </button>
                        <a href="admin_products.php" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Category Management Section -->
            <div class="card">
                <h2>Categories</h2>
                
                <!-- Add New Category Form -->
                <form method="POST">
                    <div class="form-group">
                        <label>Add New Category</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" name="new_category" placeholder="New category name..." required>
                            <button type="submit" class="btn btn-primary" name="add_category">
                                <i class="fas fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Rename Category Form -->
                <form method="POST">
                    <div class="form-group">
                        <label>Rename Category</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px;">
                            <select name="old_category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars(ucfirst($cat)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="new_category_name" placeholder="New category name..." required>
                            <button type="submit" class="btn btn-warning" name="rename_category">
                                <i class="fas fa-edit"></i> Rename
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Categories List -->
             <!--   <div class="category-list">
                    <?php if (empty($allCategories)): ?>
                        <p>No categories found.</p>
                    <?php else: ?>
                        <?php foreach ($allCategories as $cat): ?>
                            <div class="category-item">
                                <span><?= htmlspecialchars(ucfirst($cat)) ?></span>
                                <div class="category-actions">
                                    <a href="?delete_category=<?= urlencode($cat) ?>" onclick="return confirm('Delete this category?')" title="Delete">
                                        <i class="fas fa-trash text-danger"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>-->
            </div>

            <!-- Add New Product Form -->
            <form method="POST" enctype="multipart/form-data">
                <h2>Add New Product</h2>
                
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" placeholder="Product name" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Description" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <select name="type" required>
                        <option value="">Select Category</option>
                        <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars(ucfirst($cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Price (KSh)</label>
                    <input type="number" step="0.01" name="price" placeholder="Price" required>
                </div>
                
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                
                <button type="submit" class="btn btn-success" name="add_product">
                    <i class="fas fa-plus-circle"></i> Add Product
                </button>
            </form>

                 <!-- Search and Filter Form -->
            <form method="GET" class="search-filter">
                <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($cat)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </form>


            <!-- Products Table -->
            <div class="card">
                <h2>All Products</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Preview</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="6">No products found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <?php
                                $imgPath = '../uploads/products/' . $p['image'];
                                $image = (!empty($p['image']) && file_exists($imgPath)) ? $imgPath : '../images/default.jpg';
                                ?>
                                <tr>
                                    <td><?= $p['id'] ?></td>
                                    <td><img src="<?= $image ?>" alt="Preview" class="thumb"></td>
                                    <td><?= htmlspecialchars($p['name']) ?></td>
                                    <td><?= htmlspecialchars($p['type']) ?></td>
                                    <td>KSh <?= number_format($p['price'], 2) ?></td>
                                    <td class="action-links">
                                        <a href="?edit=<?= $p['id'] ?>" class="edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?= $p['id'] ?>" class="delete" title="Delete" onclick="return confirm('Delete this product?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.mobile-toggle').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnToggle = toggleBtn.contains(event.target);
            
            if (window.innerWidth <= 992 && !isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>