<?php
// send_newsletter.php
session_start();
require 'vendor/autoload.php'; // PHPMailer via Composer
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


// Load SMTP credentials from .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (empty($subject) || empty($message)) {
        echo "<script>alert('Please fill in all fields.'); window.history.back();</script>";
        exit;
    }

    // Fetch all subscriber emails
    $stmt = $pdo->query("SELECT email FROM newsletter_subscribers");
    $subscribers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($subscribers) === 0) {
        echo "<script>alert('No subscribers found.'); window.history.back();</script>";
        exit;
    }

    $mail = new PHPMailer(true);

    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'mail.yusaibrand.co.ke';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER']; // info@yusaibrand.co.ke
        $mail->Password   = $_ENV['SMTP_PASS']; // email password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port       = 465;

        $mail->setFrom($_ENV['SMTP_USER'], 'YUSAI Brand');
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = nl2br($message);

        // Send to each subscriber
        foreach ($subscribers as $email) {
            $mail->addAddress($email);
        }

        if ($mail->send()) {
            echo "<script>alert('Newsletter sent successfully to all subscribers!'); window.location.href='admin_subscribers.php';</script>";
        } else {
            echo "<script>alert('Failed to send newsletter. Please try again.'); window.history.back();</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('Error: {$mail->ErrorInfo}'); window.history.back();</script>";
    }
} else {
    header("Location: admin_subscribers.php");
    exit;
}
