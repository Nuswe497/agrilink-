<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';

echo "Structure of 'orders' table:\n";
$res = $conn->query("DESCRIBE orders");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\nSample data from 'orders' table:\n";
$res = $conn->query("SELECT * FROM orders LIMIT 5");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\nBuyer related transactions in 'finance' table:\n";
// Join with users to find transactions where the user's role is 'external' and stakeholder_type is 'buyer'
$res = $conn->query("
    SELECT f.*, u.full_name, u.stakeholder_type 
    FROM finance f 
    JOIN users u ON f.user_id = u.user_id 
    WHERE u.stakeholder_type = 'buyer' 
    LIMIT 10
");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
