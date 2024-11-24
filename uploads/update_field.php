<?php
// Include your database connection
include('config.php');

// Get the `id` from the POST request, as it is being sent from the JavaScript code
$id = isset($_POST['id']) ? (int) $_POST['id'] : null;

// Get the field and value from POST
$field = isset($_POST['field']) ? $_POST['field'] : '';
$value = isset($_POST['value']) ? $_POST['value'] : '';

// Check if `id`, `field`, and `value` are set
if ($id && $field && $value) {
    // Sanitize the input
    $field = htmlspecialchars($field);
    $value = htmlspecialchars($value);

    // Check if the field is either 'purpose' or 'rules' to avoid SQL injection risks
    if ($field == 'purpose' || $field == 'rules') {
        // Prepare the query to update the relevant field in the database
        $query = "UPDATE tontine SET $field = :value WHERE id = :id";
        $stmt = $pdo->prepare($query);

        // Bind the parameters
        $stmt->bindParam(':value', $value, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        // Execute the query and send a response based on the result
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid field']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
}
