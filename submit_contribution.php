<?php
session_start();
require 'config.php';

// Set proper headers for JSON response
header('Content-Type: application/json');

// Disable error display to prevent corruption of JSON response
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Function to send JSON response and exit
function sendJsonResponse($data) {
    echo json_encode($data);
    exit();
}

// Function to handle errors
function handleError($message, $title = 'Contribution Failed') {
    sendJsonResponse([
        'status' => 'error',
        'title' => $title,
        'message' => $message,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tontine_id'], $_POST['amount'], $_POST['payment_method'])) {
    try {
        // Input sanitization and validation
        $user_id = $_SESSION['user_id'] ?? null;
        $tontine_id = filter_var($_POST['tontine_id'], FILTER_VALIDATE_INT);
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $payment_method = htmlspecialchars($_POST['payment_method']);

        if (!$user_id) {
            handleError('User not logged in. Please refresh and try again.');
        }
        
        if (!$tontine_id || $tontine_id <= 0) {
            handleError('Invalid tontine ID.');
        }
        
        if (!$amount || $amount <= 0) {
            handleError('Invalid contribution amount.');
        }
        
        if (empty($payment_method)) {
            handleError('Payment method is required.');
        }

        // Ensure the database connection exists
        if (!isset($pdo)) {
            handleError("Database connection not initialized.");
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();

        // Validate tontine and join status
        $stmt = $pdo->prepare("SELECT tjr.status AS join_status, tjr.payment_status, t.status AS tontine_status, 
                                      t.occurrence, t.total_contributions AS expected_amount, t.join_date, 
                                      t.late_contribution_penalty
                                FROM tontine_join_requests tjr
                                INNER JOIN tontine t ON tjr.tontine_id = t.id
                                WHERE tjr.user_id = :user_id AND tjr.tontine_id = :tontine_id");
        $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            $pdo->rollBack();
            handleError('No matching tontine join request found.');
        }

        // Enhanced validation
        if ($result['join_status'] !== 'Permitted') {
            $pdo->rollBack();
            handleError('You are not permitted by the admin of this tontine.');
        }

        if ($result['payment_status'] !== 'Approved') {
            $pdo->rollBack();
            handleError('Your joining payment has not been approved yet. Please contact tontine admin.');
        }

        if ($result['tontine_status'] !== 'Justified') {
            $pdo->rollBack();
            handleError('This tontine is not registered by the sector.');
        }

        $today = date('Y-m-d');
        $join_date = $result['join_date'];
        $occurrence = $result['occurrence'];
        $expected_amount = $result['expected_amount'];
        $penalty_amount = $result['late_contribution_penalty'];

        // Check if user already contributed today (with approved status)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM contributions 
                               WHERE user_id = :user_id AND tontine_id = :tontine_id 
                               AND contribution_date = :today AND payment_status = 'Approved'");
        $stmt->execute([
            'user_id' => $user_id, 
            'tontine_id' => $tontine_id, 
            'today' => $today
        ]);
        
        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            handleError('You have already made an approved contribution for today.');
        }

        // Generate ALL expected contribution dates from join_date until today (inclusive)
        $all_expected_dates = [];
        $current_date = new DateTime($join_date);
        $today_date = new DateTime($today);
        
        // Generate all contribution dates including today
        while ($current_date <= $today_date) {
            $all_expected_dates[] = $current_date->format('Y-m-d');
            
            // Increment based on occurrence
            switch ($occurrence) {
                case 'Daily':
                    $current_date->modify('+1 day');
                    break;
                case 'Weekly':
                    $current_date->modify('+7 days');
                    break;
                case 'Monthly':
                    $current_date->modify('+1 month');
                    break;
                default:
                    $pdo->rollBack();
                    handleError('Invalid tontine occurrence: ' . $occurrence);
            }
        }

        // VALIDATE: Check if today is a valid contribution date
        if (!in_array($today, $all_expected_dates)) {
            $pdo->rollBack();
            
            // Find the next valid contribution date
            $next_date = null;
            $current_check = new DateTime($today);
            $current_check->modify('+1 day');
            
            // Look for next 30 days to find next contribution date
            for ($i = 0; $i < 30; $i++) {
                $check_date = $current_check->format('Y-m-d');
                
                // Calculate if this would be a contribution date
                $temp_date = new DateTime($join_date);
                while ($temp_date->format('Y-m-d') < $check_date) {
                    switch ($occurrence) {
                        case 'Daily':
                            $temp_date->modify('+1 day');
                            break;
                        case 'Weekly':
                            $temp_date->modify('+7 days');
                            break;
                        case 'Monthly':
                            $temp_date->modify('+1 month');
                            break;
                    }
                    
                    if ($temp_date->format('Y-m-d') === $check_date) {
                        $next_date = $check_date;
                        break 2;
                    }
                }
                
                $current_check->modify('+1 day');
            }
            
            $error_message = "Today ({$today}) is not a valid contribution date for this {$occurrence} tontine.";
            if ($next_date) {
                $error_message .= " Your next contribution date is: {$next_date}";
            }
            
            handleError($error_message, 'Invalid Contribution Date');
        }

        // Generate contribution dates up to yesterday for missed contribution checking
        $contribution_dates = [];
        $current_date = new DateTime($join_date);
        $yesterday = new DateTime($today);
        $yesterday->modify('-1 day'); // Only check up to yesterday, not today
        
        while ($current_date <= $yesterday) {
            $contribution_dates[] = $current_date->format('Y-m-d');
            
            // Increment based on occurrence
            switch ($occurrence) {
                case 'Daily':
                    $current_date->modify('+1 day');
                    break;
                case 'Weekly':
                    $current_date->modify('+7 days');
                    break;
                case 'Monthly':
                    $current_date->modify('+1 month');
                    break;
            }
        }

        // Debug: Log expected contribution dates
        error_log("Expected contribution dates: " . implode(', ', $contribution_dates));

        // Get all dates user has already contributed (with approved status)
        $stmt = $pdo->prepare("SELECT contribution_date FROM contributions 
                               WHERE user_id = :user_id AND tontine_id = :tontine_id AND payment_status = 'Approved'
                               ORDER BY contribution_date");
        $stmt->execute(['user_id' => $user_id, 'tontine_id' => $tontine_id]);
        $contributed_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Debug: Log actual contribution dates
        error_log("Actual contribution dates: " . implode(', ', $contributed_dates));

        // Find missed contribution dates
        $missed_dates = array_diff($contribution_dates, $contributed_dates);
        
        // Debug: Log missed dates
        error_log("Missed contribution dates: " . implode(', ', $missed_dates));

        // Process missed contributions
        $total_missed_amount = 0;
        $missed_count = 0;
        $penalty_count = 0;
        $total_penalty = 0;

        foreach ($missed_dates as $missed_date) {
            // Check if this missed contribution already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM missed_contributions 
                                   WHERE user_id = :user_id AND tontine_id = :tontine_id AND missed_date = :missed_date");
            $stmt->execute([
                'user_id' => $user_id,
                'tontine_id' => $tontine_id,
                'missed_date' => $missed_date
            ]);

            if ($stmt->fetchColumn() == 0) {
                // Insert missed contribution
                $stmt = $pdo->prepare("INSERT INTO missed_contributions (user_id, tontine_id, missed_amount, missed_date, status)
                                       VALUES (:user_id, :tontine_id, :missed_amount, :missed_date, 'Unpaid')");
                $result = $stmt->execute([
                    'user_id' => $user_id,
                    'tontine_id' => $tontine_id,
                    'missed_amount' => $expected_amount,
                    'missed_date' => $missed_date
                ]);
                
                if ($result) {
                    $total_missed_amount += $expected_amount;
                    $missed_count++;
                    error_log("Inserted missed contribution for date: {$missed_date}");
                } else {
                    error_log("Failed to insert missed contribution for date: {$missed_date}");
                }
            } else {
                error_log("Missed contribution already exists for date: {$missed_date}");
            }

            // Check if penalty for this date already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM penalties 
                                   WHERE user_id = :user_id AND tontine_id = :tontine_id 
                                   AND missed_contribution_date = :missed_date");
            $stmt->execute([
                'user_id' => $user_id,
                'tontine_id' => $tontine_id,
                'missed_date' => $missed_date
            ]);

            if ($stmt->fetchColumn() == 0) {
                // Insert penalty for missed contribution
                $stmt = $pdo->prepare("INSERT INTO penalties (user_id, tontine_id, penalty_amount, reason, missed_contribution_date, status)
                                       VALUES (:user_id, :tontine_id, :penalty_amount, :reason, :missed_date, 'Unpaid')");
                $result = $stmt->execute([
                    'user_id' => $user_id,
                    'tontine_id' => $tontine_id,
                    'penalty_amount' => $penalty_amount,
                    'reason' => 'Missed contribution penalty',
                    'missed_date' => $missed_date
                ]);
                
                if ($result) {
                    $total_penalty += $penalty_amount;
                    $penalty_count++;
                    error_log("Inserted penalty for missed date: {$missed_date}");
                } else {
                    error_log("Failed to insert penalty for missed date: {$missed_date}");
                }
            } else {
                error_log("Penalty already exists for missed date: {$missed_date}");
            }
        }

        // Generate transaction reference
        $transaction_ref = bin2hex(random_bytes(16));

        // Insert today's contribution
        $stmt = $pdo->prepare("INSERT INTO contributions (user_id, tontine_id, amount, payment_method, contribution_date, transaction_ref, payment_status)
                               VALUES (:user_id, :tontine_id, :amount, :payment_method, :contribution_date, :transaction_ref, 'Approved')");
        $stmt->execute([
            'user_id' => $user_id,
            'tontine_id' => $tontine_id,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'contribution_date' => $today,
            'transaction_ref' => $transaction_ref
        ]);

        $pdo->commit();

        // Prepare success message
        $success_message = 'Your contribution of ' . number_format($amount, 2) . ' has been successfully recorded.';
        
        if ($missed_count > 0) {
            $success_message .= ' We also recorded ' . $missed_count . ' missed contribution(s) totaling ' . number_format($total_missed_amount, 2) . '.';
        }
        
        if ($penalty_count > 0) {
            $success_message .= ' Penalties of ' . number_format($total_penalty, 2) . ' have been applied for ' . $penalty_count . ' missed contribution(s).';
        }

        sendJsonResponse([
            'status' => 'success',
            'title' => 'Contribution Recorded Successfully',
            'message' => $success_message,
            'transaction_ref' => $transaction_ref,
            'contribution_amount' => $amount,
            'missed_contributions' => $missed_count,
            'missed_amount' => $total_missed_amount,
            'penalties_applied' => $penalty_count,
            'penalty_amount' => $total_penalty,
            'debug_info' => [
                'expected_dates' => $contribution_dates,
                'contributed_dates' => $contributed_dates,
                'missed_dates' => array_values($missed_dates),
                'join_date' => $join_date,
                'today' => $today,
                'occurrence' => $occurrence,
                'all_valid_dates' => $all_expected_dates
            ],
            'redirect_url' => 'joined_tontine.php'
        ]);

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log the actual error for debugging
        error_log("Contribution error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
        
        handleError('An error occurred while processing your contribution: ' . $e->getMessage());
    }
} else {
    // Invalid request method or missing parameters
    handleError('Invalid request. Please ensure all required fields are provided.', 'Invalid Request');
}
?>