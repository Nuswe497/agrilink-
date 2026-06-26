<?php
require 'c:\xampp\htdocs\New\Agrilink\Agrilink\admin\db.php';

// 1. Create user_notif_read table for granular tracking
$sql = "CREATE TABLE IF NOT EXISTS user_notif_read (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, notification_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "Table user_notif_read created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// 2. Mark existing notifications as read based on user_notif_cleared?
// Actually, it's better to start fresh. 
// Any notification created after their last clear will still show as unread.
?>
