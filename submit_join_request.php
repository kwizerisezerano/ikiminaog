<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tontine_id'], $_POST['number_place'], $_POST['amount'], $_POST['payment_method'])) {
    $user_id = $_SESSION['user_id'];
    $tontine_id = intval($_POST['tontine_id']);
    $number_place = intval($_POST['number_place']);
    $amount = floatval($_POST['amount']);
    $payment_method = htmlspecialchars($_POST['payment_method']);

    try {
        $stmt = $pdo->prepare("INSERT INTO tontine_join_requests (user_id, tontine_id, number_place, amount, payment_method, status) VALUES (:user_id, :tontine_id, :number_place, :amount, :payment_method, 'pending')");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':tontine_id', $tontine_id);
        $stmt->bindParam(':number_place', $number_place);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':payment_method', $payment_method);

        if ($stmt->execute()) {
            echo "Join request submitted successfully.";
        } else {
            echo "Error submitting the request.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
