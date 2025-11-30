<?php

session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tontine_id'], $_POST['number_place'], $_POST['amount'], $_POST['payment_method'], $_POST['terms'])) {
    $user_id = $_SESSION['user_id'];
    $tontine_id = filter_var($_POST['tontine_id'], FILTER_VALIDATE_INT); // Validate and sanitize
    $number_place = filter_var($_POST['number_place'], FILTER_VALIDATE_INT);
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $payment_method = htmlspecialchars($_POST['payment_method']);
    $terms = filter_var($_POST['terms'], FILTER_VALIDATE_INT);

    if (!$tontine_id || !$number_place || !$amount || !$terms) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Invalid Input',
            'message' => 'Some of the input data is invalid. Please check and try again.',
            'redirect' => 'join_tontine.php?id=' . $tontine_id
        ]);
        exit;
    }

    try {
        // Check the tontine status and count members with Approved payment status only
        $stmt = $pdo->prepare("
            SELECT 
                status, 
                (SELECT COUNT(*) FROM tontine_join_requests WHERE tontine_id = :tontine_id AND payment_status = 'Approved') AS approved_count 
            FROM tontine
            WHERE id = :tontine_id
        ");
        $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
        $stmt->execute();
        $tontine = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tontine) {
            echo json_encode([
                'status' => 'error',
                'title' => 'Tontine Not Found',
                'message' => 'The tontine you are trying to join does not exist.',
                'redirect' => 'join_tontine.php?id=' . $tontine_id
            ]);
            exit;
        }

        // Updated logic: Check status only if approved member count >= 5
        $approved_count = $tontine['approved_count'];
        
        if ($approved_count >= 5) {
            // If we already have 5+ approved members, only allow more if status is 'Justified'
            if ($tontine['status'] !== 'Justified') {
                echo json_encode([
                    'status' => 'warning',
                    'title' => 'Tontine Not Available',
                    'message' => 'This tontine has reached the maximum capacity for non-justified tontines. Only justified tontines can accept more than 5 members.',
                    'redirect' => 'user_profile.php'
                ]);
                exit;
            }
        }
        // For <5 approved members, allow joining regardless of status

        // Set a reasonable upper limit to prevent unlimited growth
        $max_members = ($tontine['status'] === 'Justified') ? 30 : 5;
        
        if ($approved_count >= $max_members) {
            echo json_encode([
                'status' => 'warning',
                'title' => 'Join Limit Reached',
                'message' => 'This tontine has reached its maximum allowed participants (' . $max_members . ' members).',
                'redirect' => 'join_tontine.php?id=' . $tontine_id
            ]);
            exit;
        }

        // Check if the user has already requested to join the tontine
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tontine_join_requests WHERE user_id = :user_id AND tontine_id = :tontine_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
        $stmt->execute();
        $exists = $stmt->fetchColumn();

        if ($exists > 0) {
            echo json_encode([
                'status' => 'warning',
                'title' => 'Already Joined',
                'message' => 'You have already submitted a join request for this tontine.',
                'redirect' => 'join_tontine.php?id=' . $tontine_id
            ]);
            exit;
        }

        // Payment process
        $transaction_ref = uniqid();
        $pay = hdev_payment::pay($payment_method, $amount, $transaction_ref, $callback = '');

        // Check if payment was successful
        if ($pay->status == 'success') {
            // Payment was successful, insert the join request into the database
            // Note: payment_status should be set based on your payment processing logic
            $payment_status = 'Pending'; // This should be updated to 'Approved' after payment verification
            
            $stmt = $pdo->prepare("
                INSERT INTO tontine_join_requests 
                (user_id, tontine_id, number_place, amount, payment_method, terms, status, reason, transaction_ref, payment_status) 
                VALUES 
                (:user_id, :tontine_id, :number_place, :amount, :payment_method, :terms, 'Pending', 'Stay patient your request is being processed', :transaction_ref, :payment_status)
            ");

            // Bind parameters
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
            $stmt->bindParam(':number_place', $number_place, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $amount, PDO::PARAM_STR); // Assuming amount is decimal or string
            $stmt->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
            $stmt->bindParam(':terms', $terms, PDO::PARAM_STR);
            $stmt->bindParam(':transaction_ref', $transaction_ref, PDO::PARAM_STR);
            $stmt->bindParam(':payment_status', $payment_status, PDO::PARAM_STR);

            if ($stmt->execute()) {
                echo json_encode([
                    'status' => 'success',
                    'title' => 'Join Request Submitted',
                    'message' => 'Payment was initiated. You will be redirected to the tontine you joined.',
                    'redirect' => 'joined_tontine.php?user_id=' . $user_id  // Add user_id to the URL
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'title' => 'Error',
                    'message' => 'There was an error submitting the request. Please try again.',
                    'redirect' => 'join_tontine.php?id=' . $tontine_id
                ]);
            }
        } else {
            // Payment failed
            echo json_encode([
                'status' => 'error',
                'title' => 'Payment Failed',
                'message' => "Payment failed: " . $pay->message,
                'redirect' => 'join_tontine.php?id=' . $tontine_id
            ]);
        }
        exit;

    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Database Error',
            'message' => htmlspecialchars($e->getMessage()),
            'redirect' => 'join_tontine.php?id=' . $tontine_id
        ]);
        exit;
    }
}

?>