<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';
$tables = ['contributions', 'shares', 'profits', 'finance'];
foreach ($tables as $table) {
    echo "\nStructure of '$table' table:\n";
    $res = $conn->query("DESCRIBE $table");
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}
?>
