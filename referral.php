<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YUSAI Brand Referral Program</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --dark-blue: #002D62;
            --blue: #1E88E5;
            --orange: #FF6B00;
            --yellow: #FFC400;
            --light: #f8f9fa;
            --dark: #001a3d;
            --text: #333;
            --light-text: #555;
        }

        body {
            background: linear-gradient(135deg, #f9f9f9 0%, #ffffff 100%);
            color: var(--text);
        
            line-height: 1.7;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            margin:  auto;
            padding: 0 20px;
            flex: 1;
            padding-bottom: 50px;
        }

        /* Hero Section */
        .referral-hero {
            text-align: center;
            padding: 80px 20px 60px;
            margin-bottom: 40px;
            position: relative;
        }

        .referral-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--dark-blue), var(--blue));
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
            z-index: -1;
        }

        .referral-hero h1 {
            font-size: 3.2rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .referral-hero p {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
            max-width: 700px;
            margin: 0 auto;
        }

        .hero-highlight {
            background: linear-gradient(90deg, var(--yellow), var(--orange));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 700;
        }

        /* Program Overview */
        .program-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .overview-card {
            background: white;
            border-radius: 16px;
            padding: 35px 30px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-top: 4px solid var(--orange);
        }

        .overview-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 45, 98, 0.15);
        }

        .overview-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--dark-blue), var(--blue));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        .overview-card h3 {
            font-size: 1.4rem;
            color: var(--dark-blue);
            margin-bottom: 15px;
        }

        .overview-card p {
            color: var(--light-text);
            line-height: 1.6;
        }

        /* Level System */
        .level-system {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin: 60px 0;
            overflow: hidden;
        }

        .level-header {
            background: linear-gradient(to right, var(--dark-blue), var(--dark));
            color: white;
            padding: 25px;
            text-align: center;
        }

        .level-header h2 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .level-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        .level-body {
            padding: 40px 30px;
        }

        /* Level Indicator */
        .level-indicator {
            display: flex;
            justify-content: space-between;
            margin: 40px 0 60px;
            position: relative;
        }

        .level-indicator::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 50px;
            right: 50px;
            height: 6px;
            background: linear-gradient(to right, var(--yellow), var(--orange));
            z-index: 1;
            border-radius: 3px;
        }

        .level-step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .level-circle {
            background: white;
            border: 4px solid var(--yellow);
            color: var(--dark-blue);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.4rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .level-step.active .level-circle {
            background: var(--yellow);
            border-color: var(--dark-blue);
            transform: scale(1.1);
        }

        .level-name {
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 1rem;
            background: rgba(0, 45, 98, 0.1);
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        .level-step.active .level-name {
            background: var(--dark-blue);
            color: white;
        }

        /* Referral Table */
        .referral-table-container {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .referral-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .referral-table th {
            background: var(--orange);
            color: white;
            padding: 18px;
            font-weight: 600;
            font-size: 1.1rem;
            text-align: center;
        }

        .referral-table td {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .referral-table tr:nth-child(even) {
            background-color: rgba(0, 45, 98, 0.03);
        }

        .referral-table tr:hover {
            background-color: rgba(30, 136, 229, 0.05);
        }

        .level-column {
            font-weight: 700;
            color: var(--dark-blue);
        }

        .bonus-highlight {
            color: var(--orange);
            font-weight: 700;
            display: block;
            margin: 5px 0;
        }

        /* How It Works */
        .how-it-works {
            margin: 60px 0;
            text-align: center;
        }

        .how-it-works h2 {
            font-size: 2.5rem;
            color: var(--dark-blue);
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }

        .how-it-works h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--orange);
            border-radius: 2px;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .step-card {
            background: white;
            border-radius: 16px;
            padding: 35px 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .step-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 45, 98, 0.15);
        }

        .step-number {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--dark-blue);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .step-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--dark-blue), var(--blue));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .step-card h3 {
            font-size: 1.4rem;
            color: var(--dark-blue);
            margin-bottom: 15px;
        }

        .step-card p {
            color: var(--light-text);
            line-height: 1.6;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--dark-blue), var(--dark));
            color: white;
            text-align: center;
            padding: 70px 30px;
            border-radius: 16px;
            margin: 60px 0;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at top right, rgba(255, 196, 0, 0.2) 0%, transparent 70%);
            pointer-events: none;
        }

        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            position: relative;
        }

        .cta-section p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto 30px;
            opacity: 0.9;
        }

        .cta-button {
            background: linear-gradient(90deg, var(--yellow), var(--orange));
            color: var(--dark-blue);
            padding: 16px 50px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.2rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        }

        .cta-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(255, 107, 0, 0.4);
        }

        .cta-button i {
            margin-right: 12px;
            font-size: 1.3rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .referral-hero h1 {
                font-size: 2.5rem;
            }

            .referral-hero p {
                font-size: 1.1rem;
            }

            .level-indicator {
                flex-wrap: wrap;
                gap: 20px;
            }

            .level-indicator::before {
                display: none;
            }

            .level-step {
                flex: 0 0 calc(50% - 20px);
                margin-bottom: 30px;
            }

            .level-header h2 {
                font-size: 1.8rem;
            }

            .cta-section h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .referral-hero {
                padding: 60px 20px 40px;
            }

            .referral-hero h1 {
                font-size: 2rem;
            }

            .overview-card {
                padding: 25px 20px;
            }

            .step-card {
                padding: 30px 20px;
            }

            .level-step {
                flex: 0 0 100%;
            }

            .cta-section {
                padding: 50px 20px;
            }

            .cta-button {
                padding: 14px 35px;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>

 <?php include 'include/header.php'; ?>
    <div class="empty">

    <div class="container">
        <!-- Hero Section -->
        <section class="referral-hero">
            <h1>YUSAI Referral Program</h1>
            <p>Earn up to <span class="hero-highlight">KSh 2,500 monthly</span> by sharing our services with friends and family</p>
        </section>

        <!-- Program Overview -->
        <section class="program-overview">
            <div class="overview-card">
                <div class="overview-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <h3>Earn Money</h3>
                <p>Get paid for every successful referral you make with our tiered commission system</p>
            </div>
            
            <div class="overview-card">
                <div class="overview-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <h3>Exclusive Rewards</h3>
                <p>Receive special bonuses, branded merchandise, and monthly incentives</p>
            </div>
            
            <div class="overview-card">
                <div class="overview-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Growth Potential</h3>
                <p>Unlock higher earnings as you advance through our 6 achievement levels</p>
            </div>
        </section>

        <!-- Level System -->
        <section class="level-system">
            <div class="level-header">
                <h2>Referral System Levels</h2>
                <p class="level-subtitle">Progress through tiers to unlock greater rewards and bonuses</p>
            </div>
            
            <div class="level-body">
                <!-- Level Indicator -->
                <div class="level-indicator">
                    <div class="level-step active">
                        <div class="level-circle">1</div>
                        <div class="level-name">EXPLORER</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">2</div>
                        <div class="level-name">CONNECTOR</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">3</div>
                        <div class="level-name">CATALYST</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">4</div>
                        <div class="level-name">STRATEGIST</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">5</div>
                        <div class="level-name">VISIONARY</div>
                    </div>
                    <div class="level-step">
                        <div class="level-circle">6</div>
                        <div class="level-name">EMPIRE BUILDER</div>
                    </div>
                </div>
                
                <!-- Referral Table -->
                <div class="referral-table-container">
                    <table class="referral-table">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th>Requirements</th>
                                <th>Bonuses & Rewards</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="level-column">EXPLORER</td>
                                <td>Sign Up + 1 L1 Referral</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">CONNECTOR</td>
                                <td>5 L1 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 6 per L2</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">CATALYST</td>
                                <td>10 L1 + 25 L2 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 8 per L2</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">STRATEGIST</td>
                                <td>20 L1 + 50 L2 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 10 per L2</span>
                                    <span class="bonus-highlight">Branded T-shirt</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">VISIONARY</td>
                                <td>50 L1 + 200 L2 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 10 per L2</span>
                                    <span class="bonus-highlight">KSh 1,000 Monthly Bonus</span></td>
                            </tr>
                            <tr>
                                <td class="level-column">EMPIRE BUILDER</td>
                                <td>100+ L1 + 500+ L2 Referrals</td>
                                <td>Earn <span class="bonus-highlight">KSh 50 per L1</span>
                                    Earn <span class="bonus-highlight">KSh 10 per L2</span>
                                    <span class="bonus-highlight">KSh 2,500 Monthly Bonus</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="how-it-works">
            <h2>How It Works</h2>
            <p>Join thousands of YUSAI customers who are already earning through our referral program</p>
            
            <div class="steps">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Sign Up</h3>
                    <p>Register for our referral program to get your unique referral code</p>
                </div>
                
                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-icon">
                        <i class="fas fa-share-alt"></i>
                    </div>
                    <h3>Share</h3>
                    <p>Share your code with friends and family through social media or messaging</p>
                </div>
                
                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-icon">
                        <i class="fas fa-gas-pump"></i>
                    </div>
                    <h3>They Order</h3>
                    <p>Your referrals sign up and place their first gas order using your code</p>
                </div>
                
                <div class="step-card">
                    <div class="step-number">4</div>
                    <div class="step-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h3>You Earn</h3>
                    <p>Receive commission instantly after their first successful delivery</p>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <h2>Ready to Start Earning?</h2>
            <p>Join our referral program today and start earning rewards for every new customer you bring to YUSAI</p>
            <a href="sign_up.php" class="cta-button">
                <i class="fas fa-crown"></i> Join Referral Program
            </a>
        </section>
    </div>

    
  <!-- footer  ---------------->

<?php include 'include/footer.php'; ?>


</body>
</html>