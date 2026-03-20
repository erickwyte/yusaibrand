-- 1. Admin Emails Table
CREATE TABLE admin_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL
);

-- Insert default admin emails
INSERT INTO admin_emails (email) 
VALUES 
    ('saidiyusuf203@gmail.com'), 
    ('jumaoyoo010@gmail.com'), 
    ('bakariouma62@gmail.com'), 
    ('r.shidjuma92@gmail.com'), 
    ('angayiaerick@gmail.com');

-- 2. Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    new_email VARCHAR(150),
    email_verification_token VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    profile_image VARCHAR(255) DEFAULT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    wallet_balance DECIMAL(10,2) DEFAULT 0.00,
    referrer_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Products Table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255),
    type VARCHAR(50), -- e.g., 'refill', 'course', etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Orders Table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    notes TEXT,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    mpesa_receipt VARCHAR(50) DEFAULT NULL,
    delivery_status ENUM('pending' , 'delivered') DEFAULT 'pending',
    is_rewarded BOOLEAN DEFAULT FALSE,
    payment_failure_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. Order Items Table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    product_price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) AS (product_price * quantity) STORED,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 6. Transactions Table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NULL,
    checkout_request_id VARCHAR(100) NOT NULL,
    merchant_request_id VARCHAR(100) NOT NULL,
    mpesa_receipt_number VARCHAR(50) DEFAULT NULL,
    phone_number VARCHAR(15) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 7. Referral Earnings Table
CREATE TABLE referral_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    level ENUM('L1', 'L2') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id),
    FOREIGN KEY (to_user_id) REFERENCES users(id)
);

-- 8. Rewards Table
CREATE TABLE rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT,
    referred_user_id INT,
    amount DECIMAL(10,2),
    reward_type VARCHAR(50),
    reward_level INT DEFAULT 1,
    order_id INT,
    status ENUM('pending', 'paid', 'manually_paid') DEFAULT 'pending',
    paid_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- 9. B2C Transactions Table
CREATE TABLE b2c_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NULL,
    phone VARCHAR(15) NOT NULL, -- Changed from VARCHAR(12) to VARCHAR(15)
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') NOT NULL,
    failure_reason TEXT NULL,
    conversation_id VARCHAR(50),
    originator_conversation_id VARCHAR(50),
    receipt VARCHAR(50),
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- 10. Manual Payouts Table
CREATE TABLE manual_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reward_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    processed_by INT NOT NULL,
    processed_at DATETIME NOT NULL,
    FOREIGN KEY (reward_id) REFERENCES rewards(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- 11. Pending B2C Rewards Table
CREATE TABLE pending_b2c_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    referrer_id INT NOT NULL,
    referrer_phone VARCHAR(15) NOT NULL,
    referrer_amount DECIMAL(10,2) NOT NULL,
    order_id INT NOT NULL,
    transaction_amount DECIMAL(10,2) NOT NULL,
    reward_level INT DEFAULT 1,
    status ENUM('pending', 'processed') DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (referrer_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- 12. Additional Tables
CREATE TABLE newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    subscribed_at DATETIME NOT NULL
);

CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255),
    subject VARCHAR(255),
    message TEXT,
    response TEXT,
    responded_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sell_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255),
    product_description TEXT,
    product_category VARCHAR(100),
    product_price DECIMAL(10,2),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    seller_note TEXT,
    image1 VARCHAR(255),
    image2 VARCHAR(255),
    image3 VARCHAR(255),
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for better performance
CREATE INDEX idx_payment_status ON orders(payment_status);
CREATE INDEX idx_product_type ON products(type);
CREATE INDEX idx_transactions_checkout ON transactions(checkout_request_id);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_user_payment ON orders(user_id, payment_status);

CREATE INDEX idx_rewards_referrer ON rewards(referrer_id);
CREATE INDEX idx_rewards_referred ON rewards(referred_user_id);
CREATE INDEX idx_rewards_level ON rewards(reward_level);
CREATE INDEX idx_rewards_status ON rewards(status);

CREATE INDEX idx_b2c_conversation ON b2c_transactions(conversation_id);
CREATE INDEX idx_b2c_status ON b2c_transactions(status);

CREATE INDEX idx_manual_payouts_user ON manual_payouts(user_id);
CREATE INDEX idx_manual_payouts_processed ON manual_payouts(processed_at);

CREATE INDEX idx_pending_b2c_status ON pending_b2c_rewards(status);
CREATE INDEX idx_pending_b2c_created ON pending_b2c_rewards(created_at);

-- Add unique constraints for transactions
ALTER TABLE transactions
  ADD UNIQUE KEY uq_transactions_checkout (checkout_request_id),
  ADD UNIQUE KEY uq_transactions_merchant (merchant_request_id);