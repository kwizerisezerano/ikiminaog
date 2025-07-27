<?php
// File: cleanup_old_notifications.php
// Run this script periodically (e.g., via cron job) to clean up old notifications
require 'config.php';

// Delete notifications older than 30 days
$days_to_keep = 30;

try {
    $stmt = $pdo->prepare("
        DELETE FROM notifications 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
    ");
    $stmt->bindParam(':days', $days_to_keep);
    
    if ($stmt->execute()) {
        $deleted_count = $stmt->rowCount();
        echo "Cleanup completed. Deleted $deleted_count old notifications.\n";
    } else {
        echo "Cleanup failed.\n";
    }
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}

// Optional: Clean up notifications for deleted users
try {
    $stmt = $pdo->prepare("
        DELETE n FROM notifications n 
        LEFT JOIN users u ON n.user_id = u.id 
        WHERE u.id IS NULL
    ");
    
    if ($stmt->execute()) {
        $deleted_count = $stmt->rowCount();
        echo "Orphaned notifications cleanup completed. Deleted $deleted_count notifications.\n";
    }
} catch (Exception $e) {
    echo "Error during orphaned notifications cleanup: " . $e->getMessage() . "\n";
}
?>