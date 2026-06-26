<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';

// 1. Create products table
$sql1 = "CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    image_path VARCHAR(255),
    status ENUM('available', 'hidden') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql1)) echo "Table 'products' created successfully.\n";
else echo "Error creating 'products': " . $conn->error . "\n";

// 2. Add fee_status to users
$sql2 = "ALTER TABLE users ADD COLUMN IF NOT EXISTS fee_status ENUM('pending', 'paid') DEFAULT 'pending'";
if ($conn->query($sql2)) echo "Column 'fee_status' added to 'users' successfully.\n";
else echo "Error adding 'fee_status': " . $conn->error . "\n";

// 3. Update orders table
$sql3 = "ALTER TABLE orders 
    ADD COLUMN IF NOT EXISTS total_amount DECIMAL(10,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS paychangu_ref VARCHAR(255),
    ADD COLUMN IF NOT EXISTS product_id INT";
if ($conn->query($sql3)) echo "Table 'orders' updated successfully.\n";
else echo "Error updating 'orders': " . $conn->error . "\n";

?>
