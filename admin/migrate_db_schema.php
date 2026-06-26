<?php
/**
 * Database Schema Migration Script
 * Fixes finance and hives table structure
 * Run this once to ensure database schema is correct
 */

// Include database connection
require __DIR__ . '/db.php';

echo "<h2>Database Schema Migration</h2>";
echo "<pre>";

try {
    // Step 1: Ensure finance table has finance_id primary key
    $check = $conn->query("SHOW COLUMNS FROM finance WHERE Field='finance_id'");
    if (!$check || $check->num_rows === 0) {
        echo "Adding finance_id primary key to finance table...\n";
        // Rename old table temporarily
        $conn->query("ALTER TABLE finance RENAME TO finance_old");
        
        // Create new table with proper schema
        $sql = "CREATE TABLE finance (
            finance_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            amount DECIMAL(10,2) DEFAULT NULL,
            date DATE DEFAULT NULL,
            balance DECIMAL(10,2) DEFAULT 0.00,
            transaction_type VARCHAR(50) DEFAULT 'contribution',
            description TEXT DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        if ($conn->query($sql)) {
            echo "✓ Created new finance table with finance_id\n";
            
            // Copy data from old table
            $conn->query("INSERT INTO finance (user_id, amount, date, balance, transaction_type, description) 
                         SELECT user_id, amount, date, balance, transaction_type, description FROM finance_old");
            echo "✓ Migrated data from old finance table\n";
            
            // Drop old table
            $conn->query("DROP TABLE finance_old");
            echo "✓ Dropped temporary table\n";
        } else {
            echo "✗ Error creating finance table: " . $conn->error . "\n";
        }
    } else {
        echo "✓ finance_id column already exists\n";
    }
    
    // Step 2: Ensure transaction_type is VARCHAR
    $check = $conn->query("SHOW COLUMNS FROM finance WHERE Field='transaction_type'");
    if ($check && $result = $check->fetch_assoc()) {
        if (strpos($result['Type'], 'varchar') === false && strpos($result['Type'], 'varchar') === false) {
            echo "Converting transaction_type to VARCHAR...\n";
            $conn->query("ALTER TABLE finance MODIFY transaction_type VARCHAR(50) DEFAULT 'contribution'");
            echo "✓ Converted transaction_type to VARCHAR\n";
        } else {
            echo "✓ transaction_type is already VARCHAR\n";
        }
    }
    
    // Step 3: Convert numeric transaction_type values to strings
    $check = $conn->query("SELECT COUNT(*) as cnt FROM finance WHERE transaction_type IN ('0','1','2','3')");
    if ($check && $row = $check->fetch_assoc()) {
        if ($row['cnt'] > 0) {
            echo "Converting numeric transaction_type values to strings...\n";
            $conn->query("UPDATE finance SET transaction_type = 'contribution' WHERE transaction_type = '0'");
            $conn->query("UPDATE finance SET transaction_type = 'fee' WHERE transaction_type = '1'");
            $conn->query("UPDATE finance SET transaction_type = 'sale' WHERE transaction_type = '2'");
            $conn->query("UPDATE finance SET transaction_type = 'profit' WHERE transaction_type = '3'");
            echo "✓ Converted " . $row['cnt'] . " numeric values to string types\n";
        }
    }
    
    // Step 4: Ensure hives table has registration_date
    $check = $conn->query("SHOW COLUMNS FROM hives WHERE Field='registration_date'");
    if (!$check || $check->num_rows === 0) {
        echo "Adding registration_date to hives table...\n";
        if ($conn->query("ALTER TABLE hives ADD COLUMN registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP")) {
            echo "✓ Added registration_date to hives table\n";
        } else {
            echo "✗ Error adding registration_date: " . $conn->error . "\n";
        }
    } else {
        echo "✓ registration_date column already exists in hives\n";
    }
    
    // Step 5: Display final schema
    echo "\n=== Final Schema ===\n";
    echo "\nFinance table structure:\n";
    $result = $conn->query("SHOW COLUMNS FROM finance");
    while ($row = $result->fetch_assoc()) {
        echo sprintf("  %-20s | %s | %s\n", $row['Field'], $row['Type'], $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }
    
    echo "\nHives table structure:\n";
    $result = $conn->query("SHOW COLUMNS FROM hives");
    while ($row = $result->fetch_assoc()) {
        echo sprintf("  %-20s | %s | %s\n", $row['Field'], $row['Type'], $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }
    
    echo "\n✓ Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Migration error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
