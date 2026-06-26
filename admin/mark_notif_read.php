<?php
session_start();
require 'db.php';
require 'notif_count.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ── Mark ALL as read ────────────────────────────────────────────────────────
if (isset($_POST['mark_all'])) {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO user_notif_read (user_id, notification_id)
        SELECT ?, id FROM notifications
    ");
    $stmt->bind_param("i", $user_id);
    echo $stmt->execute()
        ? json_encode(['status' => 'success'])
        : json_encode(['status' => 'error', 'message' => $conn->error]);
    $stmt->close();
    exit;
}

// ── Mark single notification as read ───────────────────────────────────────
if (isset($_POST['notif_id'])) {
    $notif_id = (int)$_POST['notif_id'];
    $stmt = $conn->prepare("
        INSERT IGNORE INTO user_notif_read (user_id, notification_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("ii", $user_id, $notif_id);
    echo $stmt->execute()
        ? json_encode(['status' => 'success'])
        : json_encode(['status' => 'error', 'message' => $conn->error]);
    $stmt->close();
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
