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

// Get the contribution details from the URL parameters
$contribution_id = isset($_GET['contribution_id']) ? (int)$_GET['contribution_id'] : null;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : null;
$phone_number = isset($_GET['phone_number']) ? $_GET['phone_number'] : null;
$tontine_id = isset($_GET['tontine_id']) ? (int)$_GET['tontine_id'] : null; // Get the tontine_id

// Redirect to the contributions page if any of the details are missing
if (!$contribution_id || !$user_id || !$amount || !$phone_number || !$tontine_id) {
    header("Location: tontines.php");
    exit();
}

try {
    // Fetch contribution details and missed_id
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

    // Handle payment submission (in reality, you'd integrate payment processing here)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Generate a unique transaction reference
        $transaction_ref = bin2hex(random_bytes(16));
        
        // Check if a payment already exists for the same missed_id, user_id, and tontine_id
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Page</title>
     <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="paymentsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Tontine
                    </a>
                    <div class="dropdown-menu" aria-labelledby="paymentsDropdown">
                        <a class="dropdown-item" href="create_tontine.php">Create tontine</a>
                        <a class="dropdown-item" href="own_tontine.php">Tontine you Own</a>
                        <a class="dropdown-item" href="joined_tontine.php">List of Ibimina you have joined</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="contributionsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Contributions
                    </a>
                    <div class="dropdown-menu" aria-labelledby="contributionsDropdown">
                        <a class="dropdown-item" href="#">Send contributions</a>
                        <a class="dropdown-item" href="#">View Total Contributions</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="loansDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Loans
                    </a>
                    <div class="dropdown-menu" aria-labelledby="loansDropdown">
                        <a class="dropdown-item" href="#">View loan status</a>
                        <a class="dropdown-item" href="#">Apply for loan</a>
                        <a class="dropdown-item" href="#">Pay for loan</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="penaltiesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Penalties
                    </a>
                    <div class="dropdown-menu" aria-labelledby="penaltiesDropdown">
                        <a class="dropdown-item" href="#">View Paid Penalties</a>
                        <a class="dropdown-item" href="#">View Unpaid Penalties</a>
                        <a class="dropdown-item" href="#">Pay Penalties</a>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="#">Notifications</a>
                </li>
            </ul>

            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="javascript:void(0);" onclick="confirmLogout();">
                        Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Display Session Message -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['message']); ?>  <!-- Clear the message after displaying -->
    <?php endif; ?>

    <div class="container mt-5">
 
    <div class="row justify-content-center"> <!-- Centers the card -->
        <div class="col-md-6 col-lg-4"> <!-- Adjusts the card width for different screen sizes -->
            <div class="card">
                <div class="card-body">
                    <h4>Missed Contribution Details</h4>
                    <p><strong>User:</strong> <?php echo htmlspecialchars($contribution['firstname'] . ' ' . $contribution['lastname']); ?></p>
                    <p><strong>Amount Due:</strong> $<?php echo number_format($contribution['missed_amount'], 2); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($contribution['status']); ?></p>
                    <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($contribution['phone_number']); ?></p>

                    <!-- Payment Form -->
                    <form action="" method="POST">
                        <div class="form-group">
                            <label for="amount">Amount to Pay</label>
                            <input type="text" class="form-control" id="amount" name="amount" value="<?php echo number_format($amount, 2); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" readonly>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Pay Now</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure you want to log out?',
                text: "You will be logged out of your account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'No, stay logged in',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>
</body>
</html>
