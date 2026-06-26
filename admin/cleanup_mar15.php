<?php
session_start();
require 'db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access.");
}

// SQL to delete transactions from March 15th, 2026
$sql = "DELETE FROM finance WHERE date = '2026-03-15'";

if ($conn->query($sql)) {
    $affected = $conn->affected_rows;
    echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #2a9d8f; background: #e6f7f5; border-radius: 8px; max-width: 500px; margin: 50px auto; text-align: center;'>";
    echo "<h2 style='color: #2a9d8f;'>Cleanup Successful</h2>";
    echo "<p>Successfully deleted <strong>$affected</strong> test transactions from March 15, 2026.</p>";
    echo "<a href='admin_finances.php' style='display: inline-block; padding: 10px 20px; background: #2a9d8f; color: white; text-decoration: none; border-radius: 5px;'>Back to Finances</a>";
    echo "</div>";
} else {
    echo "Error deleting records: " . $conn->error;
}
?>
