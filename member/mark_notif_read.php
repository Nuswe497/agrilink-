<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];

    if (isset($_POST['mark_all'])) {
        // Mark all as read for the current role
        $role = $_SESSION['role'] ?? 'member';
        if ($role === 'admin') $target = 'admin';
        elseif ($role === 'treasurer') $target = 'treasurer';
        else $target = 'member';

        $stmt = $conn->prepare("
            INSERT IGNORE INTO user_notif_read (user_id, notification_id)
            SELECT ?, id FROM notifications WHERE target_role = ?
        ");
        $stmt->bind_param("is", $user_id, $target);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        $stmt->close();
    } elseif (isset($_POST['notif_id'])) {
        // Mark a single notification as read
        $notif_id = (int)$_POST['notif_id'];

        $stmt = $conn->prepare("
            INSERT IGNORE INTO user_notif_read (user_id, notification_id)
            VALUES (?, ?)
        ");
        $stmt->bind_param("ii", $user_id, $notif_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or session']);
}
?>
