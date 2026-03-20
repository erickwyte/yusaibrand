<?php
session_start();
require_once 'db.php';


// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $userId = (int)$_POST['delete_user_id'];
    // Prevent admin deletion
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['role'] !== 'admin') {
        try {
            // Delete login history first
            $stmt = $pdo->prepare("DELETE FROM login_history WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            $_SESSION['success'] = "User deleted successfully";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting user: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Cannot delete admin users";
    }
    header("Location: admin_users.php");
    exit;
}

// Pagination variables
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search and filter functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build the base query
$query = "SELECT u.*, COUNT(lh.id) as login_count, MAX(lh.login_time) as last_login 
          FROM users u 
          LEFT JOIN login_history lh ON u.id = lh.user_id";

$where = [];
$params = [];

// Add search conditions
if (!empty($search)) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

// Add filter conditions
if ($filter === 'active') {
    $where[] = "u.email_verified = TRUE";
} elseif ($filter === 'inactive') {
    $where[] = "u.email_verified = FALSE";
} elseif ($filter === 'admin') {
    $where[] = "u.role = 'admin'";
}

// Combine conditions
if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}

// Complete the query
$query .= " GROUP BY u.id ORDER BY last_login DESC LIMIT ? OFFSET ?";
$params = array_merge($params, [$limit, $offset]);

// Get users
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM users u";
if (!empty($where)) {
    $countQuery .= " WHERE " . implode(" AND ", $where);
}
$totalUsers = $pdo->query($countQuery)->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// Get login history for selected user if requested
$loginHistory = [];
if (isset($_GET['view_logins']) && is_numeric($_GET['view_logins'])) {
    $userId = (int)$_GET['view_logins'];
    $stmt = $pdo->prepare("SELECT * FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 50");
    $stmt->execute([$userId]);
    $loginHistory = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 70px auto auto 230px;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .btn-close {
            float: right;
            font-size: 20px;
            font-weight: bold;
            line-height: 1;
            color: #000;
            text-shadow: 0 1px 0 #fff;
            opacity: 0.5;
            background: transparent;
            border: 0;
            cursor: pointer;
        }
        
        h2 {
            color: #444;
            margin-bottom: 20px;
        }
        
        .search-box {
            display: flex;
            margin-right: 10px;
        }
        
        .filter-select {
            width: auto;
            margin-right: 10px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-admin {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-user {
            background-color: #28a745;
            color: white;
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-toggle {
            padding: 5px 10px;
            background: none;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .dropdown-menu {
            position: absolute;
            right: 0;
            z-index: 1000;
            display: none;
            min-width: 160px;
            padding: 5px 0;
            margin: 2px 0 0;
            font-size: 14px;
            text-align: left;
            list-style: none;
            background-color: #fff;
            border: 1px solid rgba(0,0,0,.15);
            border-radius: 4px;
            box-shadow: 0 6px 12px rgba(0,0,0,.175);
        }
        
        .dropdown:hover .dropdown-menu {
            display: block;
        }
        
        .dropdown-item {
            display: block;
            padding: 8px 20px;
            clear: both;
            font-weight: 400;
            color: #333;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            cursor: pointer;
            width: 100%;
            text-align: left;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        .dropdown-divider {
            height: 1px;
            margin: 5px 0;
            overflow: hidden;
            background-color: #e9ecef;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 4px;
        }
        
        .page-item {
            margin: 0 2px;
        }
        
        .page-link {
            position: relative;
            display: block;
            padding: 8px 12px;
            margin-left: -1px;
            line-height: 1.25;
            color: #007bff;
            background-color: #fff;
            border: 1px solid #dee2e6;
            text-decoration: none;
        }
        
        .page-item.active .page-link {
            z-index: 1;
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1050;
        }
        
        .modal-dialog {
            max-width: 800px;
            width: 90%;
        }
        
        .modal-content {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        
        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            margin: 0;
            font-size: 18px;
        }
        
        .modal-body {
            padding: 15px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 15px;
            border-top: 1px solid #e5e5e5;
            display: flex;
            justify-content: flex-end;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin-bottom: 0;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.5;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 4px;
            text-decoration: none;
        }
        
        .btn-primary {
            color: #fff;
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-outline-secondary {
            color: #6c757d;
            background-color: transparent;
            border-color: #6c757d;
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .form-select {
            display: block;
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .d-flex {
            display: flex;
        }
        
        .align-items-center {
            align-items: center;
        }
        
        .me-2 {
            margin-right: 8px;
        }
        
        .ms-2 {
            margin-left: 8px;
        }
        
        .mb-4 {
            margin-bottom: 20px;
        }
        
        .mt-4 {
            margin-top: 20px;
        }
        
        .text-end {
            text-align: right;
        }
        
        .text-muted {
            color: #6c757d;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding-right: 15px;
            padding-left: 15px;
        }
        
        .table-sm th, .table-sm td {
            padding: 8px 10px;
        }
        
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            width: 100vw;
            height: 100vh;
            background-color: #000;
            opacity: 0.5;
        }
        @media (max-width: 768px)
        {
                 .admin-container {
          
            margin: 70px 10px;
            padding: 5px;
        
        }
        /* Add these media queries to your existing CSS */

@media (max-width: 1200px) {
    .admin-container {
        margin-left: 200px;
        padding: 15px 15px 100px;
    }
}

@media (max-width: 992px) {
    .admin-container {
        margin-left: 70px;
    }
    
    .col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
        margin-bottom: 10px;
    }
    
    .search-box {
        margin-right: 0;
        margin-bottom: 10px;
        width: 100%;
    }
    
    .filter-select {
        width: 100%;
        margin-right: 0;
        margin-bottom: 10px;
    }
    
    .d-flex {
        flex-wrap: wrap;
    }
    
    .ms-2 {
        margin-left: 0;
        width: 100%;
    }
}

@media (max-width: 768px) {
    .admin-container {
        margin: 70px 15px 15px 15px;
    }
    
    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    th, td {
        padding: 8px 10px;
        font-size: 14px;
    }
    
    .user-avatar {
        width: 30px;
        height: 30px;
    }
    
    .badge {
        padding: 3px 8px;
        font-size: 11px;
    }
    
    .dropdown-menu {
        min-width: 140px;
    }
    
    .dropdown-item {
        padding: 6px 15px;
        font-size: 13px;
    }
    
    .modal-dialog {
        width: 95%;
    }
}

@media (max-width: 576px) {
    .admin-container {
        margin: 70px 10px 10px 10px;
        padding: 10px 10px 100px 10px;
    }
    
    h2 {
        font-size: 20px;
    }
    
    th, td {
        padding: 6px 8px;
        font-size: 13px;
    }
    
    .pagination {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .page-item {
        margin: 2px;
    }
    
    .modal-title {
        font-size: 16px;
    }
    
    .table-sm th, 
    .table-sm td {
        padding: 5px 8px;
        font-size: 12px;
    }
    
    .btn {
        padding: 6px 12px;
        font-size: 13px;
    }
}

/* Stack table columns on very small screens */
@media (max-width: 480px) {
    table, thead, tbody, th, td, tr {
        display: block;
    }
    
    thead tr {
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
    
    tr {
        margin-bottom: 15px;
        border: 1px solid #ddd;
    }
    
    td {
        border: none;
        border-bottom: 1px solid #eee;
        position: relative;
        padding-left: 40%;
    }
    
    td:before {
        position: absolute;
        top: 6px;
        left: 6px;
        width: 35%;
        padding-right: 10px;
        white-space: nowrap;
        font-weight: bold;
    }
    
    td:nth-of-type(1):before { content: "ID"; }
    td:nth-of-type(2):before { content: "User"; }
    td:nth-of-type(3):before { content: "Email"; }
    td:nth-of-type(4):before { content: "Phone"; }
    td:nth-of-type(5):before { content: "Role"; }
    td:nth-of-type(6):before { content: "Logins"; }
    td:nth-of-type(7):before { content: "Last Login"; }
    td:nth-of-type(8):before { content: "Actions"; }
    
    .dropdown {
        display: block;
        width: 100%;
    }
    
    .dropdown-toggle {
        width: 100%;
        text-align: left;
    }
    
    .dropdown-menu {
        width: 100%;
    }
}

/* Modal adjustments for mobile */
@media (max-width: 400px) {
    .modal-header, 
    .modal-body, 
    .modal-footer {
        padding: 10px;
    }
    
    .modal-title {
        font-size: 15px;
    }
    
    .btn {
        padding: 5px 10px;
    }
}
        
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="admin-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <h2><i class="fas fa-users me-2"></i>User Management</h2>
        
        <!-- Search and Filter Section -->
        <div class="row mb-4">
            <div class="col-md-6">
                <form method="get" class="d-flex">
                    <div class="search-box">
                        <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                    <select name="filter" class="form-select filter-select">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Users</option>
                        <option value="admin" <?= $filter === 'admin' ? 'selected' : '' ?>>Admins Only</option>
                    </select>
                    <button type="submit" class="btn btn-outline-secondary ms-2">Filter</button>
                </form>
            </div>
            <div class="col-md-6 text-end">
                <span class="text-muted">Total Users: <?= number_format($totalUsers) ?></span>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Logins</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                              
                                <span><?= htmlspecialchars($user['name']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= $user['phone'] ? htmlspecialchars($user['phone']) : 'N/A' ?></td>
                        <td>
                            <span class="badge <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td><?= $user['login_count'] ?: 0 ?></td>
                        <td><?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?></td>
                        <td>
                            <div class="dropdown">
                                <button class="dropdown-toggle" type="button" id="actionsDropdown<?= $user['id'] ?>">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="actionsDropdown<?= $user['id'] ?>">
                                    <li><a class="dropdown-item" href="?view_logins=<?= $user['id'] ?>"><i class="fas fa-history me-2"></i>View Logins</a></li>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                            <input type="hidden" name="delete_user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="dropdown-item text-danger"><i class="fas fa-trash-alt me-2"></i>Delete</button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination mt-4">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <!-- Login History Modal -->
        <?php if (!empty($loginHistory)): ?>
        <div class="modal" id="loginHistoryModal" style="display: block;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Login History for User #<?= $_GET['view_logins'] ?></h5>
                        <button type="button" class="btn-close" onclick="window.location.href='admin_users.php'">&times;</button>
                    </div>
                    <div class="modal-body login-history">
                        <table class="table-sm">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>IP Address</th>
                                    <th>Device</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loginHistory as $login): ?>
                                <tr>
                                    <td><?= date('M j, Y g:i A', strtotime($login['login_time'])) ?></td>
                                    <td><?= htmlspecialchars($login['ip_address']) ?></td>
                                    <td><?= htmlspecialchars($login['user_agent']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_users.php'">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop"></div>
        <?php endif; ?>
    </div>

    <script>
        // Close modal when clicking backdrop
        document.querySelector('.modal-backdrop')?.addEventListener('click', function() {
            window.location.href = 'admin_users.php';
        });
    </script>
</body>
</html>