<?php
/**
 * Database Migration: Add payout columns for Paychangu transfers
 * Run this once to update the database schema
 */

require 'db.php';

$migrations = [];
$errors = [];

// Check and add payout_phone column
$checkPhone = $conn->query("SHOW COLUMNS FROM users LIKE 'payout_phone'");
if (!$checkPhone || $checkPhone->num_rows == 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN payout_phone VARCHAR(20) NULL AFTER phone")) {
        $migrations[] = "✓ Added 'payout_phone' column to users table";
    } else {
        $errors[] = "✗ Failed to add 'payout_phone' column: " . $conn->error;
    }
} else {
    $migrations[] = "✓ 'payout_phone' column already exists";
}

// Check and add payout_method column
$checkMethod = $conn->query("SHOW COLUMNS FROM users LIKE 'payout_method'");
if (!$checkMethod || $checkMethod->num_rows == 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN payout_method ENUM('mobile_money', 'bank_transfer') DEFAULT 'mobile_money' AFTER payout_phone")) {
        $migrations[] = "✓ Added 'payout_method' column to users table";
    } else {
        $errors[] = "✗ Failed to add 'payout_method' column: " . $conn->error;
    }
} else {
    $migrations[] = "✓ 'payout_method' column already exists";
}

// Create transfer_history table if it doesn't exist
$checkTable = $conn->query("SHOW TABLES LIKE 'transfer_history'");
if (!$checkTable || $checkTable->num_rows == 0) {
    $createTableSQL = "
    CREATE TABLE transfer_history (
        transfer_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        transaction_id VARCHAR(255) UNIQUE,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        payment_method ENUM('mobile_money', 'bank_transfer') DEFAULT 'mobile_money',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        confirmation_date TIMESTAMP NULL,
        error_message VARCHAR(500) NULL,
        INDEX (user_id),
        INDEX (status),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )
    ";
    
    if ($conn->query($createTableSQL)) {
        $migrations[] = "✓ Created 'transfer_history' table";
    } else {
        $errors[] = "✗ Failed to create 'transfer_history' table: " . $conn->error;
    }
} else {
    $migrations[] = "✓ 'transfer_history' table already exists";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - Paychangu Transfers</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 1.8rem;
        }
        .migration-item {
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Database Migration</h1>
        
        <?php foreach ($migrations as $msg): ?>
            <div class="migration-item success"><?= $msg ?></div>
        <?php endforeach; ?>
        
        <?php foreach ($errors as $msg): ?>
            <div class="migration-item error"><?= $msg ?></div>
        <?php endforeach; ?>
        
        <div class="info">
            <strong>✓ Migration Complete!</strong><br>
            Your database is now ready for Paychangu profit transfers. You can safely delete this migration file.
        </div>
    </div>
  <footer style="background: #2a9d8f; color: white; text-align: center; padding: 1.5rem; margin-top: auto; font-size: 0.95rem;">
    &copy; 2026 Agrilink Cooperative | Premium Organic Products
  </footer>
</body>
</html>

