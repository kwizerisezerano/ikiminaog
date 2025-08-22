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
    // Fetch the total count of distinct missed contributions (one per user and missed date)
    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT mc.user_id, mc.missed_date) AS total
        FROM missed_contributions mc
        JOIN users u ON mc.user_id = u.id
        WHERE mc.tontine_id = :tontine_id
    ");
    $countStmt->execute([
        'tontine_id' => $tontine_id,
    ]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch missed contributions and associated payment details
    $stmt = $pdo->prepare("
        SELECT mc.id, mc.missed_date, mc.missed_amount, mc.status, u.phone_number, u.firstname, u.lastname, u.id as user_id,
               mcp.payment_status, mcp.transaction_ref
        FROM missed_contributions mc
        LEFT JOIN users u ON mc.user_id = u.id
        LEFT JOIN missed_contribution_payment mcp ON mc.id = mcp.missed_id
        WHERE mc.tontine_id = :tontine_id
        AND mc.user_id = :user_id
        AND mc.id IN (
            SELECT MAX(mc.id) FROM missed_contributions mc 
            WHERE mc.tontine_id = :tontine_id
            GROUP BY mc.user_id, mc.missed_date
        )
        ORDER BY mc.missed_date DESC
        LIMIT :start, :perPage
    ");
    $stmt->bindValue(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);  // Filter by logged-in user
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check payment status for pending contributions and update status
    foreach ($contributions as $contribution) {
        $ref_id = $contribution['transaction_ref'];
        $id = $contribution['id'];
        $payment_status = $contribution['payment_status'];

        if ($payment_status == "Pending") {
            // Fetch payment status from the payment gateway
            $paymentResponse = hdev_payment::get_pay($ref_id);

            if ($paymentResponse) {
                $status1 = $paymentResponse->status ?? null;

                // Map payment status from the gateway to database values
                $newStatus = match ($status1) {
                    'success' => "Approved",      // If the payment status is success
                    'failed' => "Failure",       // If the payment failed
                    'pending', 'initiated' => "Pending",  // If the payment is still pending
                    default => "Unknown",        // For any unexpected status
                };

                // Log unexpected statuses
                if ($newStatus === "Unknown") {
                    error_log("Unexpected payment status: " . $status1 . " for transaction ref: " . $ref_id);
                }

                // Update the payment status in the missed_contribution_payment table
                $updateStmt = $pdo->prepare("
                    UPDATE missed_contribution_payment
                    SET payment_status = :payment_status
                    WHERE missed_id = :missed_id
                ");
                $updateStmt->bindValue(':payment_status', $newStatus);
                $updateStmt->bindValue(':missed_id', $id, PDO::PARAM_INT);

                try {
                    $updateStmt->execute();

                    if ($updateStmt->rowCount() === 0) {
                        error_log("No rows updated for missed contribution ID: " . $id);
                    }

                    // If the payment status is 'Approved', update the missed_contributions table to 'Paid'
                    if ($newStatus == "Approved") {
                        $updateContributionStmt = $pdo->prepare("
                            UPDATE missed_contributions
                            SET status = 'Paid'
                            WHERE id = :missed_id
                        ");
                        $updateContributionStmt->bindValue(':missed_id', $id, PDO::PARAM_INT);
                        $updateContributionStmt->execute();
                    }
                } catch (PDOException $e) {
                    error_log("Database update error for missed contribution ID $id: " . $e->getMessage());
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
    <title>Your Missed Contributions</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --dark-text: #1e293b;
            --light-text: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--light-bg) 0%, #e2e8f0 100%);
            font-family: 'Inter', sans-serif;
            color: var(--dark-text);
            min-height: 100vh;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="white" opacity="0.1"/><circle cx="90" cy="20" r="1.5" fill="white" opacity="0.1"/><circle cx="20" cy="80" r="1" fill="white" opacity="0.1"/><circle cx="80" cy="90" r="1.5" fill="white" opacity="0.1"/></svg>');
            pointer-events: none;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 1.5rem;
        }

        .tontine-name {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .content-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-container {
            overflow-x: auto;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .modern-table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        }

        .modern-table thead th {
            color: white;
            font-weight: 600;
            padding: 1.25rem 1rem;
            text-align: left;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            position: relative;
        }

        .modern-table thead th:first-child {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .modern-table thead th:last-child {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .modern-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }

        .modern-table tbody tr:hover {
            background-color: #f1f5f9;
            transform: translateY(-1px);
        }

        .modern-table tbody tr:last-child {
            border-bottom: none;
        }

        .modern-table tbody td {
            padding: 1.25rem 1rem;
            font-size: 0.875rem;
            color: var(--dark-text);
            vertical-align: middle;
        }

        .row-number {
            background: var(--primary-color);
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-approved {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-failure {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-unknown {
            background-color: #f3f4f6;
            color: #374151;
        }

        .amount-cell {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
            .breadcrumb-nav {
            margin-bottom: 1rem;
        }

        .breadcrumb {
            background: transparent;
            margin-bottom: 0;
            padding: 0;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            font-size:1rem;
        }

        .breadcrumb-item a:hover {
            color: white;
        }

        .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.9);
        }
        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }

        .modern-pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .modern-pagination .page-link {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            color: var(--secondary-color);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            min-width: 2.75rem;
            text-align: center;
        }

        .modern-pagination .page-link:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-1px);
        }

        .modern-pagination .page-link.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--light-text);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        .empty-state p {
            font-size: 1rem;
            max-width: 400px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem 0.5rem;
            }

            .header-section {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
                flex-direction: column;
                text-align: center;
            }

            .modern-table thead th,
            .modern-table tbody td {
                padding: 0.75rem 0.5rem;
                font-size: 0.75rem;
            }

            .user-info {
                flex-direction: column;
                text-align: center;
                gap: 0.25rem;
            }

            .user-avatar {
                width: 2rem;
                height: 2rem;
                font-size: 0.75rem;
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    
        <!-- Header Section -->
        <div class="header-section fade-in">
            
               
            <div class="header-content">
                <nav class="breadcrumb-nav">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="user_profile.php">
                                <i class="fas fa-home me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="tontine_profile_member.php?id=<?php echo $tontine_id; ?>">Tontine Profile</a>
                        </li>
                  
                    </ol>
                </nav> 
                <h1 class="page-title">
                        
                    <div>
                        <div>Your Missed Contributions</div>
                        <div class="tontine-name"><?php echo htmlspecialchars($tontine['tontine_name'] ?? 'Unknown Tontine'); ?></div>
                    </div>
                </h1>
            </div>
        </div>

        <!-- Content Card -->
        <div class="content-card fade-in">
            <?php if (!empty($contributions)): ?>
                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID</th>
                                <th>User</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Phone Number</th>
                                <th>Payment Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rowNumber = $start + 1; // Start numbering from the correct position for pagination
                            foreach ($contributions as $contribution): 
                            ?>
                            <tr>
                                <td>
                                    <div class="row-number"><?php echo $rowNumber++; ?></div>
                                </td>
                                <td><strong>#<?php echo htmlspecialchars($contribution['id']); ?></strong></td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php 
                                            $initials = strtoupper(substr($contribution['firstname'], 0, 1) . substr($contribution['lastname'], 0, 1));
                                            echo htmlspecialchars($initials); 
                                            ?>
                                        </div>
                                        <div>
                                            <div><strong><?php echo htmlspecialchars($contribution['firstname'] . ' ' . $contribution['lastname']); ?></strong></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('M d, Y', strtotime($contribution['missed_date'])); ?>
                                </td>
                                <td>
                                    <div class="amount-cell">
                                        <i class="fas fa-dollar-sign"></i>
                                        <?php echo htmlspecialchars(number_format($contribution['missed_amount'], 2)); ?>
                                    </div>
                                </td>
                                <td>
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($contribution['phone_number']); ?>
                                </td>
                                <td>
                                    <?php 
                                    $status = strtolower($contribution['payment_status'] ?? 'unknown');
                                    $statusClass = match($status) {
                                        'approved' => 'status-approved',
                                        'pending' => 'status-pending',
                                        'failure', 'failed' => 'status-failure',
                                        default => 'status-unknown'
                                    };
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($contribution['payment_status'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (ceil($totalCount / $perPage) > 1): ?>
                <div class="pagination-container">
                    <div class="modern-pagination">
                        <?php for ($i = 1; $i <= ceil($totalCount / $perPage); $i++): ?>
                            <a href="?id=<?php echo $tontine_id; ?>&page=<?php echo $i; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Missed Contributions Found</h3>
                    <p>You don't have any missed contributions recorded for this tontine yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure you want to log out?',
                text: "You will be logged out of your account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'No, stay logged in',
                reverseButtons: true,
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger me-2',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Add loading animation
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>