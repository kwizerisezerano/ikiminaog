<?php
session_start();
require 'config.php';

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tontine_id'], $_POST['amount'], $_POST['payment_method'])) {
    try {
        // Input sanitization and validation
        $user_id = $_SESSION['user_id'] ?? null;
        $tontine_id = filter_var($_POST['tontine_id'], FILTER_VALIDATE_INT);
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $payment_method = htmlspecialchars($_POST['payment_method']);

        if (!$user_id || !$tontine_id || !$amount || !$payment_method) {
            throw new Exception('Invalid input data.');
        }

        // Ensure the database connection exists
        if (!isset($pdo)) {
            throw new Exception("Database connection not initialized.");
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Enable exceptions
        $pdo->beginTransaction();

        // Validate tontine and join status - UPDATED TO INCLUDE PAYMENT_STATUS CHECK
        $stmt = $pdo->prepare("SELECT tjr.status AS join_status, tjr.payment_status, t.status AS tontine_status, 
                                      t.occurrence, t.total_contributions AS expected_amount, t.join_date, 
                                      t.late_contribution_penalty
                                FROM tontine_join_requests tjr
                                INNER JOIN tontine t ON tjr.tontine_id = t.id
                                WHERE tjr.user_id = :user_id AND tjr.tontine_id = :tontine_id");
        $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception('No matching tontine join request found.');
        }

        // ENHANCED VALIDATION: Check all three conditions
        if ($result['join_status'] !== 'Permitted') {
            throw new Exception('You are not permitted by the admin of this tontine.');
        }

        if ($result['payment_status'] !== 'Approved') {
            throw new Exception('Your joining payment has not been approved yet. Because your join payment status failed Please Contact tontine Admin');
        }

        if ($result['tontine_status'] !== 'Justified') {
            throw new Exception('This tontine is not registered by the sector.');
        }

        // Generate contribution dates
        $query = "
            WITH RECURSIVE contribution_dates AS (
                SELECT :join_date AS contribution_date,
                       ADDDATE(:join_date, INTERVAL 1 YEAR) AS end_date,
                       :occurrence AS frequency
                UNION ALL
                SELECT CASE 
                        WHEN frequency = 'Daily' THEN DATE_ADD(contribution_date, INTERVAL 1 DAY)
                        WHEN frequency = 'Weekly' THEN DATE_ADD(contribution_date, INTERVAL 7 DAY)
                        WHEN frequency = 'Monthly' THEN DATE_ADD(contribution_date, INTERVAL 1 MONTH)
                       END AS contribution_date,
                       end_date,
                       frequency
                FROM contribution_dates
                WHERE contribution_date < end_date
            )
            SELECT contribution_date FROM contribution_dates;
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'join_date' => $result['join_date'],
            'occurrence' => $result['occurrence'],
        ]);
        $valid_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Validate contribution date
        $today = (new DateTime())->format('Y-m-d');
        if (!in_array($today, $valid_dates)) {
            throw new Exception('Today is not a valid contribution date.');
        }

        // Check missed contributions - UPDATED to only consider APPROVED contributions
        $past_dates = array_filter($valid_dates, fn($date) => $date < $today);

        // FIXED: Only fetch contributions with 'Approved' payment status
        $stmt = $pdo->prepare("SELECT contribution_date FROM contributions 
                               WHERE user_id = :user_id AND tontine_id = :tontine_id AND payment_status = 'Approved'");
        $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id]);
        $contributed_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Prevent duplicate contributions on the same date - UPDATED logic
        if (in_array($today, $contributed_dates)) {
            throw new Exception('You have already made an approved contribution for today.');
        }

        $missed_dates = array_diff($past_dates, $contributed_dates);
        $penalty_applied_missed_dates = [];
        $total_penalty = 0;

        // Check for existing penalties on missed dates
        foreach ($missed_dates as $missed_date) {
            $stmt = $pdo->prepare("SELECT 1 FROM penalties WHERE user_id = :user_id AND tontine_id = :tontine_id AND missed_contribution_date = :missed_date");
            $stmt->execute([
                'user_id' => $user_id,
                'tontine_id' => $tontine_id,
                'missed_date' => $missed_date,
            ]);

            // If penalty does not exist, add it
            if (!$stmt->fetchColumn()) {
                $penalty_applied_missed_dates[] = $missed_date;
                $total_penalty += $result['late_contribution_penalty'];

                $stmt = $pdo->prepare("
                    INSERT INTO penalties (user_id, tontine_id, penalty_amount, infraction_date, reason, missed_contribution_date)
                    VALUES (:user_id, :tontine_id, :penalty_amount, NOW(), 'Missed contributions', :missed_date)
                ");
                $stmt->execute([
                    'user_id' => $user_id,
                    'tontine_id' => $tontine_id,
                    'penalty_amount' => $result['late_contribution_penalty'],
                    'missed_date' => $missed_date,
                ]);
            }
        }

        // Calculate the total payment
        if (!empty($penalty_applied_missed_dates)) {
            $total_payment = $amount + $total_penalty; // Add new penalties only
        } else {
            $total_payment = $amount; // Regular contribution without additional penalties
        }

        // Generate transaction reference
        $transaction_ref = bin2hex(random_bytes(16));

        // PAYMENT INTEGRATION - Initiate payment before recording contribution
        $pay = hdev_payment::pay($payment_method, $total_payment, $transaction_ref, $callback = '');

        // Check if payment was successful
        if ($pay->status !== 'success') {
            // Payment failed - rollback any penalties that were added and throw exception
            $pdo->rollBack();
            throw new Exception('Payment failed: ' . ($pay->message ?? 'Unknown payment error'));
        }

        // Payment was successful, record the contribution
        $stmt = $pdo->prepare("INSERT INTO contributions (user_id, tontine_id, amount, payment_method, transaction_ref, contribution_date, payment_status)
                               VALUES (:user_id, :tontine_id, :amount, :payment_method, :transaction_ref, NOW(), 'Approved')");
        $stmt->execute([
            'user_id' => $user_id,
            'tontine_id' => $tontine_id,
            'amount' => $total_payment,
            'payment_method' => $payment_method,
            'transaction_ref' => $transaction_ref,
        ]);

        $pdo->commit();

        // Prepare success message
        $penalty_message = '';
        if (!empty($penalty_applied_missed_dates)) {
            $penalty_count = count($penalty_applied_missed_dates);
            $penalty_message = " Including penalties for {$penalty_count} missed contribution(s) totaling {$total_penalty}.";
        }

        echo json_encode([
            'status' => 'success',
            'title' => 'Contribution Payment Successful',
            'message' => 'Your contribution payment of ' . $total_payment . ' has been successfully processed.' . $penalty_message,
            'transaction_ref' => $transaction_ref,
            'amount_paid' => $total_payment,
            'penalty_amount' => $total_penalty
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo json_encode([
            'status' => 'error',
            'title' => 'Contribution Failed',
            'message' => $e->getMessage(),
        ]);
    }
} else {
    // Invalid request method or missing parameters
    echo json_encode([
        'status' => 'error',
        'title' => 'Invalid Request',
        'message' => 'Invalid request. Please ensure all required fields are provided.',
    ]);
}
?>