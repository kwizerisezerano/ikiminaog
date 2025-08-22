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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        :root {
            --primary-color: #2E86C1;
            --primary-light: #5DADE2;
            --primary-dark: #1B4F72;
            --primary-gradient: linear-gradient(135deg, #2E86C1 0%, #1B4F72 100%);
            --secondary-color: #F8FAFC;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --dark-color: #374151;
            --light-gray: #F3F4F6;
            --border-color: #E5E7EB;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #F8FAFC 0%, #E2E8F0 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            color: var(--dark-color);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .navbar {
            background: var(--primary-gradient);
            box-shadow: var(--shadow-lg);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.75rem;
            color: white !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .dashboard-container {
            margin-top: 2rem;
            margin-bottom: 3rem;
            max-width: 1200px;
        }
        
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .page-header h1 {
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header .text-muted {
            color: #6B7280 !important;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .payment-card {
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
            background: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
        }
        
        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .payment-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .payment-card:hover::before {
            transform: scaleX(1);
        }
        
        .card-header {
            background: linear-gradient(135deg, #F8FAFC 0%, white 100%);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            padding: 1.25rem 1.5rem;
            border-radius: 16px 16px 0 0 !important;
            color: var(--primary-dark);
            font-size: 1.1rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .payment-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: var(--light-gray);
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }
        
        .payment-detail:hover {
            background: #EDF2F7;
        }
        
        .payment-detail:last-child {
            margin-bottom: 0;
        }
        
        .detail-label {
            color: var(--dark-color);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-value {
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 1rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        .status-approved {
            background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
            color: #065F46;
            border: 1px solid #10B981;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            color: #92400E;
            border: 1px solid #F59E0B;
        }
        
        .status-failure {
            background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
            color: #991B1B;
            border: 1px solid #EF4444;
        }
        
        .pagination-container {
            margin-top: 2rem;
        }
        
        .page-link {
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            margin: 0 2px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .page-item.active .page-link {
            background: var(--primary-gradient);
            border-color: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }
        
        .empty-state .empty-icon {
            width: 100px;
            height: 100px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }
        
        .empty-state .empty-icon i {
            font-size: 3rem;
            color: white;
        }
        
        .empty-state h3 {
            color: var(--primary-dark);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #6B7280;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .back-button {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        .back-button:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .currency-symbol {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .payment-id {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                margin-top: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .payment-detail {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .detail-value {
                font-size: 1.1rem;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .status-badge {
                align-self: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
        }
        
        /* Loading animation for status updates */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .status-updating {
            animation: pulse 2s infinite;
        }
        
        /* Smooth transitions */
        * {
            transition: color 0.2s ease, background-color 0.2s ease;
        }
    </style>
</head>
<body>
    

    <div class="container dashboard-container">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>
                        <i class="fas fa-file-invoice-dollar"></i>
                        Your Loan Payments
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-users mr-2"></i>
                        For: <strong><?php echo htmlspecialchars($tontine['tontine_name']); ?></strong>
                    </p>
                </div>
                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                    <a class="back-button" href="tontine_profile_member.php?id=<?php echo $tontine_id; ?>">
                        <i class="fas fa-arrow-left"></i>
                        Back to Tontine Profile
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($loanPayments)): ?>
            <?php 
            $paymentNumber = ($page - 1) * $perPage + 1; // Start counting from 1 based on pagination
            foreach ($loanPayments as $payment): 
                // Determine status class
                $statusClass = '';
                $statusIcon = '';
                if ($payment['payment_status'] == 'Approved') {
                    $statusClass = 'status-approved';
                    $statusIcon = 'fas fa-check-circle';
                } elseif ($payment['payment_status'] == 'Pending') {
                    $statusClass = 'status-pending';
                    $statusIcon = 'fas fa-clock';
                } else {
                    $statusClass = 'status-failure';
                    $statusIcon = 'fas fa-times-circle';
                }
            ?>
                <div class="card payment-card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <span class="payment-id">
                            <i class="fas fa-hashtag mr-1"></i>
                            Payment #<?php echo $paymentNumber; ?>
                        </span>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <i class="<?php echo $statusIcon; ?>"></i>
                            <?php echo htmlspecialchars($payment['payment_status']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="payment-detail">
                            <span class="detail-label">
                                <i class="fas fa-money-bill-wave"></i>
                                Loan Amount:
                            </span>
                            <span class="detail-value">
                                <span class="currency-symbol">RWF</span> <?php echo number_format($payment['loan_amount']); ?>
                            </span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">
                                <i class="fas fa-percentage"></i>
                                Interest Rate:
                            </span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['interest_rate']); ?>%</span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">
                                <i class="fas fa-calculator"></i>
                                Total Amount:
                            </span>
                            <span class="detail-value">
                                <span class="currency-symbol">RWF</span> <?php echo number_format($payment['total_amount']); ?>
                            </span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">
                                <i class="fas fa-calendar-check"></i>
                                Monthly Payment:
                            </span>
                            <span class="detail-value">
                                <span class="currency-symbol">RWF</span> <?php echo number_format($payment['amount']); ?>
                            </span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">
                                <i class="fas fa-calendar-alt"></i>
                                Payment Date:
                            </span>
                            <span class="detail-value"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></span>
                        </div>
                        <div class="payment-detail">
                            <span class="detail-label">
                                <i class="fas fa-phone"></i>
                                Phone Number:
                            </span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['phone_number']); ?></span>
                        </div>
                    </div>
                </div>
            <?php 
                $paymentNumber++; // Increment payment number for next iteration
            endforeach; ?>

            <!-- Pagination -->
            <?php if (ceil($totalCount / $perPage) > 1): ?>
                <div class="pagination-container">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left mr-1"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= ceil($totalCount / $perPage); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < ceil($totalCount / $perPage)): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $page + 1; ?>">
                                        Next <i class="fas fa-chevron-right ml-1"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <h3>No Loan Payments Found</h3>
                <p>You haven't made any loan payments for this tontine yet. Once you start making payments, they will appear here.</p>
                <a href="tontines.php" class="btn btn-primary btn-lg">
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
                confirmButtonColor: '#2E86C1',
                cancelButtonColor: '#EF4444',
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'No, stay logged in',
                reverseButtons: true,
                customClass: {
                    popup: 'animated fadeInUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';

        // Add loading animation for any dynamic status updates
        document.addEventListener('DOMContentLoaded', function() {
            const statusBadges = document.querySelectorAll('.status-pending');
            statusBadges.forEach(badge => {
                badge.classList.add('status-updating');
            });
        });

        // Optional: Auto-refresh pending payments every 30 seconds
        setInterval(function() {
            const pendingPayments = document.querySelectorAll('.status-pending');
            if (pendingPayments.length > 0) {
                // Only refresh if there are pending payments
                // Uncomment the line below if you want auto-refresh
                // location.reload();
            }
        }, 30000);
    </script>
</body>
</html>