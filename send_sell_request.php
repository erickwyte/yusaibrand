<?php
session_start();
require_once 'db.php'; // Your PDO connection

// Enforce HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/sell_requests/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$publicBaseUrl = 'https://yusaibrand.co.ke/uploads/sell_requests/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Security token mismatch. Please try again.";
        header('Location: send_sell_request.php');
        exit;
    }

    // Honeypot detection
    if (!empty($_POST['website'])) {
        $_SESSION['error'] = "Invalid request detected.";
        header('Location: send_sell_request.php');
        exit;
    }

    // Validate CAPTCHA
    if (!isset($_POST['captcha']) || (int)$_POST['captcha'] !== $_SESSION['captcha_answer']) {
        $_SESSION['error'] = "Incorrect security answer.";
        header('Location: send_sell_request.php');
        exit;
    }

    // Sanitize and validate inputs
    $productName = htmlspecialchars(trim($_POST['productName']));
    $productDescription = htmlspecialchars(trim($_POST['productDescription']));
    $productCategory = htmlspecialchars(trim($_POST['productCategory']));
    $productPrice = floatval($_POST['productPrice']);
    $contactEmail = filter_var($_POST['contactEmail'], FILTER_SANITIZE_EMAIL);
    $contactPhone = htmlspecialchars(trim($_POST['contactPhone'] ?? ''));
    $sellerNote = htmlspecialchars(trim($_POST['sellerNote'] ?? ''));

    // Validate required fields
    if (!$productName || !$productDescription || !$productCategory || !$productPrice || !$contactEmail || !$contactPhone) {
        $_SESSION['error'] = "Please fill all required fields.";
        header('Location: send_sell_request.php');
        exit;
    }

    // Validate email
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header('Location: send_sell_request.php');
        exit;
    }

    // Validate price
    if ($productPrice <= 0) {
        $_SESSION['error'] = "Please enter a valid product price.";
        header('Location: send_sell_request.php');
        exit;
    }

    // Check for duplicate submission
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sell_requests WHERE product_name = :productName AND contact_email = :contactEmail AND submitted_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $stmt->execute([':productName' => $productName, ':contactEmail' => $contactEmail]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = "This submission appears to be a duplicate. Please try again later.";
            header('Location: send_sell_request.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: send_sell_request.php');
        exit;
    }

    // Handle file uploads
    $uploadedImages = [];
    if (isset($_FILES['productImage']) && !empty($_FILES['productImage']['name'][0])) {
        $fileNames = $_FILES['productImage']['name'];
        $fileTypes = $_FILES['productImage']['type'];
        $fileTmpNames = $_FILES['productImage']['tmp_name'];
        $fileErrors = $_FILES['productImage']['error'];
        $fileSizes = $_FILES['productImage']['size'];

        for ($i = 0; $i < count($fileNames); $i++) {
            if ($fileErrors[$i] === UPLOAD_ERR_OK) {
                // Verify file type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedType = finfo_file($finfo, $fileTmpNames[$i]);
                finfo_close($finfo);

                if (!in_array($detectedType, $allowedTypes)) {
                    $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                    header('Location: send_sell_request.php');
                    exit;
                }

                if ($fileSizes[$i] > $maxFileSize) {
                    $_SESSION['error'] = "File size exceeds 5MB limit.";
                    header('Location: send_sell_request.php');
                    exit;
                }

                // Generate secure filename
                $extension = pathinfo($fileNames[$i], PATHINFO_EXTENSION);
                $uniqueName = bin2hex(random_bytes(16)) . '.' . $extension;
                $uploadPath = $uploadDir . $uniqueName;

                if (move_uploaded_file($fileTmpNames[$i], $uploadPath)) {
                    $uploadedImages[] = '/uploads/sell_requests/' . $uniqueName;
                } else {
                    $_SESSION['error'] = "Error uploading file. Please try again.";
                    header('Location: send_sell_request.php');
                    exit;
                }
            } elseif ($fileErrors[$i] !== UPLOAD_ERR_NO_FILE) {
                $_SESSION['error'] = "File upload error: " . $fileErrors[$i];
                header('Location: send_sell_request.php');
                exit;
            }
        }
    }

    $image1 = $uploadedImages[0] ?? null;
    $image2 = $uploadedImages[1] ?? null;
    $image3 = $uploadedImages[2] ?? null;

    $submittedAt = date('Y-m-d H:i:s');

    // Insert into database
    $sql = "INSERT INTO sell_requests (
        product_name, product_description, product_category, product_price,
        contact_email, contact_phone, seller_note, image1, image2, image3, submitted_at, status
    ) VALUES (
        :productName, :productDescription, :productCategory, :productPrice,
        :contactEmail, :contactPhone, :sellerNote, :image1, :image2, :image3, :submittedAt, 'Pending'
    )";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':productName' => $productName,
            ':productDescription' => $productDescription,
            ':productCategory' => $productCategory,
            ':productPrice' => $productPrice,
            ':contactEmail' => $contactEmail,
            ':contactPhone' => $contactPhone,
            ':sellerNote' => $sellerNote,
            ':image1' => $image1,
            ':image2' => $image2,
            ':image3' => $image3,
            ':submittedAt' => $submittedAt
        ]);

        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['success'] = "Product submission successful! Our team will review your request shortly.";
        header('Location: send_sell_request.php');
        exit;
    } catch (PDOException $e) {
        // Clean up uploaded files on failure
        foreach ($uploadedImages as $image) {
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . $image)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . $image);
            }
        }
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: send_sell_request.php');
        exit;
    }
} else {
    // Generate CAPTCHA for GET requests
    $num1 = rand(5, 10);
    $num2 = rand(1, 4);
    $_SESSION['captcha_answer'] = $num1 + $num2;
    $_SESSION['captcha_question'] = "What is $num1 + $num2?";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Your Product - Sell With Us</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/send-sell-request.css">
    <style>
        @media (max-width: 800px) {
            .sells-header {
                display: block;
            }
            .sells-header h1 {
                font-size: 1.5rem;
            }
        }
        .submit-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php include 'include/header.php'; ?>
    <div class="empty"></div>
    
    <div class="container">
        <div class="sells-header">
            <h1>Sell Your Products With Us</h1>
            <p>Submit your product details and our team will review your request</p>
        </div>
        
        <div class="form-container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form id="productSubmissionForm" action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div style="display: none;">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website">
                </div>

                <div class="form-group">
                    <label for="productName" class="required">Product Name</label>
                    <input type="text" id="productName" name="productName" required placeholder="Enter your product name">
                </div>
                
                <div class="form-group">
                    <label for="productDescription" class="required">Product Description</label>
                    <textarea id="productDescription" name="productDescription" rows="4" required placeholder="Describe your product features, benefits, and specifications"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="productCategory" class="required">Product Category</label>
                    <select id="productCategory" name="productCategory" required>
                        <option value="">Select a category</option>
                        <option value="electronics">Electronics</option>
                        <option value="clothing">Clothing & Accessories</option>
                        <option value="home">Home & Kitchen</option>
                        <option value="beauty">Beauty & Health</option>
                        <option value="books">Books & Media</option>
                        <option value="toys">Toys & Games</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="productPrice" class="required">Price (KSH)</label>
                    <input type="number" id="productPrice" name="productPrice" min="0.01" step="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Product Images <span class="optional">(Optional - max 3 images)</span></label>
                    <div class="image-upload" id="imageUploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload or drag & drop</p>
                        <p class="optional">JPG, PNG, GIF up to 5MB</p>
                        <input type="file" id="productImage" name="productImage[]" accept="image/*" multiple style="display: none;">
                    </div>
                    <div id="imagePreview"></div>
                </div>
                
                <div class="form-group">
                    <label for="contactEmail" class="required">Contact Email</label>
                    <input type="email" id="contactEmail" name="contactEmail" required placeholder="your.email@example.com">
                </div>
                
                <div class="form-group">
                    <label for="contactPhone" class="required">Phone Number</label>
                    <input type="tel" id="contactPhone" name="contactPhone" required placeholder="0712345678">
                </div>
                
                <div class="form-group">
                    <label for="sellerNote">Note or Question <span class="optional">(Optional)</span></label>
                    <textarea id="sellerNote" name="sellerNote" rows="3" placeholder="Any additional information or questions for our team"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="captcha" class="required">Security Check</label>
                    <p><?php echo htmlspecialchars($_SESSION['captcha_question']); ?></p>
                    <input type="number" id="captcha" name="captcha" required>
                </div>
                
                <div class="form-note">
                    <p><i class="fas fa-info-circle"></i> After submitting this form, our team will review your product details and contact you within 3 business days to discuss next steps.</p>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">Submit Product Request</button>
            </form>
        </div>
        
        <footer>
            <p>© 2025 Yusai Brand Company. All rights reserved.</p>
        </footer>
    </div>

    <style>
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
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imageUploadArea = document.getElementById('imageUploadArea');
            const imageInput = document.getElementById('productImage');
            const imagePreview = document.getElementById('imagePreview');
            const form = document.getElementById('productSubmissionForm');
            const submitBtn = document.getElementById('submitBtn');

            // Disable submit button on click
            form.addEventListener('submit', function(e) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
            });

            // Image upload handling
            imageUploadArea.addEventListener('click', () => {
                imageInput.click();
            });

            imageInput.addEventListener('change', function() {
                imagePreview.innerHTML = '';
                const files = this.files;

                if (files.length > 3) {
                    alert('You can only upload up to 3 images');
                    this.value = '';
                    return;
                }

                for (let i = 0; i < files.length; i++) {
                    const file = files[i];

                    if (!file.type.match('image.*')) {
                        alert('Please select an image file (JPG, PNG, GIF)');
                        this.value = '';
                        return;
                    }

                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size exceeds 5MB limit');
                        this.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'preview-item';
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <div class="remove" data-index="${i}"><i class="fas fa-times"></i></div>
                        `;
                        imagePreview.appendChild(previewItem);

                        const removeBtn = previewItem.querySelector('.remove');
                        removeBtn.addEventListener('click', function() {
                            previewItem.remove();
                            const dt = new DataTransfer();
                            for (let j = 0; j < files.length; j++) {
                                if (j !== parseInt(this.getAttribute('data-index'))) {
                                    dt.items.add(files[j]);
                                }
                            }
                            imageInput.files = dt.files;
                        });
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Drag and drop functionality
            imageUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                imageUploadArea.style.borderColor = '#4b6cb7';
                imageUploadArea.style.backgroundColor = '#f0f3ff';
            });

            imageUploadArea.addEventListener('dragleave', () => {
                imageUploadArea.style.borderColor = '#ccc';
                imageUploadArea.style.backgroundColor = '';
            });

            imageUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                imageUploadArea.style.borderColor = '#ccc';
                imageUploadArea.style.backgroundColor = '';
                if (e.dataTransfer.files.length) {
                    imageInput.files = e.dataTransfer.files;
                    const event = new Event('change');
                    imageInput.dispatchEvent(event);
                }
            });

            // Form validation
            form.addEventListener('submit', function(e) {
                let valid = true;

                const requiredFields = form.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        valid = false;
                        field.style.borderColor = 'red';
                    } else {
                        field.style.borderColor = '';
                    }
                });

                const priceField = document.getElementById('productPrice');
                if (priceField.value <= 0) {
                    valid = false;
                    priceField.style.borderColor = 'red';
                    alert('Please enter a valid price');
                }

                const emailField = document.getElementById('contactEmail');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailField.value)) {
                    valid = false;
                    emailField.style.borderColor = 'red';
                    alert('Please enter a valid email address');
                }

                if (!valid) {
                    e.preventDefault();
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Product Request';
                }
            });
        });
    </script>
</body>
</html>