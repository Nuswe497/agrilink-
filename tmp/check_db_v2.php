<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';
echo "Tables in database:\n";
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}

echo "\nStructure of 'users' table:\n";
$res = $conn->query("DESCRIBE users");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
