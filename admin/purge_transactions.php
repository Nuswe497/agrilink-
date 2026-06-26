<?php
session_start();
require 'db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access.");
}

$start_date = $_POST['start_date'] ?? null;
$end_date   = $_POST['end_date'] ?? null;

if (!$start_date || !$end_date) {
    die("Please provide both start and end dates.");
}

// SQL to delete transactions in range
$stmt = $conn->prepare("DELETE FROM finance WHERE date >= ? AND date <= ?");
$stmt->bind_param("ss", $start_date, $end_date);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #2a9d8f; background: #e6f7f5; border-radius: 8px; max-width: 500px; margin: 50px auto; text-align: center;'>";
    echo "<h2 style='color: #2a9d8f;'>Purge Successful</h2>";
    echo "<p>Successfully deleted <strong>$affected</strong> transactions between <strong>$start_date</strong> and <strong>$end_date</strong>.</p>";
    echo "<a href='admin_finances.php' style='display: inline-block; padding: 10px 20px; background: #2a9d8f; color: white; text-decoration: none; border-radius: 5px;'>Back to Finances</a>";
    echo "</div>";
} else {
    echo "Error deleting records: " . $conn->error;
}
$stmt->close();
?>
