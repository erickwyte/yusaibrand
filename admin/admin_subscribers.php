<?php
// admin_subscribers.php
session_start();
require_once 'db.php';


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


// Handle delete action
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    
    // Prepare and execute delete statement
    $deleteStmt = $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id = :id");
    $deleteStmt->bindParam(':id', $deleteId, PDO::PARAM_INT);
    $deleteStmt->execute();
    
    // Redirect to prevent refresh issues
    header('Location: admin_subscribers.php');
    exit;
}


// Pagination variables
$perPage = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Get total count for pagination
$totalStmt = $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers");
$totalSubscribers = $totalStmt->fetchColumn();

// Fetch subscribers with pagination
$stmt = $pdo->prepare("SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$subscribers = $stmt->fetchAll();

// Calculate total pages
$totalPages = ceil($totalSubscribers / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Subscribers | Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c7be5;
            --primary-dark: #1c65c7;
            --secondary: #00d97e;
            --dark: #12263f;
            --light: #f9fbfd;
            --gray: #95aac9;
            --danger: #e44d26;
            --border-radius: 8px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 250px; /* Match sidebar width */
            margin-top:70px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .page-title {
            font-size: 1.8rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title i {
            color: var(--primary);
        }

        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(44, 123, 229, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .stats-content h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .stats-content p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }

        .data-table thead {
            background: var(--primary);
            color: white;
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 500;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-new {
            background: rgba(0, 217, 126, 0.1);
            color: var(--secondary);
        }

        .newsletter-form {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--box-shadow);
            margin-top: 2rem;
        }

        .form-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-title i {
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.2);
        }

        textarea.form-control {
            min-height: 200px;
            resize: vertical;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #d33a1e;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 2rem;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            background: white;
            color: var(--dark);
            text-decoration: none;
            border: 1px solid #e2e8f0;
            transition: var(--transition);
        }

        .page-link:hover {
            background: #f8fafc;
        }

        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Responsive styles */
        @media (max-width: 992px) {
            .main-content {
                
                padding: 1rem;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .stats-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-envelope"></i>
                    Newsletter Subscribers
                </h1>
                <div class="actions">
                    <button class="btn btn-primary" onclick="document.getElementById('newsletterForm').scrollIntoView()">
                        <i class="fas fa-paper-plane"></i> Send Newsletter
                    </button>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                    <h3>Total Subscribers</h3>
                    <p><?= number_format($totalSubscribers) ?> active subscribers</p>
                </div>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Subscription Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($subscribers) > 0): ?>
                        <?php foreach ($subscribers as $subscriber): 
                            $isNew = strtotime($subscriber['subscribed_at']) > strtotime('-7 days');
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($subscriber['email']) ?></td>
                                <td><?= date('M j, Y g:i a', strtotime($subscriber['subscribed_at'])) ?></td>
                                <td>
                                    <?php if ($isNew): ?>
                                        <span class="badge badge-new">New</span>
                                    <?php else: ?>
                                        Active
                                    <?php endif; ?>
                                </td>
                              
<td>
    <a href="admin_subscribers.php?delete_id=<?= $subscriber['id'] ?>" 
       class="btn btn-danger btn-sm"
       onclick="return confirm('Are you sure you want to remove <?= htmlspecialchars($subscriber['email']) ?>?')">
        <i class="fas fa-trash-alt"></i> Remove
    </a>
</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No subscribers found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item">
                            <a class="page-link <?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
            
            <div class="newsletter-form" id="newsletterForm">
                <h2 class="form-title">
                    <i class="fas fa-paper-plane"></i>
                    Send Newsletter Email
                </h2>
                
                <form method="post" action="send_newsletter.php">
                    <div class="form-group">
                        <label for="subject">Email Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" placeholder="Important news from YUSAI Brand" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Email Content</label>
                        <textarea id="message" name="message" class="form-control" placeholder="Write your newsletter content here..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send to All Subscribers
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
 
function confirmDelete(email, id) {
    if (confirm(`Are you sure you want to remove ${email} from subscribers?`)) {
        // Create a form to submit the delete request
        const form = document.createElement('form');
        form.method = 'GET';
        form.action = 'admin_subscribers.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_id';
        input.value = id;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
        
        // Auto-expand textarea as user types
        document.getElementById('message').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    </script>
</body>
</html>