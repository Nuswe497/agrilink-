<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';
$res = $conn->query("DESCRIBE users");
if ($res) {
    echo "Table 'users' structure:\n";
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Failed to query table structure.";
}
?>
