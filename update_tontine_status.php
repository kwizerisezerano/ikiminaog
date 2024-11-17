<?php
// Include the database connection file
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tontine_id = $_POST['tontine_id'];
    $status = $_POST['status'];
    $reason = $_POST['reason'];

    // Validate the input
    if (empty($tontine_id) || empty($status) || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    // Prepare the SQL query to update the status and reason in the database
    $query = "UPDATE tontine SET status = :status, reason = :reason WHERE id = :tontine_id";
    $stmt = $pdo->prepare($query);
    
    // Bind the values to the query
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);

    // Execute the query and check if it was successful
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status and reason updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update the status.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
