<?php
/**
 * Notification count helper.
 * Include this AFTER session_start() and db.php on any member page.
 * Provides: $notifCount (int) — total unread notifications for this user.
 *
 * This version uses the `user_notif_read` table for granular tracking.
 */

$notifCount = 0;

if (!empty($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];

    $role = $_SESSION['role'] ?? 'member';
    if ($role === 'admin') {
        $target_role = 'admin';
    } elseif ($role === 'treasurer') {
        $target_role = 'treasurer';
    } else {
        $target_role = 'member';
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt 
        FROM notifications n
        LEFT JOIN user_notif_read r ON n.id = r.notification_id AND r.user_id = ?
        WHERE r.notification_id IS NULL
        AND n.target_role = ?
    ");
    $stmt->bind_param("is", $uid, $target_role);
    $stmt->execute();
    $res = $stmt->get_result();
    $notifCount = (int)($res->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
}
?>
