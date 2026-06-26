<?php
require 'db.php';
$res = $conn->query("SHOW CREATE TABLE notifications")->fetch_assoc();
print_r($res);
$res2 = $conn->query("SHOW CREATE TABLE finance")->fetch_assoc();
print_r($res2);
