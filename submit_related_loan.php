<?php
session_start();
require 'config.php'; // Database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch POST values
    $tontine_id = $_POST['tontine_id'];
    $interest = $_POST['interest'] ?? 0.00; // Default to 0.00 if not provided
    $payment_frequency = $_POST['payment_frequency'];
    $frequent_payment_date = $_POST['frequent_payment_date'] ?? null;
    $frequent_payment_day = $_POST['frequent_payment_day'] ?? null;
    $late_loan_repayment_amount = $_POST['late_loan_repayment_amount'] ?? 0.00; // Default to 0.00 if not provided

    // Prepare the query to update the tontine details
    $stmt = $pdo->prepare("UPDATE tontine SET 
                            interest = :interest, 
                            payment_frequency = :payment_frequency, 
                            frequent_payment_date = :frequent_payment_date, 
                            frequent_payment_day = :frequent_payment_day, 
                            late_loan_repayment_amount = :late_loan_repayment_amount 
                            WHERE id = :tontine_id");

    // Bind parameters
    $stmt->bindParam(':interest', $interest, PDO::PARAM_STR);
    $stmt->bindParam(':payment_frequency', $payment_frequency, PDO::PARAM_STR);
    $stmt->bindParam(':frequent_payment_date', $frequent_payment_date, PDO::PARAM_STR);
    $stmt->bindParam(':frequent_payment_day', $frequent_payment_day, PDO::PARAM_STR);
    $stmt->bindParam(':late_loan_repayment_amount', $late_loan_repayment_amount, PDO::PARAM_STR); // Bind late_loan_repayment_amount
    $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);

    // Execute the query and check if successful
    if ($stmt->execute()) {
        // Success message with redirection URL
        echo json_encode([
            'status' => 'success',
            'message' => 'Tontine updated successfully!',
            'redirect_to' => "tontine_profile.php?id=$tontine_id"
        ]);
    } else {
        // Error message
        $errorInfo = $stmt->errorInfo(); // Capture the error information
        echo json_encode([
            'status' => 'error',
            'message' => 'There was an error updating the tontine: ' . $errorInfo[2]
        ]);
    }
}
?>
