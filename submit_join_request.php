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
        // Check if the user has already requested to join the tontine
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tontine_join_requests WHERE user_id = :user_id AND tontine_id = :tontine_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':tontine_id', $tontine_id);
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
            $stmt = $pdo->prepare("INSERT INTO tontine_join_requests (user_id, tontine_id, number_place, amount, payment_method, terms, status, transaction_ref) VALUES (:user_id, :tontine_id, :number_place, :amount, :payment_method, :terms, 'pending', :transaction_ref)");
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':tontine_id', $tontine_id);
$stmt->bindParam(':number_place', $number_place);
$stmt->bindParam(':amount', $amount);
$stmt->bindParam(':payment_method', $payment_method);
$stmt->bindParam(':terms', $terms);
$stmt->bindParam(':transaction_ref', $transaction_ref);


            // After successful join request submission
if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'title' => 'Join request submitted successfully',
        'message' => 'Payment was successful. You will be redirected to the tontine you joined.',
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
