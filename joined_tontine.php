<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);

// Pagination settings
$limit = 5; // Results per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// First, update payment statuses by checking with payment gateway
$pendingPaymentsStmt = $pdo->prepare("
    SELECT tjr.transaction_ref, tjr.id, tjr.payment_status 
    FROM tontine_join_requests tjr
    WHERE tjr.user_id = :user_id AND tjr.payment_status = 'Pending'
");
$pendingPaymentsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$pendingPaymentsStmt->execute();
$pendingPayments = $pendingPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pendingPayments as $payment) {
    $ref_id = $payment['transaction_ref'];
    $id = $payment['id'];

    // Only check payment status if transaction_ref exists
    if (!empty($ref_id)) {
        try {
            // Fetch payment status from the payment gateway
            $paymentResponse = hdev_payment::get_pay($ref_id);

            // Check if the gateway response contains a status field
            if (isset($paymentResponse->status)) {
                $status1 = $paymentResponse->status;

                // Map payment status from the gateway to database values
                $newPaymentStatus = match ($status1) {
                    'success' => "Approved",
                    'failed' => "Failure",
                    default => "Pending",
                };

                // Update payment status in the database only if it changed
                if ($newPaymentStatus !== $payment['payment_status']) {
                    $updatePaymentStmt = $pdo->prepare("
                        UPDATE tontine_join_requests 
                        SET payment_status = :payment_status 
                        WHERE id = :id
                    ");
                    $updatePaymentStmt->bindValue(':payment_status', $newPaymentStatus);
                    $updatePaymentStmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $updatePaymentStmt->execute();
                }
            }
        } catch (Exception $e) {
            // Log error but continue processing
            error_log("Payment gateway error for transaction {$ref_id}: " . $e->getMessage());
        }
    }
}

// Now update all records where payment_status is "Approved" but status is not "Permitted"
$updateStatusStmt = $pdo->prepare("
    UPDATE tontine_join_requests 
    SET status = 'Permitted' 
    WHERE user_id = :user_id 
    AND LOWER(payment_status) = 'approved' 
    AND LOWER(status) != 'permitted'
");
$updateStatusStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$updateStatusResult = $updateStatusStmt->execute();

// Debug: Check how many rows were affected
$rowsAffected = $updateStatusStmt->rowCount();

// Fetch join requests where the logged-in user has joined tontines
// Added DISTINCT to avoid duplicate records and ensure we get the latest data
$joinRequestsStmt = $pdo->prepare("
    SELECT DISTINCT tjr.transaction_ref, tjr.id, tjr.number_place, tjr.amount, 
           tjr.payment_method, tjr.status, tjr.request_date, t.tontine_name, 
           tjr.reason, tjr.payment_status
    FROM tontine_join_requests tjr
    JOIN tontine t ON tjr.tontine_id = t.id
    WHERE tjr.user_id = :user_id
    ORDER BY tjr.request_date DESC, tjr.id DESC
    LIMIT :limit OFFSET :offset
");
$joinRequestsStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$joinRequestsStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$joinRequestsStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$joinRequestsStmt->execute();
$joinRequests = $joinRequestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total number of DISTINCT join requests for pagination calculation
$totalRequestsStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT tjr.id) 
    FROM tontine_join_requests tjr 
    WHERE tjr.user_id = :user_id
");
$totalRequestsStmt->execute(['user_id' => $user_id]);
$totalRequests = $totalRequestsStmt->fetchColumn();
$totalPages = ceil($totalRequests / $limit); // Total pages for pagination

$total_notifications = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Joined Tontines</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { 
            margin-top: 30px;
            width: 100%;
            max-width: 100%;
        }
        .table-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -0px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 0.80rem;
        }
        .status-permitted {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .status-pending {
            background-color: #ffc107;
            color: #212529;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .status-rejected {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .payment-approved {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        .payment-pending {
            background-color: #ffc107;
            color: #212529;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        .payment-failure {
            background-color: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        .refresh-notice {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
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
            
            </li>
            <li class="nav-item dropdown" hidden>
                <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="contributionsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Contributions
                </a>
                <div class="dropdown-menu" aria-labelledby="contributionsDropdown">
                    <a class="dropdown-item" href="#">Send contributions</a>
                    <a class="dropdown-item" href="#">View Total Contributions</a>
                </div>
            </li>
            <li class="nav-item dropdown" hidden>
                <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="loansDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Loans
                </a>
                <div class="dropdown-menu" aria-labelledby="loansDropdown">
                    <a class="dropdown-item" href="#">View loan status</a>
                    <a class="dropdown-item" href="#">Apply for loan</a>
                    <a class="dropdown-item" href="#">Pay for loan</a>
                </div>
            </li>
            <li class="nav-item dropdown" hidden>
                <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="penaltiesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Penalties
                </a>
                <div class="dropdown-menu" aria-labelledby="penaltiesDropdown">
                    <a class="dropdown-item" href="#">View Paid Penalties</a>
                    <a class="dropdown-item" href="#">View Unpaid Penalties</a>
                    <a class="dropdown-item" href="#">Pay Penalties</a>
                </div>
            </li>
            <li class="nav-item" hidden>
                <a class="nav-link font-weight-bold text-white" href="#">Notifications</a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
             <li class="nav-item">
                <a class="nav-link font-weight-bold text-white d-flex align-items-center" href="#" style="gap: 8px;">
                    <div style="background-color: #ffffff; color: #007bff; font-weight: bold; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 1rem; text-transform: uppercase;">
                        <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                    </div>
                    <?php echo htmlspecialchars($user_name); ?>
                </a>
            </li>
            <li class="nav-item" >
                <a class="nav-link position-relative font-weight-bold text-white" href="#">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $total_notifications; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold text-white" href="setting.php">
                    <i class="fas fa-cog"></i>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold text-white" href="#" onclick="confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i> Log Out
                </a>
            </li>
        </ul>
    </div>
</nav>
    <div class="container ">
        <h4 class="text-center">Tontines joined</h4>
        
        <?php if ($rowsAffected > 0): ?>
        <div class="refresh-notice">
            <i class="fas fa-info-circle"></i> <?php echo $rowsAffected; ?> record(s) were automatically updated based on payment status.
        </div>
        <?php endif; ?>
        
        <div class="table-container">
            <?php if (!empty($joinRequests)): ?>
                <div class="mb-3">
                    <button onclick="location.reload()" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-sync-alt"></i> Refresh Status
                    </button>
                </div>
                
                <table class="table table-bordered ">
                    <thead>
                        <tr class="table-primary">
                            <th>Tontine</th>
                            <th>Number of Places</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>                           
                            <th>Reason</th>
                            <th>Payment Status</th>
                            <th>Request Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($joinRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['tontine_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['number_place']); ?></td>
                                <td><?php echo number_format($request['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($request['payment_method']); ?></td>
                                <td>
                                    <?php 
                                    $status = htmlspecialchars($request['status']);
                                    $statusClass = match(strtolower($status)) {
                                        'permitted' => 'status-permitted',
                                        'pending' => 'status-pending',
                                        'rejected' => 'status-rejected',
                                        default => 'status-pending'
                                    };
                                    ?>
                                    <span class="<?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($request['reason'] ?? 'N/A');?></td>
                                <td>
                                    <?php 
                                    $paymentStatus = htmlspecialchars($request['payment_status']);
                                    $paymentClass = match(strtolower($paymentStatus)) {
                                        'approved' => 'payment-approved',
                                        'pending' => 'payment-pending',
                                        'failure' => 'payment-failure',
                                        default => 'payment-pending'
                                    };
                                    ?>
                                    <span class="<?php echo $paymentClass; ?>"><?php echo $paymentStatus; ?></span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($request['request_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination Controls -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation example">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page == 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="joined_tontine.php?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="joined_tontine.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page == $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="joined_tontine.php?page=<?php echo $page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No tontines joined yet</h5>
                    <p class="text-muted">Start by exploring available tontines to join!</p>
                    <a href="browse_tontines.php" class="btn btn-primary">Browse Tontines</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to log out?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Auto-refresh page every 30 seconds to check for payment status updates
        let refreshInterval = setInterval(function() {
            // Only refresh if there are pending payments
            const pendingPayments = document.querySelectorAll('.payment-pending');
            if (pendingPayments.length > 0) {
                console.log('Refreshing page to check payment status...');
                location.reload();
            } else {
                // Clear interval if no pending payments
                clearInterval(refreshInterval);
                console.log('No pending payments found, stopping auto-refresh.');
            }
        }, 30000);

        // Clear interval when page is about to unload
        window.addEventListener('beforeunload', function() {
            clearInterval(refreshInterval);
        });
    </script>
</body>
</html>