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
    // Fetch the total count of loan payments for the given tontine_id and user_id
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM loan_payments lp
        JOIN loan_requests lr ON lp.loan_id = lr.id
        WHERE lr.tontine_id = :tontine_id AND lp.user_id = :user_id
    ");
    $countStmt->execute([
        'tontine_id' => $tontine_id,
        'user_id' => $_SESSION['user_id'],
    ]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch loan payments and associated loan request details
    $stmt = $pdo->prepare("
        SELECT lp.id, lp.amount, lp.payment_date, lp.payment_status, lp.transaction_ref, lp.phone_number,
               lr.loan_amount, lr.interest_rate, lr.total_amount, lr.payment_frequency, lr.status AS loan_status,
               lr.created_at AS loan_created_at
        FROM loan_payments lp
        JOIN loan_requests lr ON lp.loan_id = lr.id
        WHERE lr.tontine_id = :tontine_id AND lp.user_id = :user_id
        ORDER BY lp.payment_date DESC
        LIMIT :start, :perPage
    ");
    $stmt->bindValue(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $loanPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch tontine details
    $tontineStmt = $pdo->prepare("SELECT tontine_name FROM tontine WHERE id = :id");
    $tontineStmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $tontineStmt->execute();
    $tontine = $tontineStmt->fetch(PDO::FETCH_ASSOC);

    // Loop through loan payments and check payment status for any "Pending" contributions
    foreach ($loanPayments as $payment) {
        $ref_id = $payment['transaction_ref'];
        $id = $payment['id'];
        $payment_status = $payment['payment_status'];

        if ($payment_status == "Pending") {
            // Fetch payment status from the payment gateway
            $paymentResponse = hdev_payment::get_pay($ref_id);

            if ($paymentResponse) {
                $status1 = $paymentResponse->status ?? null;

                // Map payment status from the gateway to database values
                $newStatus = match ($status1) {
                    'success' => "Approved",
                    'failed' => "Failure",
                    'pending', 'initiated' => "Pending",
                    default => "Unknown", // Handle unexpected statuses
                };

                // Log unexpected statuses
                if ($newStatus === "Unknown") {
                    error_log("Unexpected payment status: " . $status1 . " for transaction ref: " . $ref_id);
                }

                // Update the payment status in the database
                $updateStmt = $pdo->prepare("
                    UPDATE loan_payments
                    SET payment_status = :payment_status 
                    WHERE id = :id
                ");
                $updateStmt->bindValue(':payment_status', $newStatus);
                $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);

                try {
                    $updateStmt->execute();

                    if ($updateStmt->rowCount() === 0) {
                        error_log("No rows updated for loan payment ID: " . $id);
                    }
                } catch (PDOException $e) {
                    error_log("Database update error for ID $id: " . $e->getMessage());
                }
            } else {
                error_log("Payment gateway response missing for transaction ref: " . $ref_id);
            }
        }
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
    <title>Your Loan Payments</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #5a5c69;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        
        .navbar {
            background: linear-gradient(90deg, var(--primary-color) 0%, #2a3e9d 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .dashboard-container {
            margin-top: 30px;
            margin-bottom: 50px;
        }
        
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .payment-card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            border: none;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .payment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .payment-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #e3e6f0;
        }
        
        .payment-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            text-align: right;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-approved {
            background-color: rgba(28, 200, 138, 0.2);
            color: var(--success-color);
        }
        
        .status-pending {
            background-color: rgba(246, 194, 62, 0.2);
            color: var(--warning-color);
        }
        
        .status-failure {
            background-color: rgba(231, 74, 59, 0.2);
            color: var(--danger-color);
        }
        
        .pagination-container {
            margin-top: 30px;
        }
        
        .page-link {
            color: var(--primary-color);
            border: 1px solid #ddd;
            padding: 0.5rem 0.75rem;
        }
        
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            color: #999;
            font-size: 1.2rem;
            margin-bottom: 25px;
        }
        
        .back-button {
            background-color: #fff;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 8px 20px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            background-color: var(--primary-color);
            color: #fff;
        }
        
        footer {
            margin-top: 50px;
            text-align: center;
            color: var(--dark-color);
            font-weight: 500;
            font-size: 0.9rem;
            padding: 20px 0;
            border-top: 1px solid #e3e6f0;
        }
        
        @media (max-width: 768px) {
            .payment-detail {
                flex-direction: column;
            }
            
            .detail-value {
                text-align: left;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
   

    <div class="container dashboard-container">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="h3 mb-0">Your Loan Payments</h1>
                    <p class="mb-0 text-muted">For: <?php echo htmlspecialchars($tontine['tontine_name']); ?></p>
                </div>
                <div class="col-md-6 text-md-right">
                    <a href="tontines.php" class="back-button">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Tontines
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($loanPayments)): ?>
            <?php foreach ($loanPayments as $payment): 
                // Determine status class
                $statusClass = '';
                if ($payment['payment_status'] == 'Approved') {
                    $statusClass = 'status-approved';
                } elseif ($payment['payment_status'] == 'Pending') {
                    $statusClass = 'status-pending';
                } else {
                    $statusClass = 'status-failure';
                }
            ?>
                <div class="card payment-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Payment #<?php echo htmlspecialchars($payment['id']); ?></span>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo htmlspecialchars($payment['payment_status']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="payment-detail">
                            <span class="detail-label">Loan Amount:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['loan_amount']); ?></span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">Interest Rate:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['interest_rate']); ?>%</span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">Total Amount:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['total_amount']); ?></span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">Monthly Payment:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['amount']); ?></span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">Payment Date:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['payment_date']); ?></span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">Phone Number:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['phone_number']); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if (ceil($totalCount / $perPage) > 1): ?>
                <div class="pagination-container">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= ceil($totalCount / $perPage); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice-dollar"></i>
                <p>No loan payments found for this tontine.</p>
                <a href="tontines.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Tontines
                </a>
            </div>
        <?php endif; ?>
    </div>

    

    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure you want to log out?',
                text: "You will be logged out of your account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4e73df',
                cancelButtonColor: '#d33',
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