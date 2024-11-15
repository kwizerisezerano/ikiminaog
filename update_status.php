<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode the incoming JSON data
    $data = json_decode(file_get_contents('php://input'), true);

    // Extract tontine ID and status
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $status = isset($data['status']) ? $data['status'] : '';

    if ($id > 0 && in_array($status, ['Not Justified', 'Justification Request sent', 'Justified', 'Rejected'])) {
        try {
            // Include database configuration
            include 'config.php'; // Ensure this file sets up the PDO connection as $pdo

            // Prepare the SQL query
            $query = "UPDATE tontine SET status = :status WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            // Execute the query
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to execute the query']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
