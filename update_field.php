<?php
// Include your database connection
include('config.php');

// Get the field and value from POST
$field = isset($_POST['field']) ? $_POST['field'] : '';
$value = isset($_POST['value']) ? $_POST['value'] : '';

if ($field && $value) {
    // Sanitize the input
    $field = htmlspecialchars($field);
    $value = htmlspecialchars($value);

    // Check if the field is either 'purpose' or 'rules'
    if ($field == 'purpose' || $field == 'rules') {
        // Update the relevant field in the database
        $query = "UPDATE tontine SET $field = :value WHERE id = 1";  // Assuming `id = 1` for this example
        $stmt = $pdo->prepare($query);

        // Bind the value to the prepared statement
        $stmt->bindParam(':value', $value, PDO::PARAM_STR);

        // Execute the query
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>
