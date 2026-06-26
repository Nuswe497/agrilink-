<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';
$res = $conn->query("SELECT user_id, full_name, stakeholder_type FROM users WHERE role = 'external' AND stakeholder_type = 'buyer'");
echo "Buyers found:\n";
while($row = $res->fetch_assoc()){
    print_r($row);
}
?>
