<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a regular admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../log_in.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_admin'])) {
        // Add new black market admin
        $user_id = $_POST['user_id'];
        $access_level = $_POST['access_level'];
        $can_manage_products = isset($_POST['can_manage_products']) ? 1 : 0;
        $can_manage_categories = isset($_POST['can_manage_categories']) ? 1 : 0;
        $can_manage_orders = isset($_POST['can_manage_orders']) ? 1 : 0;
        $can_view_analytics = isset($_POST['can_view_analytics']) ? 1 : 0;
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update user's black market admin status
            $stmt = $pdo->prepare("UPDATE users SET is_black_market_admin = TRUE WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Insert into black_market_admins table
            $stmt = $pdo->prepare("INSERT INTO black_market_admins 
                (user_id, access_level, can_manage_products, can_manage_categories, 
                 can_manage_orders, can_view_analytics) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id, 
                $access_level, 
                $can_manage_products, 
                $can_manage_categories,
                $can_manage_orders,
                $can_view_analytics
            ]);
            
            $pdo->commit();
            $success = "Black Market Admin added successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error adding admin: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_admin'])) {
        // Update admin permissions
        $admin_id = $_POST['admin_id'];
        $access_level = $_POST['access_level'];
        $can_manage_products = isset($_POST['can_manage_products']) ? 1 : 0;
        $can_manage_categories = isset($_POST['can_manage_categories']) ? 1 : 0;
        $can_manage_orders = isset($_POST['can_manage_orders']) ? 1 : 0;
        $can_view_analytics = isset($_POST['can_view_analytics']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE black_market_admins 
            SET access_level = ?, can_manage_products = ?, can_manage_categories = ?,
                can_manage_orders = ?, can_view_analytics = ?, is_active = ?
            WHERE id = ?");
        $stmt->execute([
            $access_level, $can_manage_products, $can_manage_categories,
            $can_manage_orders, $can_view_analytics, $is_active, $admin_id
        ]);
        $success = "Admin permissions updated successfully!";
    } elseif (isset($_POST['delete_admin'])) {
        // Delete admin
        $admin_id = $_POST['admin_id'];
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Get user_id first
            $stmt = $pdo->prepare("SELECT user_id FROM black_market_admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $user_id = $stmt->fetchColumn();
            
            // Delete from black_market_admins
            $stmt = $pdo->prepare("DELETE FROM black_market_admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            
            // Update user's black market admin status
            $stmt = $pdo->prepare("UPDATE users SET is_black_market_admin = FALSE WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            $success = "Black Market Admin removed successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error removing admin: " . $e->getMessage();
        }
    }
}

// Fetch all black market admins
$stmt = $pdo->query("
    SELECT bma.*, u.name, u.email, u.phone 
    FROM black_market_admins bma 
    JOIN users u ON bma.user_id = u.id 
    ORDER BY bma.created_at DESC
");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users for dropdown
$stmt = $pdo->query("SELECT id, name, email FROM users WHERE is_black_market_admin = FALSE ORDER BY name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Black Market Admins</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #666;
            font-size: 1.1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            margin-bottom: 1.5rem;
            color: #333;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: auto;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .access-level {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .access-full {
            background: #cce5ff;
            color: #004085;
        }
        
        .access-limited {
            background: #fff3cd;
            color: #856404;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Manage Black Market Admins</h1>
            <p class="subtitle">Add, edit, or remove administrators for the Black Market section</p>
        </header>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="content-grid">
            <!-- Add Admin Form -->
            <div class="card">
                <h2>Add New Black Market Admin</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="user_id">Select User</label>
                        <select id="user_id" name="user_id" class="form-control" required>
                            <option value="">-- Select a user --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="access_level">Access Level</label>
                        <select id="access_level" name="access_level" class="form-control" required>
                            <option value="limited">Limited Access</option>
                            <option value="full">Full Access</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Permissions</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="can_manage_products" name="can_manage_products" checked>
                                <label for="can_manage_products">Manage Products</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="can_manage_categories" name="can_manage_categories" checked>
                                <label for="can_manage_categories">Manage Categories</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="can_manage_orders" name="can_manage_orders">
                                <label for="can_manage_orders">Manage Orders</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="can_view_analytics" name="can_view_analytics">
                                <label for="can_view_analytics">View Analytics</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                </form>
            </div>
            
            <!-- Admins List -->
            <div class="card">
                <h2>Current Black Market Admins</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Access Level</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($admin['name']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td>
                                        <span class="access-level access-<?php echo $admin['access_level']; ?>">
                                            <?php echo ucfirst($admin['access_level']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $admin['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    onclick="editAdmin(<?php echo htmlspecialchars(json_encode($admin)); ?>)">
                                                Edit
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                <button type="submit" name="delete_admin" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Are you sure you want to remove this admin?')">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Edit Modal (hidden by default) -->
        <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
             background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div class="card" style="width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto;">
                <h2>Edit Admin Permissions</h2>
                <form method="POST" id="editForm">
                    <input type="hidden" name="admin_id" id="edit_admin_id">
                    
                    <div class="form-group">
                        <label for="edit_access_level">Access Level</label>
                        <select id="edit_access_level" name="access_level" class="form-control" required>
                            <option value="limited">Limited Access</option>
                            <option value="full">Full Access</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Permissions</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="edit_can_manage_products" name="can_manage_products">
                                <label for="edit_can_manage_products">Manage Products</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="edit_can_manage_categories" name="can_manage_categories">
                                <label for="edit_can_manage_categories">Manage Categories</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="edit_can_manage_orders" name="can_manage_orders">
                                <label for="edit_can_manage_orders">Manage Orders</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="edit_can_view_analytics" name="can_view_analytics">
                                <label for="edit_can_view_analytics">View Analytics</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_is_active">Status</label>
                        <select id="edit_is_active" name="is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" name="update_admin" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn" onclick="closeModal()" style="background: #6c757d; color: white;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function editAdmin(admin) {
            // Fill form with admin data
            document.getElementById('edit_admin_id').value = admin.id;
            document.getElementById('edit_access_level').value = admin.access_level;
            document.getElementById('edit_can_manage_products').checked = admin.can_manage_products == 1;
            document.getElementById('edit_can_manage_categories').checked = admin.can_manage_categories == 1;
            document.getElementById('edit_can_manage_orders').checked = admin.can_manage_orders == 1;
            document.getElementById('edit_can_view_analytics').checked = admin.can_view_analytics == 1;
            document.getElementById('edit_is_active').value = admin.is_active ? '1' : '0';
            
            // Show modal
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>