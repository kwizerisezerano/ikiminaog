<?php
session_start();
require 'config.php';

// Ensure data is coming in correctly
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $requestId = $_POST['requestId'];
    $status = $_POST['status']; // 'Approved' or 'Rejected'
    $reason = $_POST['reason'];

    // Debugging: Log the input values
    error_log('Request ID: ' . $requestId);
    error_log('Status: ' . $status);
    error_log('Reason: ' . $reason);

    // Validate input
    if (empty($requestId) || empty($status) || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit();
    }

    // Update the request status and reason in the database
    $stmt = $pdo->prepare("UPDATE tontine_join_requests SET status = :status, reason = :reason WHERE id = :id");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':id', $requestId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Request has been ' . strtolower($status)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to process the request. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
