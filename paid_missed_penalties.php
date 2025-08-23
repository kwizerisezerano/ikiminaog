<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get the Tontine ID from the URL or POST
$tontine_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$tontine_id) {
    header("Location: tontines.php");
    exit();
}

// Pagination setup
$perPage = 5; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure the page number is at least 1
$start = ($page - 1) * $perPage;

try {
    // Fetch the total count of distinct missed penalties (one per user and missed date)
    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT pp.user_id, pp.penalty_id) AS total
        FROM penalty_payment pp
        JOIN users u ON pp.user_id = u.id
        WHERE pp.tontine_id = :tontine_id
    ");
    $countStmt->execute([ 'tontine_id' => $tontine_id ]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch penalty payments and associated penalty details
    $stmt = $pdo->prepare("
        SELECT pp.id, pp.penalty_id, pp.amount, pp.phone_number, pp.payment_status, pp.transaction_ref, pp.payment_date,
               p.reason, p.missed_contribution_date, u.firstname, u.lastname, u.id AS user_id
        FROM penalty_payment pp
        LEFT JOIN penalties p ON pp.penalty_id = p.id
        LEFT JOIN users u ON pp.user_id = u.id
        WHERE pp.tontine_id = :tontine_id
        AND pp.user_id = :user_id
        ORDER BY pp.payment_date DESC
        LIMIT :start, :perPage
    ");
    $stmt->bindValue(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);  // Filter by logged-in user
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check payment status for pending contributions and update status
    foreach ($payments as $payment) {
        $ref_id = $payment['transaction_ref'];
        $id = $payment['id'];
        $payment_status = $payment['payment_status'];
        $penalty_id = $payment['penalty_id'];

        if ($payment_status == "Pending") {
            // Fetch payment status from the payment gateway
            $paymentResponse = hdev_payment::get_pay($ref_id);

            if ($paymentResponse) {
                $status1 = $paymentResponse->status ?? null;

                // Map payment status from the gateway to database values
                $newStatus = match ($status1) {
                    'success' => "Approved",      // If the payment status is success
                    'failed' => "Failure",       // If the payment failed
                    'pending' => "Pending",      // If the payment is still pending
                    default => "Unknown",        // For any unexpected status
                };

                // Log unexpected statuses
                if ($newStatus === "Unknown") {
                    error_log("Unexpected payment status: " . $status1 . " for transaction ref: " . $ref_id);
                }

                // Update the payment status in the penalty_payment table
                $updateStmt = $pdo->prepare("
                    UPDATE penalty_payment
                    SET payment_status = :payment_status
                    WHERE id = :payment_id
                ");
                $updateStmt->bindValue(':payment_status', $newStatus);
                $updateStmt->bindValue(':payment_id', $id, PDO::PARAM_INT);

                try {
                    $updateStmt->execute();

                    if ($updateStmt->rowCount() === 0) {
                        error_log("No rows updated for penalty payment ID: " . $id);
                    }

                    // If the payment status is 'Approved', update the penalty table to 'Paid'
                    if ($newStatus == "Approved") {
                        // Update penalty status to 'Paid'
                        $updatePenaltyStmt = $pdo->prepare("
                            UPDATE penalties
                            SET status = 'Paid'
                            WHERE id = :penalty_id
                        ");
                        $updatePenaltyStmt->bindValue(':penalty_id', $penalty_id, PDO::PARAM_INT);
                        $updatePenaltyStmt->execute();
                    }
                } catch (PDOException $e) {
                    error_log("Database update error for penalty payment ID $id: " . $e->getMessage());
                }
            } else {
                error_log("Payment gateway response missing for transaction ref: " . $ref_id);
            }
        }
    }

    // Fetch tontine details
    $tontineStmt = $pdo->prepare("SELECT tontine_name FROM tontine WHERE id = :id");
    $tontineStmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $tontineStmt->execute();
    $tontine = $tontineStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Penalty Payments</title>
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
        }
        footer {
            margin-top: 50px;
            text-align: center;
            color: black;
            font-weight: bold;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
     <!-- Navbar -->
     <!-- Your Navbar HTML remains the same -->

    <!-- Main Content -->
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
                <!-- <li class="nav-item dropdown">
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
            </ul> -->
        </div>
    </nav>

    <div class="container mt-1">
        <h1 class="text-center">Your Penalty Payments for <?php echo htmlspecialchars($tontine['tontine_name']); ?></h1>
        <?php if (!empty($payments)): ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Penalty ID</th>
                    <th>User</th>
                    <th>Reason</th>
                    <th>Amount</th>
                    <th>Phone Number</th>
                    <th>Payment Status</th>                  
                    <th>Payment Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo htmlspecialchars($payment['penalty_id']); ?></td>
                    <td><?php echo htmlspecialchars($payment['firstname'] . ' ' . $payment['lastname']); ?></td>
                    <td><?php echo htmlspecialchars($payment['reason']); ?></td>
                    <td><?php echo htmlspecialchars($payment['amount']); ?></td>
                    <td><?php echo htmlspecialchars($payment['phone_number']); ?></td>
                    <td><?php echo htmlspecialchars($payment['payment_status']); ?></td>
                  
                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="text-center">
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= ceil($totalCount / $perPage); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php else: ?>
        <p class="text-center">No penalty payments found.</p>
        <?php endif; ?>
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
