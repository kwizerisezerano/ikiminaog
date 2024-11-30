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

// Get summary statistics with status filters
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

// Total loan requests (assuming there's a loan_requests table)
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_loan_requests FROM loan_requests WHERE tontine_id = :tontine_id AND status = 'Approved'");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$total_loan_requests = $stmt->fetchColumn();

// Total loan payments (assuming there's a loan_payments table)
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_loan_payments FROM loan_payments WHERE tontine_id = :tontine_id");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$total_loan_payments = $stmt->fetchColumn();

// Total penalties payments (assuming there's a penalty_payments table)
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_penalties_payments FROM penalty_payment WHERE tontine_id = :tontine_id AND payment_status = 'Approved'");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$total_penalties_payments = $stmt->fetchColumn();

// Missed contributions (only where status is 'Approved')
$stmt = $pdo->prepare("SELECT COUNT(*) AS missed_contributions_approved FROM missed_contribution_payment WHERE tontine_id = :tontine_id AND payment_status = 'Approved'");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$missed_contributions_approved = $stmt->fetchColumn();

// Missed contributions that are now paid (only where payment status is 'Paid')
$stmt = $pdo->prepare("SELECT COUNT(*) AS missed_contributions_paid FROM missed_contributions WHERE tontine_id = :tontine_id AND status = 'Paid'");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$missed_contributions_paid = $stmt->fetchColumn();

// Calculate total dividends (example calculation, could be based on contributions)
$dividend_percentage = 0.1; // 10% dividend (for example)
$total_dividends = $total_contributions * $dividend_percentage; // Adjust the formula as needed

// Alternatively, you can fetch actual dividend records from a dividends table if you have one.
// Example if there's a `dividends` table:
// $stmt = $pdo->prepare("SELECT SUM(amount) AS total_dividends FROM dividends WHERE tontine_id = :tontine_id");
// $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
// $stmt->execute();
// $total_dividends = $stmt->fetchColumn();
$total_notifications=5;

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
      <!-- Font Awesome (only one version needed) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Bootstrap 5 (the latest version is recommended) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- jQuery (for Bootstrap 4/5) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 4 Bundle (you can remove this if you only want to use Bootstrap 5) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
           .card-custom {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }

        .card-custom h2 {
            font-weight: bold;
            margin-bottom: 20px;
        }

        .card-custom h4 {
            font-weight: bold;
            margin-bottom: 15px;
        }

        .list-group-item {
            font-size: 1.1rem;
        }

        .navbar-nav .nav-link {
            font-weight: bold;
        }

        .nav-item:hover {
            background-color: #e9ecef;
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

        .list-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .list-group-item {
            width: 100%;
            text-align: center;
            margin-bottom: 10px;
        }

        .container-center {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
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
<div class="container  content-justify-center">
        <div class="card-custom">
            <h2 class="text-center">Administrative Information for Tontine: <?php echo htmlspecialchars($tontine['tontine_name']); ?></h2>

            <div class="row content-justify-center">
                <div class="col-md-6  content-justify-center">
                 
                    <ul class="list-group  content-justify-center">
                        <li class="list-group-item"><strong>Total Users Joined:</strong> <?php echo $total_users; ?></li>
                        <li class="list-group-item"><strong>Total Approved Contributions:</strong> <?php echo number_format($total_contributions, 2); ?></li>
                        <li class="list-group-item"><strong>Total Approved Loan Requests:</strong> <?php echo $total_loan_requests; ?></li>
                        <li class="list-group-item"><strong>Total Approved Loan Payments:</strong> <?php echo $total_loan_payments; ?></li>
                        <li class="list-group-item"><strong>Total Penalties Payments:</strong> <?php echo $total_penalties_payments; ?></li>
                        <li class="list-group-item"><strong>Missed Contributions Payment (Approved):</strong> <?php echo $missed_contributions_approved; ?></li>
                        <li class="list-group-item"><strong>Missed Contributions Paid:</strong> <?php echo $missed_contributions_paid; ?></li>
                        <li class="list-group-item"><strong>Total Dividends (Estimated 10%):</strong> <?php echo number_format($total_dividends, 2); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</body>
</html>