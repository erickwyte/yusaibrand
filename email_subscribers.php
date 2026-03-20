<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $userEmail = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);

    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Invalid email address.";
        $_SESSION['messageType'] = 'error';
    } else {
        require_once 'db.php';

        $checkStmt = $pdo->prepare("SELECT id FROM newsletter_subscribers WHERE email = ?");
        $checkStmt->execute([$userEmail]);

        if ($checkStmt->rowCount() > 0) {
            $_SESSION['message'] = "This email is already subscribed.";
            $_SESSION['messageType'] = 'warning';
        } else {
            $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email, subscribed_at) VALUES (?, NOW())");
            $stmt->execute([$userEmail]);

            $subject = "Thanks for joining Yusai Brand!";
           $body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to Yusai Brand</title>
    <style>
        body { font-family: "Poppins", Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .header { background: linear-gradient(135deg, #2d6a4f 0%, #1e3c30 100%); padding: 35px 20px; text-align: center; }
        .logo { font-size: 28px; font-weight: 700; color: white; letter-spacing: 1px; }
        .logo span { color: #b7e4c7; }
        .content { padding: 40px 30px; color: #333; line-height: 1.6; font-size: 16px; }
        h1 { color: #2d6a4f; margin-top: 0; font-size: 26px; }
        ul { padding-left: 20px; }
        .button { display: inline-block; background: #2d6a4f; color: white !important; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 600; margin: 20px 0; }
        .social-links { margin: 30px 0; }
        .social-links a { display: inline-block; margin: 0 8px; }
        .social-links img { width: 28px; height: 28px; }
        .footer { background: #f1f1f1; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        @media only screen and (max-width: 600px) {
            .content { padding: 25px 20px; }
            h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">YUSAI<span>BRAND</span></div>
        </div>
        <div class="content">
            <h1>Welcome to the Yusai Community!</h1>
            <p>Hi there,</p>
            <p>Thank you for subscribing to Yusai Brand — we\'re thrilled to have you join our growing community of eco-conscious individuals.</p>
            <p>Here\'s what you can expect from us:</p>
            <ul>
                <li>🌱 New eco-friendly product launches</li>
                <li>💚 Exclusive subscriber-only discounts</li>
                <li>📖 Sustainability tips and guides</li>
                <li>📅 Upcoming workshops and events</li>
            </ul>
            <p>Our mission is simple: help you live a sustainable lifestyle without compromising on style or quality.</p>
            <p style="text-align:center;">
                <a href="https://yusaibrand.co.ke" class="button">Visit Our Website</a>
            </p>
            <div class="social-links" style="text-align:center;">
                <p>Let\'s stay connected:</p>
                <a href="https://facebook.com/yusaibrand" target="_blank"><img src="https://cdn-icons-png.flaticon.com/512/733/733547.png" alt="Facebook"></a>
                <a href="https://instagram.com/yusaibrand" target="_blank"><img src="https://cdn-icons-png.flaticon.com/512/2111/2111463.png" alt="Instagram"></a>
                <a href="https://twitter.com/yusaibrand" target="_blank"><img src="https://cdn-icons-png.flaticon.com/512/733/733579.png" alt="Twitter"></a>
                <a href="https://pinterest.com/yusaibrand" target="_blank"><img src="https://cdn-icons-png.flaticon.com/512/733/733558.png" alt="Pinterest"></a>
            </div>
            <p>If you ever change your mind, you can <a href="#">unsubscribe</a> anytime.</p>
            <p>With green regards,<br><strong>The Yusai Brand Team</strong></p>
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' Yusai Brand Company. All rights reserved.</p>
            <p>123 Green Street, Eco City | info@yusaibrand.co.ke</p>
        </div>
    </div>
</body>
</html>';


            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'mail.yusaibrand.co.ke';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'info@yusaibrand.co.ke';
                $mail->Password   = 'XQM~FF!0,Zs!fK4-'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('info@yusaibrand.co.ke', 'Yusai Brand Newsletter');
                $mail->addAddress($userEmail);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                $mail->AltBody = 'Thank you for subscribing to Yusai Brand!';

                $mail->send();
                $_SESSION['message'] = "Subscription successful! Check your email for a welcome message.";
                $_SESSION['messageType'] = 'success';
            } catch (Exception $e) {
                $_SESSION['message'] = "Subscription saved, but we couldn't send the confirmation email.";
                $_SESSION['messageType'] = 'warning';
            }
        }
    }
}

// Redirect to index page
header("Location: index.php");
exit();
