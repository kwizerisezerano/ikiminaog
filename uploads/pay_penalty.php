<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get the penalty ID, tontine ID, amount, and user's phone number from the URL
$penalty_id = isset($_GET['penalty_id']) ? (int)$_GET['penalty_id'] : null;
$tontine_id = isset($_GET['tontine_id']) ? (int)$_GET['tontine_id'] : null;
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : null;
$phone_number = isset($_GET['phone']) ? $_GET['phone'] : null;
$transaction_ref = bin2hex(random_bytes(16));  // Generate a unique transaction reference
// Validate required parameters
if (!$penalty_id || !$tontine_id || !$amount || !$phone_number) {
    // Redirect to the tontines page if any of the parameters are missing
    header("Location: tontines.php");
    exit();
}

try {
    // Fetch penalty details from the database
    $penaltyStmt = $pdo->prepare("SELECT * FROM penalties WHERE id = :penalty_id AND tontine_id = :tontine_id AND user_id = :user_id");
    $penaltyStmt->bindParam(':penalty_id', $penalty_id, PDO::PARAM_INT);
    $penaltyStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $penaltyStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $penaltyStmt->execute();
    
    $penalty = $penaltyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$penalty) {
        // If no penalty is found, redirect to tontines page
        header("Location: tontines.php");
        exit();
    }
    
    // Check if a payment has already been made for this penalty
    $checkPaymentStmt = $pdo->prepare("SELECT * FROM penalty_payment WHERE user_id = :user_id AND penalty_id = :penalty_id AND tontine_id = :tontine_id");
    $checkPaymentStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $checkPaymentStmt->bindParam(':penalty_id', $penalty_id, PDO::PARAM_INT);
    $checkPaymentStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $checkPaymentStmt->execute();

    if ($checkPaymentStmt->rowCount() > 0) {
        // Payment already exists, avoid inserting again or show a message
        $_SESSION['payment_error'] = 'Payment already made for this penalty.';
        header("Location: missed_penalties.php?id=" . $tontine_id);
        exit();
    }

    // Proceed with the payment (this assumes payment is successful and will be marked as 'Unpaid' initially)
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
         // If no existing payment, proceed with payment processing
        $pay = hdev_payment::pay($phone_number, $amount, $transaction_ref);

        if ($pay->status !== 'success') {
            throw new Exception("Payment failed: " . $pay->message);
        }

        // Insert the payment details into the penalty_payment table
        $paymentStmt = $pdo->prepare("
            INSERT INTO penalty_payment (user_id, tontine_id, penalty_id, amount, phone_number, payment_status, transaction_ref)
            VALUES (:user_id, :tontine_id, :penalty_id, :amount, :phone_number, 'Pending', :transaction_ref)
        ");
        
        $paymentStmt->execute([
          'user_id' => $_SESSION['user_id'],
          'tontine_id' => $tontine_id,
          'penalty_id' => $penalty_id,
          'amount' => $amount,
          'phone_number' => $phone_number,
          'transaction_ref' => $transaction_ref // Pass the generated transaction_ref
      ]);
        
        // Payment is assumed to be successful, now redirect the user back to their penalties page
        $_SESSION['payment_success'] = true;
        header("Location: missed_penalties.php?id=" . $tontine_id);
        exit();
    }

} catch (PDOException $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Penalty</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .container {
            margin-top: 50px;
        }
    </style>
</head>
<body>

<!-- Main Content -->
<div class="container">
    <h1 class="text-center">Confirm Payment for Penalty</h1>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Penalty Details</h5>

            <?php if ($penalty): ?>
                <p><strong>Tontine ID:</strong> <?php echo htmlspecialchars($tontine_id); ?></p>
                <p><strong>Penalty Amount:</strong> $<?php echo number_format($penalty['penalty_amount'], 2); ?></p>
                <p><strong>Reason:</strong> <?php echo htmlspecialchars($penalty['reason']); ?></p>
                <p><strong>Missed Contribution Date:</strong> <?php echo htmlspecialchars($penalty['missed_contribution_date']); ?></p>
                <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($phone_number); ?></p>

                <form action="pay_penalty.php?penalty_id=<?php echo $penalty_id; ?>&tontine_id=<?php echo $tontine_id; ?>&amount=<?php echo $amount; ?>&phone=<?php echo urlencode($phone_number); ?>" method="POST">
                    <div class="form-group">
                        <label for="phone">Your Phone Number</label>
                        <input type="text" class="form-control" id="phone" value="<?php echo htmlspecialchars($phone_number); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount to Pay</label>
                        <input type="text" class="form-control" id="amount" value="$<?php echo number_format($penalty['penalty_amount'], 2); ?>" readonly>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Pay Now</button>
                </form>
            <?php else: ?>
                <p class="text-danger">Penalty details could not be found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
