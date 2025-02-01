<?php
session_start();
require 'config.php';

ini_set('display_errors', 1); // Remove in production
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tontine_id'], $_POST['amount'], $_POST['payment_method'])) {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $tontine_id = filter_var($_POST['tontine_id'], FILTER_VALIDATE_INT);
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $payment_method = htmlspecialchars($_POST['payment_method']);

        if (!$user_id || !$tontine_id || !$amount || !$payment_method) {
            throw new Exception('Invalid input data.');
        }

        if (!isset($pdo)) {
            throw new Exception("Database connection not initialized.");
        }

        $pdo->beginTransaction();

        // Validate tontine and join status
        $stmt = $pdo->prepare("SELECT tjr.status AS join_status, t.status AS tontine_status, t.occurrence, 
                               t.total_contributions AS expected_amount, t.join_date, t.late_contribution_penalty
                               FROM tontine_join_requests tjr
                               INNER JOIN tontine t ON tjr.tontine_id = t.id
                               WHERE tjr.user_id = :user_id AND tjr.tontine_id = :tontine_id");
        $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception('No matching tontine join request found.');
        }

        if ($result['join_status'] !== 'Permitted' || $result['tontine_status'] !== 'Justified') {
            throw new Exception('Invalid tontine status.');
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

        // Check if the contribution date already exists in contributions or missed contributions
        $contribution_date = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM contributions c
            WHERE c.user_id = :user_id AND c.tontine_id = :tontine_id 
            AND DATE(c.contribution_date) = :contribution_date
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'tontine_id' => $tontine_id,
            'contribution_date' => $contribution_date,
        ]);
        
        $contributionExists = $stmt->fetchColumn();

        // If contribution exists, skip insertion
        if ($contributionExists > 0) {
            echo json_encode([
                'status' => 'error',
                'title' => 'Duplicate Contribution',
                'message' => 'A contribution for today already exists for this tontine.',
            ]);
            exit();
        }

        // If no existing contribution, insert contribution
        $transaction_ref = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO contributions (user_id, tontine_id, amount, payment_method, transaction_ref, contribution_date, payment_status)
                               VALUES (:user_id, :tontine_id, :amount, :payment_method, :transaction_ref, NOW(), 'Pending')");
        $stmt->execute([
            'user_id' => $user_id,
            'tontine_id' => $tontine_id,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'transaction_ref' => $transaction_ref,
        ]);

        // Check if missed contributions or penalties should be applied
        $stmt = $pdo->prepare("SELECT contribution_date FROM contributions 
                               WHERE user_id = :user_id AND tontine_id = :tontine_id");
        $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id]);
        $contributed_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $missed_dates = array_diff($past_dates, $contributed_dates);

        if (!empty($missed_dates)) {
            foreach ($missed_dates as $missed_date) {
                // Handle penalties and missed contributions logic
                // Check and insert penalties if needed
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM penalties 
                                       WHERE user_id = :user_id AND tontine_id = :tontine_id AND missed_contribution_date = :missed_date");
                $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id, 'missed_date' => $missed_date]);
                $penalty_exists = $stmt->fetchColumn();

                if (!$penalty_exists) {
                    $penalty_amount = $result['late_contribution_penalty'];
                    $stmt = $pdo->prepare("INSERT INTO penalties (user_id, tontine_id, penalty_amount, infraction_date, reason, missed_contribution_date)
                                           VALUES (:user_id, :tontine_id, :penalty_amount, NOW(), 'Missed contributions', :missed_date)");
                    $stmt->execute([ 
                        'user_id' => $user_id,
                        'tontine_id' => $tontine_id,
                        'penalty_amount' => $penalty_amount,
                        'missed_date' => $missed_date,
                    ]);
                }

                // Check if missed contribution already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM missed_contributions 
                                       WHERE user_id = :user_id AND tontine_id = :tontine_id AND missed_date = :missed_date");
                $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id, 'missed_date' => $missed_date]);
                $missed_exists = $stmt->fetchColumn();

                if (!$missed_exists) {
                    // Insert missed contribution
                    $stmt = $pdo->prepare("INSERT INTO missed_contributions (user_id, tontine_id, missed_amount, missed_date)
                                           VALUES (:user_id, :tontine_id, :missed_amount, :missed_date)");
                    $stmt->execute([
                        'user_id' => $user_id,
                        'tontine_id' => $tontine_id,
                        'missed_amount' => $result['expected_amount'],
                        'missed_date' => $missed_date,
                    ]);
                }
            }
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'title' => 'Contribution Recorded',
            'message' => 'Your contribution has been successfully recorded.',
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
