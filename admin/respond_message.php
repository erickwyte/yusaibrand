<?php
session_start();
require_once 'db.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


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

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Invalid request method.');
}

// CSRF protection
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Invalid CSRF token.');
}

// Sanitize inputs
$id       = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$response = trim($_POST['response'] ?? '');

if (!$id || !$email || !$response) {
    $_SESSION['error'] = 'All fields are required.';
    header('Location: admin_contact_messages.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Invalid email format.';
    header('Location: admin_contact_messages.php');
    exit;
}

if (strlen($response) < 10) {
    $_SESSION['error'] = 'Response is too short.';
    header('Location: admin_contact_messages.php');
    exit;
}

// Fetch original message
try {
    $stmt = $pdo->prepare("SELECT name, subject, message FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    $original = $stmt->fetch();

    if (!$original) {
        throw new Exception('Original message not found.');
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: admin_contact_messages.php');
    exit;
}

// Configure PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USERNAME'];
    $mail->Password   = $_ENV['SMTP_PASSWORD'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $_ENV['SMTP_PORT'];
    $mail->SMTPDebug  = SMTP::DEBUG_OFF;

    // Sender and recipient
    $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress($email, $original['name']);
    if (!empty($_ENV['MAIL_REPLY_TO'])) {
        $mail->addReplyTo($_ENV['MAIL_REPLY_TO'], $_ENV['MAIL_REPLY_TO_NAME'] ?? 'Support');
    }

    // Email content
    $mail->isHTML(true);
    $mail->Subject = 'Re: ' . htmlspecialchars($original['subject']);

    $mail->Body = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f8f9fa; padding: 20px;'>
        <table style='max-width: 600px; margin: auto; background: white; border-radius: 8px; overflow: hidden;'>
            <tr>
                <td style='background: #2d6a4f; color: white; padding: 20px; text-align: center; font-size: 20px;'>
                    YUSAI Energy Support
                </td>
            </tr>
            <tr>
                <td style='padding: 20px;'>
                    <p>Dear <strong>" . htmlspecialchars($original['name']) . "</strong>,</p>
                    <p>" . nl2br(htmlspecialchars($response)) . "</p>
                    <hr>
                    <h4>Original Message:</h4>
                    <blockquote style='color: #555;'>" . nl2br(htmlspecialchars($original['message'])) . "</blockquote>
                    <p style='margin-top: 20px;'>Best regards,<br>YUSAI Energy Team</p>
                </td>
            </tr>
            <tr>
                <td style='background: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; color: #666;'>
                    © " . date('Y') . " YUSAI Energy. All rights reserved.
                </td>
            </tr>
        </table>
    </body>
    </html>";

    $mail->AltBody = "Dear {$original['name']},\n\n$response\n\nOriginal message:\n{$original['message']}";

    $mail->send();

    // Update DB with admin response
    $stmt = $pdo->prepare("UPDATE contact_messages SET response = ?, responded_at = NOW(), admin_id = ? WHERE id = ?");
    $stmt->execute([$response, $_SESSION['admin_id'], $id]);

    $_SESSION['success'] = 'Response sent successfully!';
} catch (Exception $e) {
    error_log('Mail error: ' . $mail->ErrorInfo);
    $_SESSION['error'] = 'Email failed to send. Please check SMTP settings.';
}

header('Location: admin_contact_messages.php');
exit;
