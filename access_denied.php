<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied | Admin Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #1a2a6c);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
        }
        
        .container {
            background: rgba(0, 0, 0, 0.7);
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .lock-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            color: #ff4d4d;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #ff4d4d;
        }
        
        p {
            font-size: 1.2rem;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #ff4d4d;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            margin: 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
        }
        
        .btn:hover {
            background: #ff1a1a;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 77, 77, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid #4d79ff;
            color: #4d79ff;
        }
        
        .btn-secondary:hover {
            background: rgba(77, 121, 255, 0.1);
            box-shadow: 0 5px 15px rgba(77, 121, 255, 0.3);
        }
        
        .contact-info {
            margin-top: 30px;
            font-size: 0.9rem;
            color: #aaa;
        }
        
        .admin-note {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 77, 77, 0.1);
            border-radius: 8px;
            font-size: 0.9rem;
            text-align: left;
        }
        
        .logo {
            position: absolute;
            top: 20px;
            left: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
        }
    </style>
</head>
<body>
    <div class="logo">AdminPortal</div>
    
    <div class="container">
        <div class="lock-icon">🔒</div>
        <h1>Access Denied</h1>
        <p>You do not have permission to access this page. This area is restricted to administrators only.</p>
        
        <p>If you believe this is an error, please contact your system administrator with your account details.</p>
        
        <div class="admin-note">
            <strong>Admin Note:</strong> This page is displayed when a non-admin user attempts to access restricted admin pages.
            The PHP security code should be placed at the top of all admin pages to enforce this restriction.
        </div>
        
        <div>
            <a href="../index.php" class="btn">Return to Homepage</a>
            <a href="mailto:admin@example.com" class="btn btn-secondary">Contact Administrator</a>
        </div>
        
        <div class="contact-info">
            <p>If you need immediate assistance, please call: (555) 123-4567</p>
        </div>
    </div>

    <script>
        // Simple animation on buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>