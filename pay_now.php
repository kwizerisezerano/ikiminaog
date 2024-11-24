<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>
            alert('You must be logged in to access this page.');
            window.location.href = 'index.php';
          </script>";
    exit();
}

// Get the loan ID, amount, payment date, late repayment amount, and tontine_id from the URL
$loan_id = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : null;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : null;
$payment_date = isset($_GET['payment_date']) ? $_GET['payment_date'] : null;
$late_amount = isset($_GET['late_amount']) ? (float)$_GET['late_amount'] : 0.0;  // Optional late repayment amount
$tontine_id = isset($_GET['tontine_id']) ? (int)$_GET['tontine_id'] : null; // Get tontine_id
$phone_number = isset($_GET['phone']) ? $_GET['phone'] : null; // Get phone number (Allow string, not cast to int)

$transaction_ref = bin2hex(random_bytes(16));  // Generate a unique transaction reference

// Validate required parameters
if (!$loan_id || !$payment_date || !$tontine_id) {
    echo "<script>
            alert('Required parameters are missing.');
            window.location.href = 'loan_list.php?id=$tontine_id';
          </script>";
    exit();
}

try {
    // Fetch loan details from the database
    $loanStmt = $pdo->prepare("SELECT * FROM loan_requests WHERE id = :loan_id AND user_id = :user_id AND tontine_id = :tontine_id");
    $loanStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
    $loanStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $loanStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $loanStmt->execute();

    $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        echo "<script>
                alert('Loan not found.');
                  window.location.href = 'loan_list.php?id=$tontine_id';
              </script>";
        exit();
    }

    // **Check for duplicate payment**:
    $checkPaymentStmt = $pdo->prepare("SELECT * FROM loan_payments WHERE tontine_id = :tontine_id AND loan_id = :loan_id AND user_id = :user_id AND (payment_status = 'Approved' OR payment_status = 'Pending')");
    $checkPaymentStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
    $checkPaymentStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $checkPaymentStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $checkPaymentStmt->execute();

    // If a payment already exists (either pending or completed), prevent insertion
    if ($checkPaymentStmt->rowCount() > 0) {
        echo "<script>
                alert('A payment for this loan has already been made or is pending.');
            window.location.href = 'loan_list.php?id=$tontine_id';
              </script>";
        exit();
    }

    // Proceed with the payment if no existing payment record
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Capture the phone number entered by the user
        $phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';

        // Process the payment (this assumes payment is successful and will be marked as 'Unpaid' initially)
        $pay = hdev_payment::pay($phone_number, $amount, $transaction_ref);

        if ($pay->status !== 'success') {
            echo "<script>
                    alert('Payment failed: " . $pay->message . "');
                  window.location.href = 'loan_list.php?id=$tontine_id';
                  </script>";
            exit();
        }

        // Insert the payment details into the loan_payments table
        $paymentStmt = $pdo->prepare("
            INSERT INTO loan_payments (user_id, loan_id, amount, payment_date, payment_status, transaction_ref, late_amount, tontine_id, phone_number)
            VALUES (:user_id, :loan_id, :amount, :payment_date, 'Pending', :transaction_ref, :late_amount, :tontine_id, :phone_number)
        ");

        $paymentStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'loan_id' => $loan_id,
            'amount' => round($amount), // Ensure amount is an integer
            'payment_date' => $payment_date,
            'transaction_ref' => $transaction_ref,
            'late_amount' => round($late_amount), // Ensure late amount is an integer
            'tontine_id' => $tontine_id,
            'phone_number' => $phone_number
        ]);

      

        // Payment successful, now redirect the user back to their loan list page
        echo "<script>
                alert('Payment successful!');
                 window.location.href = 'loan_list.php?id=$tontine_id';
              </script>";
        exit();
    }

} catch (PDOException $e) {
    echo "<script>
            alert('Error: " . htmlspecialchars($e->getMessage()) . "');
            window.location.href = 'loan_list.php?id=$tontine_id';
          </script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Loan</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .container {
            margin-top: 50px;
            width: 30%;
        }
    </style>
</head>
<body>
   <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="user_profile.php">Home</a>
                </li>
                <!-- Additional Menu Items -->
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="javascript:void(0);" onclick="confirmLogout();">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

<!-- Main Content -->
<div class="container mt-1">
    <h5 class="text-center">Confirm Payment for Loan</h5>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Loan Details</h5>

            <?php if ($loan): ?>
                <p><strong>Loan Amount:</strong> $<?php echo number_format(round($loan['loan_amount']), 0); ?></p>
                <p><strong>Interest Rate:</strong> <?php echo htmlspecialchars($loan['interest_rate']); ?>%</p>
                <p><strong>Payment Frequency:</strong> <?php echo htmlspecialchars($loan['payment_frequency']); ?></p>
                <p><strong>Due Payment Date:</strong> <?php echo htmlspecialchars($loan['payment_date']); ?></p>
                <p><strong>Late Loan Repayment Amount:</strong> RWF <?php echo number_format(round($late_amount), 0); ?></p>
                <p><strong>Tota Amount:</strong> <?php echo number_format(round($amount), 0); ?></p>

                <form action="pay_now.php?loan_id=<?php echo $loan_id; ?>&amount=<?php echo round($amount); ?>&payment_date=<?php echo urlencode($payment_date); ?>&late_amount=<?php echo round($late_amount); ?>&tontine_id=<?php echo $tontine_id; ?>" method="POST">
                    <div class="form-group">
                        <label for="phone_number">Your Phone Number</label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($phone_number); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount to Pay</label>
                        <input type="number" class="form-control" id="amount" name="amount"  >
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Pay Now</button>
                </form>
            <?php else: ?>
                <p class="text-danger">Loan details could not be found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
