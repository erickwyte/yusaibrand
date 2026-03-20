<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../log_in.php');
    exit;
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Define paths
$publicBaseUrl = 'https://yusaibrand.co.ke/uploads/sell_requests/';
$imageDirServer = $_SERVER['DOCUMENT_ROOT'] . '/uploads/sell_requests/';

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Initialize variables
$submissions = [];
$admin_message = '';
$admin_error = '';
$pendingCount = $approvedCount = $rejectedCount = 0;
$totalCount = 0;

// Handle POST: Status update or delete with PRG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['admin_error'] = "Invalid CSRF token.";
    } else {
        try {
            // Status update
            if (isset($_POST['request_id'], $_POST['new_status'])) {
                $request_id = (int)$_POST['request_id'];
                $new_status = $_POST['new_status'];

                if (in_array($new_status, ['Approved', 'Rejected'])) {
                    $stmt = $pdo->prepare("UPDATE sell_requests SET status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $request_id]);
                    $_SESSION['admin_message'] = "Submission marked as $new_status.";
                }
            }
            // Delete submission
            elseif (isset($_POST['delete'])) {
                $request_id = (int)$_POST['delete'];

                // Fetch images to delete
                $stmt = $pdo->prepare("SELECT image1, image2, image3 FROM sell_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    foreach (['image1', 'image2', 'image3'] as $field) {
                        if (!empty($row[$field])) {
                            $filename = basename($row[$field]);
                            $filePath = $imageDirServer . $filename;
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        }
                    }
                }

                // Delete record
                $stmt = $pdo->prepare("DELETE FROM sell_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $_SESSION['admin_message'] = "Submission deleted successfully!";
            }
            // Regenerate CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (PDOException $e) {
            $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?page=" . $page);
    exit;
}

// Fetch submissions with pagination
try {
    // Get total count
    $stmt = $pdo->query("SELECT COUNT(*) FROM sell_requests");
    $totalCount = $stmt->fetchColumn();
    $totalPages = ceil($totalCount / $perPage);

    // Get paginated submissions
    $stmt = $pdo->prepare("SELECT * FROM sell_requests ORDER BY submitted_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Log fetched submissions
    error_log("Fetched submissions: " . count($submissions));

    // Ensure unique submissions by ID
    $uniqueSubmissions = [];
    $seenIds = [];
    foreach ($submissions as $submission) {
        if (!in_array($submission['id'], $seenIds)) {
            $seenIds[] = $submission['id'];
            $uniqueSubmissions[] = $submission;
            error_log("Processing submission ID: " . $submission['id']);
        }
    }
    $submissions = $uniqueSubmissions;

    // Update image paths for web display
    foreach ($submissions as &$submission) {
        foreach (['image1', 'image2', 'image3'] as $field) {
            if (!empty($submission[$field])) {
                $filename = basename($submission[$field]);
                $submission[$field] = $publicBaseUrl . $filename;
            }
        }
    }

    // Count statuses
    $statusStmt = $pdo->query("SELECT status, COUNT(*) as count FROM sell_requests GROUP BY status");
    $statusCounts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $pendingCount = $statusCounts['Pending'] ?? 0;
    $approvedCount = $statusCounts['Approved'] ?? 0;
    $rejectedCount = $statusCounts['Rejected'] ?? 0;
} catch (PDOException $e) {
    $admin_error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Admin Dashboard - Product Submissions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
       <style>
        :root {
            --sidebar-width: 220px;
            --header-height: 60px;
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --danger-color: #e63946;
            --light-bg: #f8f9fa;
            --dark-text: #212529;
            --border-color: #dee2e6;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin-top:60px;
        }
        
        .header {
            background-color: white;
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-left {
            display: flex;
            align-items: center;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: var(--dark-text);
            font-size: 1.2rem;
            cursor: pointer;
            margin-right: 15px;
            display: none;
        }
        
        .header-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-text);
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-left: 15px;
        }
        
        /* Content Styles */
        .content {
            flex: 1;
            padding: 25px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-info h3 {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .stat-info .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-text);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-card:nth-child(1) .stat-icon {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
        }
        
        .stat-card:nth-child(2) .stat-icon {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--warning-color);
        }
        
        .stat-card:nth-child(3) .stat-icon {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success-color);
        }
        
        .stat-card:nth-child(4) .stat-icon {
            background-color: rgba(230, 57, 70, 0.1);
            color: var(--danger-color);
        }
        
        /* Submissions Section */
        .submissions-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-header h3 {
            font-size: 1.1rem;
            color: var(--dark-text);
            font-weight: 600;
        }
        
        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border-radius: 20px;
            background-color: var(--light-bg);
            color: #6c757d;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }
        
        .filter-btn.active, .filter-btn:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: var(--light-bg);
            border-radius: 20px;
            padding: 5px 15px;
            border: 1px solid var(--border-color);
        }
        
        .search-box input {
            border: none;
            background: transparent;
            padding: 5px;
            outline: none;
            width: 200px;
        }
        
        .search-box i {
            color: #6c757d;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            padding: 0 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        thead th {
            background-color: var(--light-bg);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #6c757d;
            font-size: 0.85rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s;
        }
        
        tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.03);
        }
        
        tbody td {
            padding: 15px;
            font-size: 0.9rem;
            color: var(--dark-text);
        }
        
        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .product-img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .product-category {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ff9800;
        }
        
        .status-approved {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }
        
        .status-rejected {
            background-color: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-view {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196f3;
        }
        
        .action-approve {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4caf50;
        }
        
        .action-reject {
            background-color: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .action-delete {
            background-color: rgba(158, 158, 158, 0.1);
            color: #9e9e9e;
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
        
        .action-view:hover {
            background-color: #2196f3;
            color: white;
        }
        
        .action-approve:hover {
            background-color: #4caf50;
            color: white;
        }
        
        .action-reject:hover {
            background-color: #f44336;
            color: white;
        }
        
        .action-delete:hover {
            background-color: #9e9e9e;
            color: white;
        }
        
        /* Pagination */
        .pagination {
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            border-top: 1px solid var(--border-color);
        }
        
        .pagination-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--light-bg);
            color: var(--dark-text);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pagination-btn.active, .pagination-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark-text);
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .submission-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .detail-group {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1rem;
            color: var(--dark-text);
            font-weight: 500;
        }
        
        .product-images {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .product-img-lg {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .product-img-lg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .description-box {
            background-color: var(--light-bg);
            border-radius: 8px;
            padding: 15px;
            margin-top: 5px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            
           
            .toggle-sidebar {
                display: block;
            }
            
            .submission-details {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters {
                width: 100%;
                justify-content: center;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .header {
                padding: 0 15px;
            }
            
            .content {
                padding: 15px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
    
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        tbody tr {
            display: none; /* Initially hide all rows */
        }
        tbody tr.visible {
            display: table-row; /* Show only rows marked visible */
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="content">
            <?php if (isset($_SESSION['admin_message'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['admin_message']); unset($_SESSION['admin_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['admin_error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?></div>
            <?php endif; ?>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Submissions</h3>
                        <div class="value"><?php echo $totalCount; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Pending Review</h3>
                        <div class="value"><?php echo $pendingCount; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Approved</h3>
                        <div class="value"><?php echo $approvedCount; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Rejected</h3>
                        <div class="value"><?php echo $rejectedCount; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>

            <div class="submissions-section">
                <div class="section-header">
                    <h3>Sell Requests</h3>
                    <div class="filters">
                        <div class="filter-btn active" data-filter="all">All</div>
                        <div class="filter-btn" data-filter="Pending">Pending</div>
                        <div class="filter-btn" data-filter="Approved">Approved</div>
                        <div class="filter-btn" data-filter="Rejected">Rejected</div>
                    </div>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search products...">
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Submitted At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="submissionsTable">
                            <?php foreach ($submissions as $submission): ?>
                            <tr data-status="<?php echo htmlspecialchars($submission['status']); ?>" data-id="<?php echo $submission['id']; ?>">
                                <td>
                                    <div class="product-info">
                                        <?php if ($submission['image1']): ?>
                                        <div class="product-img">
                                            <img src="<?php echo htmlspecialchars($submission['image1']); ?>" alt="Product">
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="product-name"><?php echo htmlspecialchars($submission['product_name']); ?></div>
                                            <div class="product-category"><?php echo htmlspecialchars($submission['product_category']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($submission['product_category']); ?></td>
                                <td>KSH <?php echo number_format($submission['product_price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($submission['contact_email']); ?></td>
                                <td>
                                    <span class="status status-<?php echo strtolower($submission['status']); ?>">
                                        <?php echo htmlspecialchars($submission['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <div class="action-btn action-view" title="View Details" data-id="<?php echo $submission['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </div>
                                        <div class="action-btn action-approve" title="Approve" data-id="<?php echo $submission['id']; ?>">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="action-btn action-reject" title="Reject" data-id="<?php echo $submission['id']; ?>">
                                            <i class="fas fa-times"></i>
                                        </div>
                                        <form method="POST" class="delete-form" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="delete" value="<?php echo $submission['id']; ?>">
                                            <button type="submit" class="action-btn action-delete" title="Delete"
                                                    onclick="return confirm('Are you sure you want to delete this submission?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">
                            <div class="pagination-btn">
                                <i class="fas fa-chevron-left"></i>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="pagination-btn disabled">
                            <i class="fas fa-chevron-left"></i>
                        </div>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>">
                            <div class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </div>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">
                            <div class="pagination-btn">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="pagination-btn disabled">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal" id="submissionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Product Submission Details</h3>
                <button class="close-btn">&times;</button>
            </div>
            <div class="modal-body" id="modalContent">
                <p>Loading...</p>
            </div>
        </div>
    </div>

    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="request_id" id="statusFormId">
        <input type="hidden" name="new_status" id="statusFormStatus">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('submissionModal');
            const modalContent = document.getElementById('modalContent');
            const statusForm = document.getElementById('statusForm');
            const statusFormId = document.getElementById('statusFormId');
            const statusFormStatus = document.getElementById('statusFormStatus');
            const tableBody = document.getElementById('submissionsTable');

            // Function to update table visibility
            function updateTableVisibility(filter = 'all', searchTerm = '') {
                const rows = tableBody.querySelectorAll('tr');
                const seenIds = new Set();
                console.log('Updating table, rows found:', rows.length);

                rows.forEach(row => {
                    const status = row.getAttribute('data-status');
                    const id = row.getAttribute('data-id');
                    const productName = row.querySelector('.product-name').textContent.toLowerCase();
                    const productCategory = row.querySelector('.product-category').textContent.toLowerCase();

                    // Prevent duplicate rendering
                    if (seenIds.has(id)) {
                        console.log('Duplicate ID detected:', id);
                        row.remove();
                        return;
                    }
                    seenIds.add(id);

                    // Apply filter and search
                    const matchesFilter = filter === 'all' || status === filter;
                    const matchesSearch = !searchTerm || productName.includes(searchTerm) || productCategory.includes(searchTerm);
                    row.classList.toggle('visible', matchesFilter && matchesSearch);
                });
            }

            // Initial table update
            updateTableVisibility();

            // View details
            document.querySelectorAll('.action-view').forEach(button => {
                button.addEventListener('click', function() {
                    const submissionId = this.getAttribute('data-id');
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    modalContent.innerHTML = '<p>Loading...</p>';

                    fetch(`get_submission_details.php?id=${submissionId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.error) {
                                modalContent.innerHTML = `<p>${data.error}</p>`;
                                return;
                            }
                            const imageHTML = [data.image1, data.image2, data.image3]
                                .filter(img => img)
                                .map(img => `<div class="product-img-lg"><img src="${img}" alt="Product"></div>`)
                                .join('') || '<p>No images provided.</p>';

                            modalContent.innerHTML = `
                                <div class="submission-details">
                                    <div>
                                        <div class="detail-group"><div class="detail-label">Product Name</div><div class="detail-value">${data.product_name}</div></div>
                                        <div class="detail-group"><div class="detail-label">Category</div><div class="detail-value">${data.product_category}</div></div>
                                        <div class="detail-group"><div class="detail-label">Price</div><div class="detail-value">KSH ${parseFloat(data.product_price).toFixed(2)}</div></div>
                                        <div class="detail-group"><div class="detail-label">Email</div><div class="detail-value">${data.contact_email}</div></div>
                                        <div class="detail-group"><div class="detail-label">Phone</div><div class="detail-value">${data.contact_phone || 'N/A'}</div></div>
                                        <div class="detail-group"><div class="detail-label">Status</div><div class="detail-value">${data.status}</div></div>
                                    </div>
                                    <div>
                                        <div class="detail-group">
                                            <div class="detail-label">Images</div>
                                            <div class="product-images">${imageHTML}</div>
                                        </div>
                                        <div class="detail-group"><div class="detail-label">Description</div><div class="description-box">${data.product_description}</div></div>
                                        <div class="detail-group"><div class="detail-label">Seller Note</div><div class="description-box">${data.seller_note || 'N/A'}</div></div>
                                    </div>
                                </div>
                            `;
                        })
                        .catch(err => {
                            modalContent.innerHTML = '<p>Error loading data.</p>';
                            console.error('Fetch error:', err);
                        });
                });
            });

            // Close modal
            document.querySelector('.close-btn').addEventListener('click', () => {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            });

            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // Status change
            function handleStatusChange(buttonClass, newStatus) {
                document.querySelectorAll(buttonClass).forEach(btn => {
                    btn.addEventListener('click', function() {
                        const requestId = this.getAttribute('data-id');
                        if (!requestId) return;
                        if (confirm(`Are you sure you want to mark this as ${newStatus}?`)) {
                            statusFormId.value = requestId;
                            statusFormStatus.value = newStatus;
                            statusForm.submit();
                        }
                    });
                });
            }

            handleStatusChange('.action-approve', 'Approved');
            handleStatusChange('.action-reject', 'Rejected');

            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const selectedStatus = this.getAttribute('data-filter');
                    updateTableVisibility(selectedStatus, document.getElementById('searchInput').value.toLowerCase());
                });
            });

            // Search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', function() {
                const activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
                updateTableVisibility(activeFilter, this.value.toLowerCase());
            });
        });
    </script>
</body>
</html>