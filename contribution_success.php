<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get the logged-in user's ID and the Tontine ID from the URL or POST
$user_id = $_SESSION['user_id'];
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
    // Fetch the total count of contributions
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS total 
        FROM contributions 
        WHERE user_id = :user_id AND tontine_id = :tontine_id
    ");
    $countStmt->execute([
        'user_id' => $user_id,
        'tontine_id' => $tontine_id,
    ]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch contributions with pagination
    $stmt = $pdo->prepare("
        SELECT id, amount, payment_method, transaction_ref, contribution_date, payment_status
        FROM contributions 
        WHERE user_id = :user_id AND tontine_id = :tontine_id
        ORDER BY contribution_date DESC
        LIMIT :start, :perPage
    ");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch tontine details
    $tontineStmt = $pdo->prepare("SELECT tontine_name FROM tontine WHERE id = :id");
    $tontineStmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $tontineStmt->execute();
    $tontine = $tontineStmt->fetch(PDO::FETCH_ASSOC);

    // Check payment status for pending contributions
    foreach ($contributions as $joinRequest) {
        $ref_id = $joinRequest['transaction_ref'];
        $id = $joinRequest['id'];
        $payment_status = $joinRequest['payment_status'];

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
                    UPDATE contributions
                    SET payment_status = :payment_status 
                    WHERE id = :id
                ");
                $updateStmt->bindValue(':payment_status', $newStatus);
                $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);

                try {
                    $updateStmt->execute();

                    if ($updateStmt->rowCount() === 0) {
                        error_log("No rows updated for contribution ID: " . $id);
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

// Fetch user details
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, image, idno, behalf_name, behalf_phone_number, idno_picture, otp_behalf_used FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: index.php");
    exit();
}

// Sanitize user data
$otp_behalf_used = $user['otp_behalf_used'];
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);

// Notification count (Adjust this as needed)
$total_notifications = 5;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Contributions</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
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

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .container {
            margin-top: 40px;
            margin-bottom: 40px;
        }

        /* Professional Header Section */
        .page-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><radialGradient id="a" cx="50%" cy="0%" r="100%"><stop offset="0%" stop-color="white" stop-opacity="0.1"/><stop offset="100%" stop-color="white" stop-opacity="0"/></radialGradient></defs><rect width="100%" height="100%" fill="url(%23a)"/></svg>');
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .page-header .subtitle {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        /* Professional Table Styling */
        .contributions-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: none;
        }

        .table-container {
            padding: 0;
            border-radius: 15px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            color: white;
            border: none;
            padding: 1.2rem 1rem;
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            position: relative;
        }

        .table thead th:first-child {
            border-top-left-radius: 0;
        }

        .table thead th:last-child {
            border-top-right-radius: 0;
        }

        .table tbody td {
            padding: 1.2rem 1rem;
            border-top: 1px solid #e9ecef;
            border-left: none;
            border-right: none;
            vertical-align: middle;
            font-size: 0.95rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9ff;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.1);
        }

        /* Status Badge Styling */
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-approved {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .status-pending {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }

        .status-failure {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
        }

        /* Amount Styling */
        .amount-display {
            font-weight: 600;
            color: #007bff;
            font-size: 1rem;
        }

        /* Professional Pagination */
        .pagination-container {
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .pagination .page-link {
            border: none;
            padding: 0.75rem 1rem;
            margin: 0 0.2rem;
            border-radius: 8px;
            color: #007bff;
            font-weight: 500;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .pagination .page-link:hover {
            background: #007bff;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        }

        /* Empty State Styling */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: #007bff;
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .empty-state h3 {
            color: #495057;
            margin-bottom: 1rem;
            font-weight: 300;
        }

        .empty-state p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        /* Transaction Reference Styling */
        .transaction-ref {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #495057;
            border: 1px solid #e9ecef;
        }

        /* Row Number Styling */
        .row-number {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .table tbody td {
                padding: 0.8rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .table thead th {
                padding: 1rem 0.5rem;
                font-size: 0.85rem;
            }
        }

        footer {
            margin-top: 50px;
            text-align: center;
            color: #495057;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 2rem 0;
            background: white;
            border-top: 1px solid #e9ecef;
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
                <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="paymentsDropdown" data-toggle="dropdown">
                    Tontine
                </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="create_tontine.php">Create tontine</a>
                    <a class="dropdown-item" href="own_tontine.php">Tontine you Own</a>
                    <a class="dropdown-item" href="joined_tontine.php">List of Ibimina you have joined</a>
                </div>
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
            <li class="nav-item">
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

    <!-- Main Content -->
    <div class="container">
        <!-- Professional Header -->
        <div class="page-header">
            <div class="text-center">
                <h1><i class="fas fa-chart-line mr-3"></i>Contribution History</h1>
                <p class="subtitle">Track your contributions for <strong><?php echo htmlspecialchars($tontine['tontine_name']); ?></strong></p>
            </div>
        </div>

        <?php if (!empty($contributions)): ?>
            <div class="contributions-card">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag mr-2"></i>#</th>
                                <th><i class="fas fa-calendar mr-2"></i>Date</th>
                                <th><i class="fas fa-money-bill-wave mr-2"></i>Amount</th>
                                <th><i class="fas fa-credit-card mr-2"></i>Payment Method</th>                             
                                <th><i class="fas fa-info-circle mr-2"></i>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = ($page - 1) * $perPage + 1; // Start counting based on pagination
                            foreach ($contributions as $contribution): 
                            ?>
                                <tr>
                                    <td>
                                        <span class="row-number"><?php echo $counter++; ?></span>
                                    </td>
                                    <td>
                                        <i class="fas fa-calendar-alt text-primary mr-2"></i>
                                        <?php echo date('M d, Y', strtotime($contribution['contribution_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="amount-display">
                                            RWF <?php echo number_format($contribution['amount'], 0); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-credit-card text-secondary mr-2"></i>
                                        <?php echo htmlspecialchars($contribution['payment_method']); ?>
                                    </td>
                                  
                                    <td>
                                        <?php 
                                        $status = strtolower($contribution['payment_status']);
                                        $statusClass = '';
                                        $statusIcon = '';
                                        
                                        switch($status) {
                                            case 'approved':
                                                $statusClass = 'status-approved';
                                                $statusIcon = 'fas fa-check-circle';
                                                break;
                                            case 'pending':
                                                $statusClass = 'status-pending';
                                                $statusIcon = 'fas fa-clock';
                                                break;
                                            case 'failure':
                                                $statusClass = 'status-failure';
                                                $statusIcon = 'fas fa-times-circle';
                                                break;
                                            default:
                                                $statusClass = 'status-pending';
                                                $statusIcon = 'fas fa-question-circle';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <i class="<?php echo $statusIcon; ?> mr-1"></i>
                                            <?php echo htmlspecialchars($contribution['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Professional Pagination -->
            <?php
                $totalPages = ceil($totalCount / $perPage);
                if ($totalPages > 1):
            ?>
            <div class="pagination-container">
                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Contributions Found</h3>
                <p>You haven't made any contributions to this tontine yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, logout!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>
</body>
</html>