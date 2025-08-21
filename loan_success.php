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

// Pagination setup for loan requests
$perPage = 5; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure the page number is at least 1
$start = ($page - 1) * $perPage;

try {
    // Fetch the total count of loan requests
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM loan_requests
        WHERE tontine_id = :tontine_id
        AND user_id = :user_id
    ");
    $countStmt->execute([
        'tontine_id' => $tontine_id,
        'user_id' => $_SESSION['user_id'],
    ]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch loan requests for the logged-in user in the given tontine with pagination
    $stmt = $pdo->prepare("
        SELECT lr.id, lr.loan_amount, lr.interest_rate, lr.interest_amount, lr.total_amount, 
               lr.payment_frequency, lr.payment_date, lr.status, lr.phone_number, lr.created_at, lr.late_loan_repayment_amount
        FROM loan_requests lr
        WHERE lr.tontine_id = :tontine_id
        AND lr.user_id = :user_id
        ORDER BY lr.created_at DESC
        LIMIT :start, :perPage
    ");
    $stmt->bindValue(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);  // Filter by logged-in user
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch tontine details
    $tontineStmt = $pdo->prepare("SELECT tontine_name FROM tontine WHERE id = :id");
    $tontineStmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $tontineStmt->execute();
    $tontine = $tontineStmt->fetch(PDO::FETCH_ASSOC);

    // Calculate total loan amount
    $totalLoans = array_sum(array_column($loans, 'total_amount'));

} catch (PDOException $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Loan Requests - <?php echo htmlspecialchars($tontine['tontine_name']); ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
             --primary-color: #1c64d1ff;
            --primary-hover: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --border-radius: 0.75rem;
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 14px;
        }

        /* Header Styles */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="0,0 1000,0 1000,100 0,20"/></svg>');
            background-size: cover;
        }

        .header-content {
            position: relative;
            z-index: 2;
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
        }

        .breadcrumb-item a:hover {
            color: white;
        }

        .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.9);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Stats Cards */
        .stats-section {
            margin-bottom: 2rem;
        }

        .stats-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            height: 100%;
        }

        .stats-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stats-card .icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stats-card.primary .icon {
            background: rgba(28, 100, 209, 0.1);
            color: var(--primary-color);
        }

        .stats-card.success .icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stats-card .value {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .stats-card .label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Loan Cards Layout */
        .loans-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (min-width: 992px) {
            .loans-grid {
                grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            }
        }

        .loan-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            overflow: hidden;
        }

        .loan-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* Loan Card Header */
        .loan-card-header {
            background: var(--light-bg);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .loan-id {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .loan-number {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .loan-date {
            text-align: right;
        }

        /* Loan Card Body */
        .loan-card-body {
            padding: 1.5rem;
        }

        .loan-detail-group {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .loan-detail {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .detail-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .detail-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.4;
        }

        .late-fee-notice {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        /* Loan Card Footer */
        .loan-card-footer {
            background: var(--light-bg);
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .total-amount {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .total-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .total-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .btn-pay-large {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.9375rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-pay-large:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        /* Remove old table styles */
        .table-container,
        .table-header,
        .table-responsive,
        .table {
            display: none;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-approved {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }

        .status-paid {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
        }

        /* Amount Display */
        .amount-display {
            font-weight: 600;
            color: var(--success-color);
            font-size: 0.9375rem;
        }

        .interest-rate {
            font-weight: 600;
            color: var(--warning-color);
            font-size: 0.9375rem;
        }

        /* Action Buttons */
        .btn-pay {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
            border: none;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-pay:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-pay i {
            font-size: 0.7rem;
        }

        /* Pagination */
        .pagination {
            justify-content: center;
            gap: 0.25rem;
        }

        .page-link {
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
        }

        .page-link:hover {
            background-color: var(--light-bg);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }

        .empty-state .icon {
            width: 4rem;
            height: 4rem;
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
        }

        .empty-state h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .loan-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .loan-date {
                text-align: left;
                width: 100%;
            }
            
            .loan-card-footer {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .total-amount {
                text-align: center;
                padding: 1rem;
                background: rgba(28, 100, 209, 0.05);
                border-radius: 0.5rem;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .btn-pay-large {
                flex: 1;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .page-header {
                padding: 1.5rem 0;
            }
            
            .page-title {
                font-size: 1.25rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .loan-card-body {
                padding: 1rem;
            }
            
            .loan-card-header {
                padding: 1rem;
            }
            
            .loan-card-footer {
                padding: 1rem;
            }
            
            .detail-value {
                font-size: 0.9375rem;
            }
            
            .total-value {
                font-size: 1.25rem;
            }
            
            .loan-detail-group {
                gap: 1rem;
            }
            
            .row > .col-md-6:first-child {
                margin-bottom: 1rem;
            }
        }

        /* Animation */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease-out;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="page-header">
        <div class="container">
            <div class="header-content">
                <!-- Breadcrumb Navigation -->
                <nav class="breadcrumb-nav">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="user_profile.php">
                                <i class="fas fa-home me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="tontines.php">Tontines</a>
                        </li>
                        <li class="breadcrumb-item active">Your Loan Requests</li>
                    </ol>
                </nav>

                <!-- Page Title -->
                <h1 class="page-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Your Loan Requests
                </h1>
                <p class="page-subtitle">
                    <?php echo htmlspecialchars($tontine['tontine_name']); ?> - Manage your loan applications
                </p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Statistics Section -->
        <?php if (!empty($loans)): ?>
            <div class="stats-section fade-in">
                <div class="row">
                    <div class="col-md-6 col-lg-4">
                        <div class="stats-card primary">
                            <div class="icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div class="value"><?php echo count($loans); ?></div>
                            <div class="label">Total Loan Requests</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="stats-card success">
                            <div class="icon">
                                RWF
                            </div>
                            <div class="value"><?php echo number_format($totalLoans, 2); ?></div>
                            <div class="label">Total Loan Amount</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loan Cards -->
            <div class="loans-grid fade-in">
                <?php foreach ($loans as $loan): ?>
                    <div class="loan-card">
                        <div class="loan-card-header">
                            <div class="loan-id">
                                <span class="loan-number">#<?php echo str_pad($loan['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                <?php 
                                $statusClass = 'status-pending';
                                $statusIcon = 'fas fa-clock';
                                if (strtolower($loan['status']) === 'approved') {
                                    $statusClass = 'status-approved';
                                    $statusIcon = 'fas fa-check-circle';
                                } elseif (strtolower($loan['status']) === 'rejected') {
                                    $statusClass = 'status-rejected';
                                    $statusIcon = 'fas fa-times-circle';
                                } elseif (strtolower($loan['status']) === 'paid') {
                                    $statusClass = 'status-paid';
                                    $statusIcon = 'fas fa-check-double';
                                }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <i class="<?php echo $statusIcon; ?>"></i>
                                    <?php echo htmlspecialchars($loan['status']); ?>
                                </span>
                            </div>
                            <div class="loan-date">
                                <small class="text-muted">
                                    Applied <?php echo date('M d, Y', strtotime($loan['created_at'])); ?>
                                </small>
                            </div>
                        </div>

                        <div class="loan-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="loan-detail-group">
                                        <div class="loan-detail">
                                            <div class="detail-label">
                                                <i class="fas fa-money-bill text-success"></i>
                                                Loan Amount
                                            </div>
                                            <div class="detail-value amount-display">
                                                RWF <?php echo number_format($loan['loan_amount'], 2); ?>
                                            </div>
                                        </div>

                                        <div class="loan-detail">
                                            <div class="detail-label">
                                                <i class="fas fa-percentage text-warning"></i>
                                                Interest Rate
                                            </div>
                                            <div class="detail-value interest-rate">
                                                <?php echo htmlspecialchars($loan['interest_rate']); ?>%
                                            </div>
                                        </div>

                                        <div class="loan-detail">
                                            <div class="detail-label">
                                                <i class="fas fa-calculator text-info"></i>
                                                Interest Amount
                                            </div>
                                            <div class="detail-value amount-display">
                                                RWF <?php echo number_format($loan['interest_amount'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="loan-detail-group">
                                        <div class="loan-detail">
                                            <div class="detail-label">
                                                <i class="fas fa-clock text-primary"></i>
                                                Payment Frequency
                                            </div>
                                            <div class="detail-value">
                                                <?php echo htmlspecialchars($loan['payment_frequency']); ?>
                                            </div>
                                        </div>

                                        <div class="loan-detail">
                                            <div class="detail-label">
                                                <i class="fas fa-calendar-alt text-primary"></i>
                                                Payment Date
                                            </div>
                                            <div class="detail-value">
                                                <?php echo date('M d, Y', strtotime($loan['payment_date'])); ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php 
                                                        $daysUntil = (new DateTime($loan['payment_date']))->diff(new DateTime())->days;
                                                        $isPast = new DateTime($loan['payment_date']) < new DateTime();
                                                        if ($isPast) {
                                                            echo $daysUntil . ' days overdue';
                                                        } else {
                                                            echo $daysUntil . ' days remaining';
                                                        }
                                                    ?>
                                                </small>
                                            </div>
                                        </div>

                                        <div class="loan-detail">
                                            <div class="detail-label">
                                                <i class="fas fa-phone text-secondary"></i>
                                                Phone Number
                                            </div>
                                            <div class="detail-value">
                                                <?php echo htmlspecialchars($loan['phone_number']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($loan['late_loan_repayment_amount'] > 0): ?>
                                <div class="late-fee-notice">
                                    <div class="alert alert-warning d-flex align-items-center mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <div>
                                            <strong>Late Fee:</strong> RWF <?php echo number_format($loan['late_loan_repayment_amount'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="loan-card-footer">
                            <div class="total-amount">
                                <div class="total-label">Total Amount Due</div>
                                <div class="total-value">
                                    RWF <?php echo number_format($loan['total_amount'] + ($loan['late_loan_repayment_amount'] ?? 0), 2); ?>
                                </div>
                            </div>
                            <div class="action-buttons">
                                <a href="pay_now.php?loan_id=<?php echo $loan['id']; ?>&amount=<?php echo $loan['total_amount']; ?>&payment_date=<?php echo urlencode($loan['payment_date']); ?>&late_amount=<?php echo $loan['late_loan_repayment_amount']; ?>&phone=<?php echo urlencode($loan['phone_number']); ?>&tontine_id=<?php echo $tontine_id; ?>" 
                                   class="btn-pay-large">
                                    <i class="fas fa-credit-card"></i>
                                    Pay Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if (ceil($totalCount / $perPage) > 1): ?>
                <div class="d-flex justify-content-center mt-4 fade-in">
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min(ceil($totalCount / $perPage), $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < ceil($totalCount / $perPage)): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $page + 1; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state fade-in">
                <div class="icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <h4>No Loan Requests Found!</h4>
                <p>You haven't made any loan requests for this tontine yet. Start by submitting your first loan application!</p>
                <div class="mt-3">
                    <a href="tontines.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Tontines
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Fade-in animation
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.fade-in').forEach(el => {
                observer.observe(el);
            });
        });

        // Enhanced payment confirmation
        document.querySelectorAll('.btn-pay').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const url = new URL(this.href);
                const amount = url.searchParams.get('amount');
                const lateAmount = url.searchParams.get('late_amount');
                const totalAmount = parseFloat(amount) + parseFloat(lateAmount || 0);
                
                Swal.fire({
                    title: 'Confirm Loan Payment',
                    html: `
                        <div class="text-start">
                            <p><strong>Loan Amount:</strong> RWF ${parseFloat(amount).toFixed(2)}</p>
                            ${lateAmount > 0 ? `<p><strong>Late Fee:</strong> RWF ${parseFloat(lateAmount).toFixed(2)}</p>` : ''}
                            <hr>
                            <p><strong>Total Amount:</strong> RWF ${totalAmount.toFixed(2)}</p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Pay Now',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = this.href;
                    }
                });
            });
        });

        // Tooltip initialization
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>