<?php
session_start();
require 'config.php';

// Enable error reporting for development (remove this in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get the penalty and contribution details from the URL parameters
$penalty_id = isset($_GET['penalty_id']) ? (int)$_GET['penalty_id'] : null;
$tontine_id = isset($_GET['tontine_id']) ? (int)$_GET['tontine_id'] : null;
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : null;
$phone_number = isset($_GET['phone']) ? $_GET['phone'] : null;

$contribution_id = isset($_GET['contribution_id']) ? (int)$_GET['contribution_id'] : null;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Validate required parameters for penalty payment
if ($penalty_id && $tontine_id && $amount && $phone_number) {
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

        // Handle payment submission (in reality, you'd integrate payment processing here)
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Insert the payment details into the penalty_payment table
            $paymentStmt = $pdo->prepare("
                INSERT INTO penalty_payment (user_id, tontine_id, penalty_id, amount, phone_number, payment_status, transaction_ref)
                VALUES (:user_id, :tontine_id, :penalty_id, :amount, :phone_number, 'Unpaid', :transaction_ref)
            ");

            $paymentStmt->execute([
                'user_id' => $_SESSION['user_id'],
                'tontine_id' => $tontine_id,
                'penalty_id' => $penalty_id,
                'amount' => $amount,
                'phone_number' => $phone_number,
                // Payment status is 'Unpaid' initially
                'transaction_ref' => bin2hex(random_bytes(16))  // Generate a unique transaction reference
            ]);

            // Payment is assumed to be successful, now redirect the user back to their penalties page
            $_SESSION['payment_success'] = true;
            header("Location: missed_penalties.php?id=" . $tontine_id);
            exit();
        }

    } catch (PDOException $e) {
        die("Error: " . htmlspecialchars($e->getMessage()));
    }
}

// Validate required parameters for missed contribution payment
if ($contribution_id && $user_id && $amount && $phone_number && $tontine_id) {
    try {
        // Fetch missed contribution details
        $stmt = $pdo->prepare("SELECT mc.id, mc.missed_amount, mc.status, u.firstname, u.lastname, u.phone_number 
                               FROM missed_contributions mc
                               JOIN users u ON mc.user_id = u.id
                               WHERE mc.id = :contribution_id AND mc.user_id = :user_id");
        $stmt->execute([
            'contribution_id' => $contribution_id,
            'user_id' => $user_id
        ]);

        $contribution = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contribution) {
            // If no matching contribution is found, redirect to the contributions page
            header("Location: tontines.php");
            exit();
        }

        // Handle missed contribution payment submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Generate a unique transaction reference
            $transaction_ref = bin2hex(random_bytes(16));

            // Check if a payment already exists for this missed_id, user_id, and tontine_id
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM missed_contribution_payment 
                                   WHERE missed_id = :missed_id AND user_id = :user_id AND tontine_id = :tontine_id");
            $stmt->execute([
                'missed_id' => $contribution['id'],
                'user_id' => $user_id,
                'tontine_id' => $tontine_id
            ]);
            $payment_exists = $stmt->fetchColumn() > 0;

            if ($payment_exists) {
                // If a payment already exists for this missed_id, user_id, and tontine_id
                $_SESSION['message'] = "A payment for this contribution already exists.";
                header("Location: missed_contribution.php?id=" . $tontine_id);
                exit(); // Ensure no further code is executed after redirect
            }

            // If no existing payment, proceed with payment processing
            $pay = hdev_payment::pay($phone_number, $amount, $transaction_ref);

            if ($pay->status !== 'success') {
                throw new Exception("Payment failed: " . $pay->message);
            }

            // Insert the payment into the missed_contribution_payment table
            $stmt = $pdo->prepare("
                INSERT INTO missed_contribution_payment (user_id, tontine_id, amount, phone_number, transaction_ref, payment_status, missed_id)
                VALUES (:user_id, :tontine_id, :amount, :phone_number, :transaction_ref, :payment_status, :missed_id)
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'tontine_id' => $tontine_id,  // Use the received tontine_id
                'amount' => $amount,
                'phone_number' => $phone_number,
                'transaction_ref' => $transaction_ref,
                'payment_status' => 'Pending',  // or 'Approved', 'Failure' depending on the payment result
                'missed_id' => $contribution['id'],     // Insert the missed_id
            ]);

            // Set a session message for successful payment
            $_SESSION['message'] = "Payment for missed contribution was successful.";
            header("Location: missed_contribution.php?id=" . $tontine_id); // Redirect after successful payment
            exit();
        }

    } catch (Exception $e) {
        // Handle error and display a meaningful message
        error_log("Error: " . $e->getMessage());
        die("Error: " . htmlspecialchars($e->getMessage()));
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Page</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <a class="navbar-brand" href="#">Tontine System</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="home.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="penalties.php">Penalties</a>
                </li>
            </ul>
            <span class="navbar-text">
                Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
            </span>
        </div>
    </nav>

    <div class="container mt-5">
        <?php
        // Display any success or error messages
        if (isset($_SESSION['payment_success'])) {
            echo "<script>Swal.fire('Success!', 'Your penalty payment was successfully processed.', 'success');</script>";
            unset($_SESSION['payment_success']);
        }

        if (isset($_SESSION['payment_error'])) {
            echo "<script>Swal.fire('Error!', '" . $_SESSION['payment_error'] . "', 'error');</script>";
            unset($_SESSION['payment_error']);
        }
        ?>
        
        <!-- Payment Form -->
        <h3>Pay Penalty</h3>
        <form method="POST">
            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="text" class="form-control" name="phone_number" id="phone_number" required>
            </div>
            <div class="form-group">
                <label for="amount">Amount</label>
                <input type="number" class="form-control" name="amount" id="amount" value="<?php echo htmlspecialchars($amount); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Pay Now</button>
        </form>
    </div>

    <!-- Include Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
