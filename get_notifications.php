<?php
// File: get_notifications.php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

try {
    $where_clause = "WHERE user_id = :user_id";
    if ($unread_only) {
        $where_clause .= " AND is_read = 0";
    }
    
    $stmt = $pdo->prepare("
        SELECT id, title, message, type, is_read, read_at, created_at
        FROM notifications 
        $where_clause
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format timestamps
    foreach ($notifications as &$notification) {
        $notification['created_at'] = date('Y-m-d H:i:s', strtotime($notification['created_at']));
        $notification['read_at'] = $notification['read_at'] ? date('Y-m-d H:i:s', strtotime($notification['read_at'])) : null;
        $notification['is_read'] = (bool)$notification['is_read'];
    }
    
    echo json_encode(['success' => true, 'notifications' => $notifications]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>