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
    $role = $_SESSION['role'] ?? 'admin';

    if ($role === 'admin') {
        $target_role = 'admin';
    } elseif ($role === 'treasurer') {
        $target_role = 'treasurer';
    } else {
        $target_role = 'member';
    }

    // Granular Unread Count:
    // Count all notifications that DO NOT have an entry in user_notif_read for this user,
    // filtered by exact target_role.
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
