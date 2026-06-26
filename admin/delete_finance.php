<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Unauthorized access.");
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM finance WHERE finance_id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: admin_finances.php?deleted=1");
    } else {
        echo "Error deleting record: " . $conn->error;
    }
    $stmt->close();
} else {
    header("Location: admin_finances.php");
}
exit;
