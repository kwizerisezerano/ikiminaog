<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tontine_id'], $_POST['interest'])) {
    $tontine_id = filter_var($_POST['tontine_id'], FILTER_VALIDATE_INT);
    $amount = filter_var($_POST['interest'], FILTER_VALIDATE_FLOAT);

    if ($tontine_id === false || $amount === false) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Invalid Input',
            'message' => 'Invalid tontine ID or amount provided.',
        ]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE tontine SET late_contribution_penalty = :penalty WHERE id = :tontine_id");
        if ($stmt->execute(['penalty' => $amount, 'tontine_id' => $tontine_id])) {
            echo json_encode([
                'status' => 'success',
                'title' => 'Update Successful',
                'message' => 'Penalty has been updated successfully.',
                'redirect_to' => 'tontine_profile.php?id=' . $tontine_id, // Correct redirection
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'title' => 'Update Failed',
                'message' => 'Failed to update the penalty. Try again.',
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Database Error',
            'message' => 'An error occurred: ' . $e->getMessage(),
        ]);
    }
}
