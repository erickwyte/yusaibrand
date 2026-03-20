<?php
session_start();
require_once 'db.php';

// Check if user is admin (you should implement proper authentication)
// For now, let's check if admin is logged in via session
$isAdmin = isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// If not admin, show access denied
if (!$isAdmin) {
    die("<h2 style='text-align:center; padding:50px; color:#e74c3c;'>Access Denied - Admin Only</h2>");
}

// Define upload directory
$uploadDir = '../uploads/slides_photos/';

// Create upload directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload':
                $title = $_POST['title'] ?? '';
                $description = $_POST['description'] ?? '';
                
                if (empty($title) || empty($description)) {
                    $message = 'Title and description are required';
                    $messageType = 'error';
                } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    $message = 'Please select an image file';
                    $messageType = 'error';
                } else {
                    $file = $_FILES['image'];
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    $fileType = mime_content_type($file['tmp_name']);
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        $message = 'Invalid file type. Only JPG, PNG, GIF, WebP images are allowed.';
                        $messageType = 'error';
                    } elseif ($file['size'] > 5 * 1024 * 1024) {
                        $message = 'File is too large. Maximum size is 5MB.';
                        $messageType = 'error';
                    } else {
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = uniqid() . '_' . time() . '.' . $extension;
                        $uploadPath = $uploadDir . $filename;
                        
                        // Create slides table if it doesn't exist
                        $pdo->exec("
                            CREATE TABLE IF NOT EXISTS slides (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                title VARCHAR(255) NOT NULL,
                                description TEXT NOT NULL,
                                image_filename VARCHAR(255) NOT NULL,
                                is_active BOOLEAN DEFAULT TRUE,
                                sort_order INT DEFAULT 0,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                            )
                        ");
                        
                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            try {
                                $stmt = $pdo->prepare("
                                    INSERT INTO slides (title, description, image_filename, sort_order) 
                                    VALUES (:title, :description, :filename, 
                                    (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM (SELECT sort_order FROM slides) AS temp))
                                ");
                                
                                $stmt->execute([
                                    ':title' => $title,
                                    ':description' => $description,
                                    ':filename' => $filename
                                ]);
                                
                                $message = 'Slide uploaded successfully!';
                                $messageType = 'success';
                                $_POST = [];
                                
                            } catch (PDOException $e) {
                                $message = 'Database error: ' . $e->getMessage();
                                $messageType = 'error';
                                unlink($uploadPath);
                            }
                        } else {
                            $message = 'Failed to upload file. Please check directory permissions.';
                            $messageType = 'error';
                        }
                    }
                }
                break;
                
            case 'delete':
                if (isset($_POST['id'])) {
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("SELECT image_filename FROM slides WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $slide = $stmt->fetch();
                    
                    if ($slide) {
                        $filePath = $uploadDir . $slide['image_filename'];
                        $stmt = $pdo->prepare("DELETE FROM slides WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        
                        $message = 'Slide deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Slide not found.';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'toggle_status':
                if (isset($_POST['id'])) {
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("UPDATE slides SET is_active = NOT is_active WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $message = 'Slide status updated!';
                    $messageType = 'success';
                }
                break;
                
            case 'update_order':
                if (isset($_POST['order']) && is_array($_POST['order'])) {
                    foreach ($_POST['order'] as $position => $id) {
                        $stmt = $pdo->prepare("UPDATE slides SET sort_order = :order WHERE id = :id");
                        $stmt->execute([
                            ':order' => $position + 1,
                            ':id' => (int)$id
                        ]);
                    }
                    $message = 'Slide order updated!';
                    $messageType = 'success';
                }
                break;
        }
    }
}

// Get all slides
$stmt = $pdo->query("SELECT * FROM slides ORDER BY sort_order ASC, created_at DESC");
$slides = $stmt->fetchAll();

// Check if slides table exists
$tableExists = $pdo->query("SHOW TABLES LIKE 'slides'")->rowCount() > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slideshow Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 2.2rem;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .nav-link {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-link:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .nav-link.view {
            background-color: #2ecc71;
        }
        
        .nav-link.view:hover {
            background-color: #27ae60;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .admin-panel {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
        }
        
        .admin-panel h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #34495e;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .upload-area {
            border: 2px dashed #3498db;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            background-color: #f8fafc;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-area:hover, .upload-area.dragover {
            background-color: #e8f4fc;
            border-color: #2980b9;
        }
        
        .upload-area i {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .btn {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        
        .btn i {
            font-size: 1.2rem;
        }
        
        .file-info {
            margin-top: 10px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .slides-list {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .slides-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .slides-list h2 {
            color: #2c3e50;
            margin: 0;
        }
        
        .slides-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .slide-item {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            border: 1px solid #eee;
        }
        
        .slide-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .slide-item img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }
        
        .slide-item-content {
            padding: 20px;
        }
        
        .slide-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .slide-item h4 {
            color: #2c3e50;
            font-size: 1.2rem;
            margin: 0;
            flex: 1;
        }
        
        .order-badge {
            background-color: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .slide-item p {
            color: #7f8c8d;
            font-size: 0.95rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .slide-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 0.85rem;
            color: #95a5a6;
        }
        
        .slide-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 8px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            flex: 1;
            justify-content: center;
        }
        
        .delete-btn {
            background-color: #e74c3c;
            color: white;
        }
        
        .delete-btn:hover {
            background-color: #c0392b;
        }
        
        .toggle-btn {
            background-color: #3498db;
            color: white;
        }
        
        .toggle-btn:hover {
            background-color: #2980b9;
        }
        
        .inactive-badge {
            background-color: #e74c3c;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .active-badge {
            background-color: #2ecc71;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #bdc3c7;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .nav-links {
                width: 100%;
                justify-content: flex-start;
            }
            
            .slides-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .admin-panel, .slides-list {
                padding: 20px;
            }
            
            .slides-list-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .slides-grid {
                grid-template-columns: 1fr;
            }
            
            .slide-actions {
                flex-direction: column;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .nav-link {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Slideshow Admin Panel</h1>
            <div class="nav-links">
               
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="admin-panel">
            <h2>Upload New Slide</h2>
            
            <form id="upload-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                
                <div class="form-group">
                    <label for="slide-title">Slide Title *</label>
                    <input type="text" id="slide-title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                           placeholder="Enter slide title" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="slide-description">Slide Description *</label>
                    <textarea id="slide-description" name="description" class="form-control" 
                              placeholder="Enter slide description" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="slide-image">Slide Image *</label>
                    <div class="upload-area" id="upload-area">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to select an image or drag and drop</p>
                        <p>Maximum file size: 5MB. Allowed formats: JPG, PNG, GIF, WebP</p>
                        <input type="file" id="slide-image" name="image" accept="image/*" hidden required>
                    </div>
                    <div id="file-name" class="file-info"></div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-upload"></i> Upload Slide
                </button>
            </form>
        </div>
        
        <div class="slides-list">
            <div class="slides-list-header">
                <h2>Manage Slides (<?php echo count($slides); ?> total)</h2>
            </div>
            
            <?php if (!$tableExists): ?>
            <div class="empty-state">
                <i class="fas fa-database"></i>
                <h3>Database Setup Required</h3>
                <p>The slides table will be created automatically when you upload your first slide.</p>
            </div>
            
            <?php elseif (count($slides) > 0): ?>
            <div class="slides-container">
                <div class="slides-grid">
                    <?php foreach ($slides as $index => $slide): ?>
                    <div class="slide-item">
                        <img src="../uploads/slides_photos/<?php echo htmlspecialchars($slide['image_filename']); ?>" 
                             alt="<?php echo htmlspecialchars($slide['title']); ?>"
                        >
                        <div class="slide-item-content">
                            <div class="slide-item-header">
                                <h4><?php echo htmlspecialchars($slide['title']); ?></h4>
                                <span class="order-badge">#<?php echo $slide['sort_order']; ?></span>
                            </div>
                            
                            <p><?php echo htmlspecialchars(substr($slide['description'], 0, 100)); 
                                     echo strlen($slide['description']) > 100 ? '...' : ''; ?></p>
                            
                            <div class="slide-meta">
                                <span>ID: <?php echo $slide['id']; ?></span>
                                <span class="<?php echo $slide['is_active'] ? 'active-badge' : 'inactive-badge'; ?>">
                                    <?php echo $slide['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <div class="slide-actions">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                                    <button type="submit" class="action-btn toggle-btn">
                                        <i class="fas fa-toggle-<?php echo $slide['is_active'] ? 'on' : 'off'; ?>"></i>
                                        <?php echo $slide['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" style="flex: 1;" 
                                      onsubmit="return confirm('Are you sure you want to delete this slide?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                                    <button type="submit" class="action-btn delete-btn">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <h3>No Slides Yet</h3>
                <p>Upload your first slide using the form above</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // File upload handling
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('slide-image');
        const fileNameDisplay = document.getElementById('file-name');
        const uploadForm = document.getElementById('upload-form');
        
        // Click to select file
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });
        
        // File input change
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileNameDisplay.textContent = `Selected: ${file.name} (${formatFileSize(file.size)})`;
                
                // Validate file size
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert(`File is too large. Maximum size is ${formatFileSize(maxSize)}.`);
                    fileInput.value = '';
                    fileNameDisplay.textContent = '';
                }
            }
        });
        
        // Drag and drop for file upload
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                const file = e.dataTransfer.files[0];
                fileNameDisplay.textContent = `Selected: ${file.name} (${formatFileSize(file.size)})`;
                
                // Validate file size
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert(`File is too large. Maximum size is ${formatFileSize(maxSize)}.`);
                    fileInput.value = '';
                    fileNameDisplay.textContent = '';
                }
            }
        });
        
        // Form validation
        uploadForm.addEventListener('submit', (e) => {
            const title = document.getElementById('slide-title').value.trim();
            const description = document.getElementById('slide-description').value.trim();
            const file = fileInput.files[0];
            
            if (!title || !description || !file) {
                e.preventDefault();
                alert('Please fill in all required fields and select an image.');
                return false;
            }
            
            // Show loading state
            const submitBtn = uploadForm.querySelector('.btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            submitBtn.disabled = true;
            
            return true;
        });
        
        // Helper function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Auto-hide message after 5 seconds
        const message = document.querySelector('.message');
        if (message) {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 500);
            }, 5000);
        }
    </script>
</body>
</html>