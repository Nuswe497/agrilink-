<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';

echo "User IDs and their 'fee' field:\n";
$res = $conn->query("SELECT user_id, full_name, fee FROM users WHERE role = 'member' LIMIT 10");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\nCheck 'finance' for Registration Fee transactions:\n";
$res = $conn->query("SELECT * FROM finance WHERE description LIKE '%Registration%' OR description LIKE '%Fee%' LIMIT 10");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
