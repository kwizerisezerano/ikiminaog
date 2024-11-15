<?php
// Include database configuration file
include 'config.php';

header("Content-Type: application/json");

// Get request ID from URL if it’s a GET request
$requestId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Only process POST requests for updates
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve data from POST
    $status = $_POST['status'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $requestId = $_POST['request_id'] ?? 0;  // Use request_id from POST

    // Debugging output to error log
    file_put_contents('php://stderr', "Received data - Status: $status, Reason: $reason, Request ID: $requestId\n");

    // Ensure valid data before updating the database
    if ($status && $reason && $requestId) {
        try {
            // Prepare and execute the update statement
            $stmt = $pdo->prepare("UPDATE tontine_join_requests SET status = :status, reason = :reason WHERE id = :id");
            $stmt->execute([
                ':status' => $status,
                ':reason' => $reason,
                ':id' => $requestId
            ]);

            // Check if the update affected any rows
            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true]);
            } else {
                echo json_encode(["success" => false, "message" => "No rows affected. Possibly incorrect request ID."]);
            }
        } catch (Exception $e) {
            // Handle and log any errors
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Invalid input"]);
    }
}
?>
