-- SQL Setup for Paychangu Integration
-- Run these queries in your database to set up payment handling

-- Add status column to users table (if not already present)
ALTER TABLE users ADD COLUMN status ENUM('pending', 'active', 'inactive') DEFAULT 'active';

-- Create table for pending registrations (waiting for payment)
CREATE TABLE IF NOT EXISTS pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    hive_id VARCHAR(100) NOT NULL,
    date_joined DATE NOT NULL,
    fee DECIMAL(10,2) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    paychangu_reference VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (DATE_ADD(NOW(), INTERVAL 24 HOUR)),
    INDEX idx_paychangu_reference (paychangu_reference),
    INDEX idx_email (email)
);

-- Optional: Table to log all payment transactions for audit trail
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'MWK',
    status VARCHAR(50) NOT NULL,
    paychangu_reference VARCHAR(255),
    paychangu_transaction_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);
