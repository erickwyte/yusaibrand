<?php
session_start();
require_once 'db.php'; // $pdo connection

// Enhanced security checks
if (!isset($_SESSION['user_id'])) { 
    header('Location: ../log_in.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../access_denied.php');
    exit;
}

// Handle form submissions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new admin email
    if (!empty($_POST['new_email'])) {
        $email = trim($_POST['new_email']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_emails WHERE email = ?");
            $stmt->execute([$email]);
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $stmt = $pdo->prepare("INSERT INTO admin_emails (email) VALUES (?)");
                if ($stmt->execute([$email])) {
                    $message = '<div class="success">Admin email added successfully!</div>';
                } else {
                    $message = '<div class="error">Error adding email.</div>';
                }
            } else {
                $message = '<div class="error">This email is already registered as an admin.</div>';
            }
        } else {
            $message = '<div class="error">Please enter a valid email address.</div>';
        }
    }

    // Delete admin email
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare("DELETE FROM admin_emails WHERE id = ?");
        if ($stmt->execute([$deleteId])) {
            $message = '<div class="success">Admin email removed successfully!</div>';
        } else {
            $message = '<div class="error">Error removing email.</div>';
        }
    }
}

// Fetch all admin emails
$emails = $pdo->query("SELECT * FROM admin_emails ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Email Management</title>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
            --border: #dee2e6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fb;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
      
      
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            margin-top:60px;
        }
        
      
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.4rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            font-size: 1rem;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #d1146a;
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        /* Table Styles */
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            text-align: left;
            padding: 15px;
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table tr:hover {
            background-color: rgba(67, 97, 238, 0.03);
        }
        
        /* Status Messages */
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info {
            background: #cce5ff;
            color: #004085;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            
            .main-content {
                margin-left: 70px;
            }
            
           
        }
        
        @media (max-width: 768px) {
      
            .table {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (max-width: 576px) {
        
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            
            .card-body {
                padding: 15px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        
        
  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
           
            
            <!-- Status Messages -->
            <?= $message ?>
            
            <!-- Add Admin Email Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-plus-circle"></i> Add New Admin</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label" for="new_email">Email Address</label>
                            <input type="email" class="form-control" id="new_email" name="new_email" 
                                   placeholder="Enter email address" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add Admin
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Admin Emails List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-envelope"></i> Admin Email List</h3>
                    <span class="badge"><?= count($emails) ?> Admins</span>
                </div>
                <div class="card-body">
                    <?php if (count($emails) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Email Address</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emails as $email): ?>
                                    <tr>
                                        <td><?= $email['id'] ?></td>
                                        <td><?= htmlspecialchars($email['email']) ?></td>
                                        <td><?= date('M d, Y', strtotime($email['created_at'] ?? 'now')) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="delete_id" value="<?= $email['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Are you sure you want to remove this admin email?')">
                                                    <i class="fas fa-trash-alt"></i> Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="info">
                            <i class="fas fa-info-circle"></i> No admin emails found. Please add at least one admin email.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Security Notice -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-shield-alt"></i> Security Notice</h3>
                </div>
                <div class="card-body">
                    <ul>
                        <li>Only users with admin privileges can access this page</li>
                        <li>All admin emails added here will have full access to the admin portal</li>
                        <li>Always verify email addresses before adding them as admins</li>
                        <li>Regularly review and update admin access privileges</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Confirmation for delete actions
        document.querySelectorAll('.btn-danger').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to remove this admin email?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const emailInput = document.getElementById('new_email');
            if (!emailInput.value) {
                alert('Please enter an email address');
                e.preventDefault();
            } else if (!validateEmail(emailInput.value)) {
                alert('Please enter a valid email address');
                e.preventDefault();
            }
        });
        
        function validateEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
    </script>
</body>
</html>