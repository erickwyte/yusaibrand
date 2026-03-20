<?php
session_start();
require_once 'db.php';
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../log_in.php');
    exit;
}

// Check if logged-in user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../access_denied.php');
    exit;
}

// Load .env variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Simple in-memory queue for emails (replace with Redis/RabbitMQ in production)
$emailQueue = [];

// Function to process email queue in background
function processEmailQueue($pdo, $emailQueue) {
    $mail = new PHPMailer(true);
    try {
        // SMTP settings (configured once for all emails)
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->CharSet = 'UTF-8';
        $mail->SMTPKeepAlive = true; // Keep SMTP connection alive

        foreach ($emailQueue as $emailData) {
            try {
                $mail->clearAllRecipients();
                $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
                $mail->addAddress($emailData['email'], $emailData['name']);
                $mail->addReplyTo($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
                $mail->MessageID = '<' . uniqid() . '@' . parse_url($_ENV['MAIL_FROM_ADDRESS'], PHP_URL_HOST) . '>';

                $mail->isHTML(true);
                $mail->Subject = $emailData['subject'];
                $mail->Body = $emailData['body'];
                $mail->AltBody = $emailData['altBody'];

                $mail->send();

                // Update database after successful send
                $updateStmt = $pdo->prepare("UPDATE contact_messages SET response = ?, responded_at = NOW() WHERE id = ?");
                $updateStmt->execute([$emailData['reply'], $emailData['id']]);
            } catch (Exception $e) {
                // Log error (in production, use proper logging)
                error_log("Queue email failed for ID {$emailData['id']}: {$mail->ErrorInfo}");
            }
        }
    } catch (Exception $e) {
        error_log("SMTP setup failed: {$mail->ErrorInfo}");
    } finally {
        $mail->smtpClose(); // Close SMTP connection
    }
}

// Handle reply form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $id = intval($_POST['message_id']);
    $reply = trim($_POST['reply_message']);

    // Fetch user's details
    $stmt = $pdo->prepare("SELECT email, name, subject, message FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Simplified email template
        $emailBody = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reply from Yusai Brand</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .content { padding: 20px; background: #fff; border: 1px solid #ddd; }
                .original { background: #f8f9fa; padding: 10px; margin: 10px 0; }
                .response { background: #e8f4fd; padding: 10px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($user['name']) . ',</h2>
                    <p>Thank you for your message. Our response:</p>
                    <div class="original">
                        <strong>Your Message:</strong><br>' . nl2br(htmlspecialchars($user['message'])) . '
                    </div>
                    <div class="response">
                        <strong>Our Response:</strong><br>' . nl2br(htmlspecialchars($reply)) . '
                    </div>
                    <p>Best regards,<br>Yusai Brand Support Team</p>
                </div>
            </div>
        </body>
        </html>';

        $altBody = "Dear {$user['name']},\n\nThank you for your message.\n\nYour Message:\n{$user['message']}\n\nOur Response:\n{$reply}\n\nBest regards,\nYusai Brand Support Team";

        // Add to email queue
        $emailQueue[] = [
            'id' => $id,
            'email' => $user['email'],
            'name' => $user['name'],
            'subject' => "Re: {$user['subject']}",
            'body' => $emailBody,
            'altBody' => $altBody,
            'reply' => $reply
        ];

        // Process queue in background (simulated)
        ignore_user_abort(true);
        set_time_limit(0);
        ob_start();
        processEmailQueue($pdo, $emailQueue);
        ob_end_clean();

        $_SESSION['success'] = "Reply queued for sending to {$user['name']}.";
        header('Location: admin_contact_messages.php');
        exit;
    }
}

// Handle test email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    if ($test_email) {
        $emailBody = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Test Email</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>Test Email</h2>
                <p>This is a test email from Yusai Brand.</p>
                <p>Date: ' . date('Y-m-d H:i:s') . '</p>
            </div>
        </body>
        </html>';

        $altBody = "Test Email\n\nThis is a test email from Yusai Brand.\nDate: " . date('Y-m-d H:i:s');

        $emailQueue[] = [
            'id' => 0, // No DB update for test email
            'email' => $test_email,
            'name' => 'Test Recipient',
            'subject' => 'Test Email from Yusai Brand',
            'body' => $emailBody,
            'altBody' => $altBody,
            'reply' => null
        ];

        ignore_user_abort(true);
        set_time_limit(0);
        ob_start();
        processEmailQueue($pdo, $emailQueue);
        ob_end_clean();

        $_SESSION['success'] = "Test email queued for sending to $test_email.";
        header('Location: admin_contact_messages.php');
        exit;
    }
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $deleteStmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = :id");
    $deleteStmt->bindParam(':id', $deleteId, PDO::PARAM_INT);
    $deleteStmt->execute();
    $_SESSION['success'] = "Message deleted successfully.";
    header('Location: admin_contact_messages.php');
    exit;
}

// Pagination variables
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Cache total count
$totalStmt = $pdo->query("SELECT COUNT(*) FROM contact_messages");
$totalMessages = $totalStmt->fetchColumn();

// Cache unreplied count
$unrepliedStmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE response IS NULL");
$unrepliedCount = $unrepliedStmt->fetchColumn();

// Fetch messages with pagination
$stmt = $pdo->prepare("SELECT * FROM contact_messages 
                      ORDER BY 
                        CASE WHEN response IS NULL THEN 0 ELSE 1 END, 
                        created_at DESC
                      LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();

// Calculate total pages
$totalPages = ceil($totalMessages / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages | Admin Panel</title>
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
            --warning: #f6c343;
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
            margin-left: 230px; /* Match sidebar width */
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stats-icon.total { background: rgba(44, 123, 229, 0.1); color: var(--primary); }
        .stats-icon.unreplied { background: rgba(244, 106, 38, 0.1); color: var(--danger); }
        .stats-icon.replied { background: rgba(0, 217, 126, 0.1); color: var(--secondary); }

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
        .thead:hover{
            background: var(--primary);
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
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-new {
            background: rgba(0, 217, 126, 0.1);
            color: var(--secondary);
        }
        
        .badge-unreplied {
            background: rgba(244, 106, 38, 0.1);
            color: var(--danger);
        }
        
        .badge-replied {
            background: rgba(0, 217, 126, 0.1);
            color: var(--secondary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            gap: 8px;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: rgba(44, 123, 229, 0.1);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #d33a1e;
        }
        
        .btn-warning {
            background: var(--warning);
            color: #fff;
        }

        .btn-warning:hover {
            background: #e5b339;
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
        
        .message-content {
            max-height: 100px;
            overflow: hidden;
            position: relative;
            line-height: 1.5;
        }
        
        .message-content::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
          
        }
        
        .message-details {
            margin-top: 0.5rem;
            color: var(--gray);
            font-size: 0.85rem;
        }
        
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
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.5rem;
            color: var(--dark);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .message-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-box {
            background: #f8fafc;
            border-radius: var(--border-radius);
            padding: 1rem;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: 600;
        }
        
        .message-container {
            background: #f8fafc;
            border-left: 3px solid var(--primary);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            white-space: pre-wrap;
            position: relative;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .message-label {
            font-weight: 600;
            color: var(--primary);
        }
        
        .message-date {
            font-size: 0.85rem;
            color: var(--gray);
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
            min-height: 150px;
            resize: vertical;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        
        .notification {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification.success {
            background: rgba(0, 217, 126, 0.1);
            color: var(--secondary);
            border-left: 3px solid var(--secondary);
        }
        
        .notification.error {
            background: rgba(244, 106, 38, 0.1);
            color: var(--danger);
            border-left: 3px solid var(--danger);
        }
        
        .notification i {
            font-size: 1.2rem;
        }
        
        .email-tools {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }
        
        .email-tools h3 {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .email-tools h3 i {
            color: var(--warning);
        }
        
        .tool-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 15px;
        }
        
        .form-inline {
            display: flex;
            gap: 10px;
        }
        
        .form-inline .form-control {
            flex: 1;
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tool-grid {
                grid-template-columns: 1fr;
            }
            
            .form-inline {
                flex-direction: column;
            }
            
            .message-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-envelope"></i>
                    Contact Messages
                </h1>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="notification success">
                    <i class="fas fa-check-circle"></i>
                    <p><?= $_SESSION['success'] ?></p>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="notification error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?= $_SESSION['error'] ?></p>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-icon total">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stats-content">
                        <h3>Total Messages</h3>
                        <p><?= number_format($totalMessages) ?> messages received</p>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon unreplied">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stats-content">
                        <h3>Unreplied Messages</h3>
                        <p><?= number_format($unrepliedCount) ?> awaiting response</p>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon replied">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-content">
                        <h3>Replied Messages</h3>
                        <p><?= number_format($totalMessages - $unrepliedCount) ?> responded to</p>
                    </div>
                </div>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $msg): 
                            $isNew = strtotime($msg['created_at']) > strtotime('-2 days');
                            $isReplied = !empty($msg['response']);
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($msg['name']) ?></td>
                                <td><?= htmlspecialchars($msg['subject']) ?></td>
                                <td><?= date('M j, Y', strtotime($msg['created_at'])) ?></td>
                                <td>
                                    <?php if ($isReplied): ?>
                                        <span class="badge badge-replied">Replied</span>
                                        <div class="message-details">
                                            <?= date('M j, Y', strtotime($msg['responded_at'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-unreplied">Unreplied</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <button class="btn btn-outline" onclick="openMessageModal(<?= $msg['id'] ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-danger" onclick="confirmDelete(<?= $msg['id'] ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem;">No messages found</td>
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
        </main>
    </div>
    
    <!-- Message Detail Modal -->
    <div class="modal" id="messageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Message Details</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="message-info">
                    <div class="info-box">
                        <div class="info-label">From</div>
                        <div class="info-value" id="modalName"></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Email</div>
                        <div class="info-value" id="modalEmail"></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Subject</div>
                        <div class="info-value" id="modalSubject"></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Date</div>
                        <div class="info-value" id="modalDate"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Original Message</label>
                    <div class="message-container">
                        <div class="message-header">
                            <div class="message-label">Customer Message</div>
                            <div class="message-date" id="modalMessageDate"></div>
                        </div>
                        <div class="message-content" id="modalMessage"></div>
                    </div>
                </div>
                
                <div id="responseSection" style="display: none;">
                    <div class="form-group">
                        <label>Our Response</label>
                        <div class="message-container">
                            <div class="message-header">
                                <div class="message-label">Your Reply</div>
                                <div class="message-date" id="modalResponseDate"></div>
                            </div>
                            <div class="message-content" id="modalResponse"></div>
                        </div>
                    </div>
                </div>
                
                <form method="post" id="replyForm" style="display: none;">
                    <input type="hidden" name="message_id" id="messageId">
                    <div class="form-group">
                        <label for="reply_message">Your Response</label>
                        <textarea id="reply_message" name="reply_message" class="form-control" placeholder="Type your response here..." required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Response
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function openMessageModal(id) {
        fetch(`get_message.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                document.getElementById('modalName').textContent = data.name;
                document.getElementById('modalEmail').textContent = data.email;
                document.getElementById('modalSubject').textContent = data.subject;
                document.getElementById('modalMessage').textContent = data.message;
                document.getElementById('modalDate').textContent = new Date(data.created_at).toLocaleString();
                document.getElementById('modalMessageDate').textContent = new Date(data.created_at).toLocaleString();
                document.getElementById('messageId').value = data.id;
                document.getElementById('responseSection').style.display = data.response ? 'block' : 'none';
                document.getElementById('modalResponse').textContent = data.response || '';
                document.getElementById('modalResponseDate').textContent = data.responded_at ? new Date(data.responded_at).toLocaleString() : '';
                document.getElementById('replyForm').style.display = data.response ? 'none' : 'block';
                document.getElementById('messageModal').style.display = 'flex';
                const textarea = document.getElementById('reply_message');
                if (textarea) {
                    textarea.style.height = 'auto';
                    textarea.style.height = (textarea.scrollHeight) + 'px';
                }
            })
            .catch(error => alert('Error loading message: ' + error.message));
    }
    
    function closeModal() {
        document.getElementById('messageModal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        if (event.target === document.getElementById('messageModal')) {
            closeModal();
        }
    };
    
    const replyTextarea = document.getElementById('reply_message');
    if (replyTextarea) {
        replyTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
    
    function confirmDelete(id) {
        if (confirm('Are you sure you want to delete this message?')) {
            window.location.href = `admin_contact_messages.php?delete_id=${id}`;
        }
    }
    
    document.querySelectorAll('.notification').forEach(notification => {
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.style.display = 'none', 300);
        }, 5000);
    });
    </script>
</body>
</html>