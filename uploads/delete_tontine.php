<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if an ID was provided in the query string
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $tontine_id = (int)$_GET['id'];

    // Prepare and execute the delete statement
    $stmt = $pdo->prepare("DELETE FROM tontine WHERE id = :id");
    $stmt->bindParam(':id', $tontine_id);

    if ($stmt->execute()) {
        // Redirect back with a success message
        header("Location: own_tontine.php?status=deleted");
        exit();
    } else {
        // Redirect back with an error message if deletion failed
        header("Location: own_tontine.php?status=error");
        exit();
    }
} else {
    // Redirect back if no ID was provided
    header("Location: own_tontine.php?status=missing_id");
    exit();
}
?>
