<?php
require 'db.php';
require 'notif_count.php';
$res = $conn->query("DESCRIBE notifications");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
