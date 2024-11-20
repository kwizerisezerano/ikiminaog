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

        // Validate tontine and join status
        $stmt = $pdo->prepare("
            SELECT tjr.status AS join_status, t.status AS tontine_status, t.occurrence, 
                   t.total_contributions AS expected_amount, t.join_date, t.late_contribution_penalty
            FROM tontine_join_requests tjr
            INNER JOIN tontine t ON tjr.tontine_id = t.id
            WHERE tjr.user_id = :user_id AND tjr.tontine_id = :tontine_id
        ");
        $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception('No matching tontine join request found.');
        }

        if ($result['join_status'] !== 'Permitted' || $result['tontine_status'] !== 'Justified') {
            throw new Exception('Tontine is not Approved by sector  or You are note Approved by Admin of this Tontine.');
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
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Check missed contributions
        $today = (new DateTime())->format('Y-m-d');
        $past_dates = array_filter($dates, fn($date) => $date < $today);

        // Check if the contribution date is valid
        $contribution_date = $today;

        if (!in_array($contribution_date, $dates)) {
            echo json_encode([
                'status' => 'error',
                'title' => 'Invalid Contribution Date',
                'message' => 'The current date is not a valid contribution date for this tontine.',
            ]);
            exit();
        }

        // Check for duplicate contributions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM contributions
            WHERE user_id = :user_id AND tontine_id = :tontine_id 
            AND DATE(contribution_date) = :contribution_date
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'tontine_id' => $tontine_id,
            'contribution_date' => $contribution_date,
        ]);

        if ($stmt->fetchColumn() > 0) {
            echo json_encode([
                'status' => 'error',
                'title' => 'Duplicate Contribution',
                'message' => 'A contribution for today already exists for this tontine.',
            ]);
            exit();
        }

        // Process payment
        $transaction_ref = bin2hex(random_bytes(16));
        $pay = hdev_payment::pay($payment_method, $amount, $transaction_ref);

        if ($pay->status !== 'success') {
            throw new Exception("Payment failed: " . $pay->message);
        }

        // Insert contribution
        $stmt = $pdo->prepare("
            INSERT INTO contributions (user_id, tontine_id, amount, payment_method, transaction_ref, contribution_date, payment_status)
            VALUES (:user_id, :tontine_id, :amount, :payment_method, :transaction_ref, NOW(), 'Pending')
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'tontine_id' => $tontine_id,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'transaction_ref' => $transaction_ref,
        ]);

        // Handle missed contributions and penalties
        $stmt = $pdo->prepare("
            SELECT contribution_date FROM contributions 
            WHERE user_id = :user_id AND tontine_id = :tontine_id
        ");
        $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id]);
        $contributed_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $missed_dates = array_diff($past_dates, $contributed_dates);

        foreach ($missed_dates as $missed_date) {
            // Insert penalties
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO penalties (user_id, tontine_id, penalty_amount, infraction_date, reason, missed_contribution_date)
                VALUES (:user_id, :tontine_id, :penalty_amount, NOW(), 'Missed contributions', :missed_date)
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'tontine_id' => $tontine_id,
                'penalty_amount' => $result['late_contribution_penalty'],
                'missed_date' => $missed_date,
            ]);

            // Insert missed contributions
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO missed_contributions (user_id, tontine_id, missed_amount, missed_date)
                VALUES (:user_id, :tontine_id, :missed_amount, :missed_date)
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'tontine_id' => $tontine_id,
                'missed_amount' => $result['expected_amount'],
                'missed_date' => $missed_date,
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'title' => 'Contribution Recorded',
            'message' => 'Your contribution has been successfully recorded and payment processed.',
            'redirect_url' => 'contribution_success.php?id=' . urlencode($tontine_id), // Add the redirect URL here
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'error',
            'title' => 'Error',
            'message' => $e->getMessage(),
        ]);
    }
}
?>
