<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user ID from session
$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, image, idno, behalf_name, behalf_phone_number, idno_picture, otp_behalf_used FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: index.php");
    exit();
}
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);

// Get tontine ID from the URL
$tontine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch tontine details
$stmt = $pdo->prepare("SELECT * FROM tontine WHERE id = :id");
$stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if tontine exists
if (!$tontine) {
    die("Tontine not found.");
}

// Get summary statistics for the specific tontine

// Total users who joined the Tontine
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_users FROM tontine_join_requests WHERE tontine_id = :tontine_id AND status = 'Permitted'");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$total_users = $stmt->fetchColumn();

// Total contributions (sum of the 'amount' field)
$stmt = $pdo->prepare("SELECT SUM(amount) AS total_contributions FROM contributions WHERE tontine_id = :tontine_id AND payment_status='Approved'");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$total_contributions = $stmt->fetchColumn();

// Total loan requests (approved)
$stmt = $pdo->prepare("SELECT SUM(loan_amount) AS total_loan_requests FROM loan_requests WHERE tontine_id = :tontine_id AND status = 'Approved'");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$total_loan_requests = $stmt->fetchColumn();

// Total loan payments (approved)
$stmt = $pdo->prepare("SELECT SUM(amount) AS total_loan_payments FROM loan_payments WHERE tontine_id = :tontine_id AND payment_status = 'Approved'");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$total_loan_payments = $stmt->fetchColumn();

// Missed contributions that are unpaid
$stmt = $pdo->prepare("SELECT COUNT(*) AS unpaid_missed_contributions FROM missed_contribution_payment WHERE tontine_id = :tontine_id AND payment_status = 'Failure' OR payment_status = 'Pending' ");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$unpaid_missed_contributions = $stmt->fetchColumn();

// Missed contributions that are paid
$stmt = $pdo->prepare("SELECT COUNT(*) AS paid_missed_contributions FROM missed_contributions WHERE tontine_id = :tontine_id AND status = 'Paid'");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$paid_missed_contributions = $stmt->fetchColumn();

// Calculate total dividends (example calculation, could be based on contributions)
$dividend_percentage = 0.1; // 10% dividend (for example)
$total_dividends = $total_contributions * $dividend_percentage; // Adjust the formula as needed

// Total notifications (as an example)
$total_notifications = 5;

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tontine Dashboard</title>
      <!-- Font Awesome (only one version needed) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Bootstrap 5 (the latest version is recommended) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.5.2/css/bootstrap.min.css">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 4 Bundle (you can remove this if you only want to use Bootstrap 5) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1d4ed8;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;6
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-danger: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --gradient-info: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            min-height: 100vh;
        }

        /* Original Navbar Styles */
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

        /* Dashboard Container */
        .dashboard-container {
            padding: 2rem 0;
            min-height: calc(100vh - 80px);
        }

        .dashboard-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: #6b7280;
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Stats Cards Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card.success::before {
            background: var(--gradient-success);
        }

        .stat-card.warning::before {
            background: var(--gradient-warning);
        }

        .stat-card.danger::before {
            background: var(--gradient-danger);
        }

        .stat-card.info::before {
            background: var(--gradient-info);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .stat-icon.primary {
            background: var(--gradient-primary);
        }

        .stat-icon.success {
            background: var(--gradient-success);
        }

        .stat-icon.warning {
            background: var(--gradient-warning);
        }

        .stat-icon.danger {
            background: var(--gradient-danger);
        }

        .stat-icon.info {
            background: var(--gradient-info);
        }

        .stat-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .stat-description {
            font-size: 0.875rem;
            color: #9ca3af;
        }

        /* User Info Section */
        .user-info-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-top: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-color);
        }

        .info-list {
            list-style: none;
            padding: 0;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.3s ease;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item:hover {
            background: #f9fafb;
            margin: 0 -1rem;
            padding: 1rem;
            border-radius: 8px;
        }

        .info-label {
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-value {
            font-weight: 500;
            color: #6b7280;
            text-align: right;
        }

        .info-value.currency {
            color: var(--success-color);
            font-weight: 700;
        }

        .info-value.count {
            background: var(--gradient-primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-title {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .dashboard-header {
                padding: 1.5rem;
            }

            .user-info-section {
                padding: 1.5rem;
            }

            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .info-value {
                text-align: left;
            }
        }

        /* Animation for loading */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gradient-primary);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gradient-secondary);
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
            <li class="nav-item dropdown"hidden>
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
            <li class="nav-item dropdown"hidden>
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
                <a class="nav-link font-weight-bold text-white" href="#">
                    <i class="fas fa-user"></i> 
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

<div class="container dashboard-container">
    <!-- Dashboard Header -->
    <div class="dashboard-header fade-in-up">
        <h1 class="dashboard-title">
            <i class="fas fa-chart-line me-3"></i>Tontine Dashboard
        </h1>
        <p class="dashboard-subtitle">
            Detailed financial overview for <strong><?php echo htmlspecialchars($tontine['tontine_name']); ?></strong>
        </p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card success fade-in-up">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fas fa-piggy-bank"></i>
                </div>
            </div>
            <div class="stat-title">Total Contributions</div>
            <div class="stat-value">RWF <?php echo number_format($total_contributions, 0); ?></div>
            <div class="stat-description">Approved contributions received</div>
        </div>

        <div class="stat-card info fade-in-up">
            <div class="stat-header">
                <div class="stat-icon info">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
            </div>
            <div class="stat-title">Loan Requests</div>
            <div class="stat-value">RWF <?php echo number_format($total_loan_requests, 0); ?></div>
            <div class="stat-description">Total approved loans</div>
        </div>

        <div class="stat-card primary fade-in-up">
            <div class="stat-header">
                <div class="stat-icon primary">
                    <i class="fas fa-money-check-alt"></i>
                </div>
            </div>
            <div class="stat-title">Loan Payments</div>
            <div class="stat-value">RWF <?php echo number_format($total_loan_payments, 0); ?></div>
            <div class="stat-description">Total loan repayments</div>
        </div>

        <div class="stat-card warning fade-in-up">
            <div class="stat-header">
                <div class="stat-icon warning">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-title">Estimated Dividends</div>
            <div class="stat-value">RWF <?php echo number_format($total_dividends, 0); ?></div>
            <div class="stat-description">10% of total contributions</div>
        </div>

        <div class="stat-card danger fade-in-up">
            <div class="stat-header">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
            <div class="stat-title">Unpaid Penalties</div>
            <div class="stat-value"><?php echo $unpaid_missed_contributions; ?></div>
            <div class="stat-description">Missed contributions pending</div>
        </div>

        <div class="stat-card success fade-in-up">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fas fa-check-double"></i>
                </div>
            </div>
            <div class="stat-title">Paid Penalties</div>
            <div class="stat-value"><?php echo $paid_missed_contributions; ?></div>
            <div class="stat-description">Resolved missed contributions</div>
        </div>
    </div>

    <!-- User Information Section -->
    <div class="user-info-section fade-in-up">
        <h3 class="section-title">
            <i class="fas fa-user-circle"></i>
            Dashboard Summary
        </h3>
        <ul class="info-list">
            <li class="info-item">
                <span class="info-label">
                    <i class="fas fa-id-card text-primary"></i>
                    User ID
                </span>
                <span class="info-value count"><?php echo $user_id; ?></span>
            </li>
            <li class="info-item">
                <span class="info-label">
                    <i class="fas fa-user-circle text-primary"></i>
                    Username
                </span>
                <span class="info-value"><?php echo $user_name; ?></span>
            </li>
            <li class="info-item">
                <span class="info-label">
                    <i class="fas fa-piggy-bank text-success"></i>
                    Total Approved Contributions
                </span>
                <span class="info-value currency">RWF <?php echo number_format($total_contributions, 2); ?></span>
            </li>
            <li class="info-item">
                <span class="info-label">
                    <i class="fas fa-hand-holding-usd text-info"></i>
                    Total Approved Loan Requests
                </span>
                <span class="info-value currency">RWF <?php echo number_format($total_loan_requests, 2); ?></span>
            </li>
            <li class="info-item">
                <span class="info-label">
                    <i class="fas fa-exclamation-circle text-warning"></i>
                    Total Unpaid Missed Contributions
                </span>
                <span class="info-value count"><?php echo $unpaid_missed_contributions; ?></span>
            </li>
            <li class="info-item">
                <span class="info-label">
                    <i class="fas fa-check-double text-success"></i>
                    Total Paid Missed Contributions
                </span>
                <span class="info-value count"><?php echo $paid_missed_contributions; ?></span>
            </li>
            <li class="info-item">
                <span class="info-label">
                    <i class="fas fa-money-check-alt text-primary"></i>
                    Total Approved Loan Payments
                </span>
                <span class="info-value currency">RWF <?php echo number_format($total_loan_payments, 2); ?></span>
            </li>
            <li class="info-item">
                <span class="info-label">
                    <i class="fas fa-chart-line text-warning"></i>
                    Total Dividends (Estimated 10%)
                </span>
                <span class="info-value currency">RWF <?php echo number_format($total_dividends, 2); ?></span>
            </li>
        </ul>
    </div>
</div>

<script>
function confirmLogout() {
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out of your account!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, log out!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
}

// Add smooth loading animation
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.fade-in-up');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Add counter animation for numbers
function animateNumbers() {
    const numbers = document.querySelectorAll('.stat-value');
    numbers.forEach(number => {
        const finalValue = number.textContent.replace(/[^\d.-]/g, '');
        if (finalValue && !isNaN(finalValue)) {
            let current = 0;
            const increment = finalValue / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    current = finalValue;
                    clearInterval(timer);
                }
                if (number.textContent.includes('RWF')) {
                    number.textContent = 'RWF ' + Math.floor(current).toLocaleString();
                } else {
                    number.textContent = Math.floor(current);
                }
            }, 30);
        }
    });
}

// Start number animation when page loads
window.addEventListener('load', animateNumbers);
</script>

</body>
</html>