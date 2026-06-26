<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';
echo "Sample data from 'shares' table:\n";
$res = $conn->query("SELECT * FROM shares LIMIT 5");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\nSample data from 'contributions' table:\n";
$res = $conn->query("SELECT * FROM contributions LIMIT 5");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
