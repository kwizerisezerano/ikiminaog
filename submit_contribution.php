<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tontine_id'], $_POST['amount'], $_POST['payment_method'])) {
    $user_id = $_SESSION['user_id']; // Get the logged-in user ID
    $tontine_id = filter_var($_POST['tontine_id'], FILTER_VALIDATE_INT); // Validate tontine ID
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT); // Validate amount
    $payment_method = htmlspecialchars($_POST['payment_method']); // Sanitize payment method

    // Constants for status
    define('STATUS_PERMITTED', 'Permitted');
    define('STATUS_JUSTIFIED', 'Justified');

    // Validate inputs
    if (!$tontine_id || !$amount || !$payment_method) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Invalid Input',
            'message' => 'Some of the input data is invalid. Please check and try again.',
        ]);
        exit;
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Check user and tontine statuses
        $stmt = $pdo->prepare("
            SELECT 
                tjr.status AS join_status, 
                t.status AS tontine_status
            FROM tontine_join_requests tjr
            INNER JOIN tontine t ON tjr.tontine_id = t.id
            WHERE tjr.user_id = :user_id AND tjr.tontine_id = :tontine_id
        ");
        $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || $result['join_status'] !== STATUS_PERMITTED) {
            echo json_encode([
                'status' => 'error',
                'title' => 'Join Request Not Permitted',
                'message' => 'Your join request must be "Permitted" to contribute to this tontine.',
            ]);
            $pdo->rollBack();
            exit;
        }

        if ($result['tontine_status'] !== STATUS_JUSTIFIED) {
            echo json_encode([
                'status' => 'error',
                'title' => 'Tontine Not Justified',
                'message' => 'Tontine status must be "Justified" before contributing.',
            ]);
            $pdo->rollBack();
            exit;
        }

     
        // Simulate payment process
        $transaction_ref = bin2hex(random_bytes(16)); // Generate a unique transaction reference
        $pay = hdev_payment::pay($payment_method, $amount, $transaction_ref, '');

        if ($pay->status === 'success') {
            // Record the contribution
            $stmt = $pdo->prepare("
                INSERT INTO contributions (user_id, tontine_id, amount, payment_method, transaction_ref, contribution_date, payment_status) 
                VALUES (:user_id, :tontine_id, :amount, :payment_method, :transaction_ref, NOW(), 'Approved')
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'tontine_id' => $tontine_id,
                'amount' => $amount,
                'payment_method' => $payment_method,
                'transaction_ref' => $transaction_ref,
            ]);

            $pdo->commit(); // Commit the transaction

            echo json_encode([
                'status' => 'success',
                'title' => 'Contribution Submitted',
                'message' => 'Payment successful. Your contribution has been recorded.',
                'redirect' => 'contribution_success.php?id=' . $tontine_id, // Redirect to a success page
            ]);
        } else {
            // Payment failed
            $pdo->rollBack(); // Roll back the transaction
            echo json_encode([
                'status' => 'error',
                'title' => 'Payment Failed',
                'message' => 'Payment failed: ' . $pay->message,
            ]);
        }
    } catch (Exception $e) {
        // Handle server errors
        $pdo->rollBack();
        error_log($e->getMessage()); // Log the error
        echo json_encode([
            'status' => 'error',
            'title' => 'Server Error',
            'message' => 'An error occurred: ' . $e->getMessage(),
        ]);
    }
}
