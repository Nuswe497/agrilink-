<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';
$res = $conn->query("SELECT * FROM users LIMIT 1");
$user = $res->fetch_assoc();
if ($user) {
    echo "Full User Record:\n";
    foreach ($user as $key => $val) {
        echo "$key: $val\n";
    }
} else {
    echo "No users found.";
}
?>
