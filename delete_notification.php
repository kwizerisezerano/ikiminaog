<?php
// File: delete_notification.php (FIXED VERSION)
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST method allowed']);
    exit();
}

// Get the raw POST data
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

// Debug: Log the received data
error_log("Delete notification - Raw input: " . $raw_input);
error_log("Delete notification - Decoded input: " . print_r($input, true));

// Check for notification_id in multiple ways
$notification_id = null;

if (isset($input['notification_id'])) {
    $notification_id = (int)$input['notification_id'];
} elseif (isset($_POST['notification_id'])) {
    $notification_id = (int)$_POST['notification_id'];
} elseif (isset($_GET['notification_id'])) {
    $notification_id = (int)$_GET['notification_id'];
}

if (!$notification_id || $notification_id <= 0) {
    echo json_encode([
        'success' => false, 
        'error' => 'Notification ID required',
        'debug' => [
            'raw_input' => $raw_input,
            'decoded_input' => $input,
            'post_data' => $_POST,
            'get_data' => $_GET
        ]
    ]);
    exit();
}

try {
    // First check if notification exists and belongs to user
    $check_stmt = $pdo->prepare("
        SELECT id FROM notifications 
        WHERE id = :notification_id AND user_id = :user_id
    ");
    $check_stmt->bindParam(':notification_id', $notification_id);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Notification not found or access denied']);
        exit();
    }
    
    // Delete the notification
    $stmt = $pdo->prepare("
        DELETE FROM notifications 
        WHERE id = :notification_id AND user_id = :user_id
    ");
    $stmt->bindParam(':notification_id', $notification_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete notification']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
