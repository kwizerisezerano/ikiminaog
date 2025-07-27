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
$user_phone = htmlspecialchars($user['phone_number']);  // Added phone number from session

// Get tontine ID from the URL
$tontine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch tontine details
$stmt = $pdo->prepare("SELECT tontine_name, interest, payment_frequency, frequent_payment_date, frequent_payment_day, late_contribution_penalty, late_loan_repayment_amount  FROM tontine WHERE id = :id");
$stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if tontine exists
if (!$tontine) {
    die("Tontine not found.");
}
$interest = $tontine['interest'];  // Tontine's interest rate
$payment_frequency = $tontine['payment_frequency'];  // Monthly or Weekly
$frequent_payment_date = $tontine['frequent_payment_date'];  // Payment date or start date
$frequent_payment_day = $tontine['frequent_payment_day'];  // Payment day if weekly
$late_contribution_penalty = $tontine['late_contribution_penalty'];  // Penalty for late contribution
$loan_late_amount   = $tontine['late_loan_repayment_amount']; //
// Notification count
$total_notifications = 5;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join <?php echo htmlspecialchars($tontine['tontine_name']); ?> - Ikimina MIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
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
            background-color: #d6dce5;
            font-family: Arial, sans-serif;
            margin: 0;
           
        }
       
        .form-container { font-size: 0.7rem;
            background-color: #fff;
            padding: 5px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            margin: 60px auto 0;
        }
        label {
            font-weight: bold;
            margin-bottom: 0.2rem;
        }
        .form-title {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-section {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .btn-submit {
            width: 100%;
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
            border: none;
        }
        .btn-submit:hover {
            background-color: #0056b3;
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


<div class="form-container mt-1">
    <h5 class="form-title" style="margin-bottom: 1px;">Welcome to <?php echo htmlspecialchars($tontine['tontine_name']); ?></h5>

    <form id="loanForm" method="POST" action="process_loan.php">
        <!-- Hidden Fields -->
        <input type="hidden" name="tontine_id" value="<?php echo $tontine_id; ?>">
        <input type="hidden" id="interest_rate" value="<?php echo $interest; ?>">

        <!-- Loan Amount -->
        <div class="mb-1">
            <label for="amount" class="form-label">Loan Amount</label>
            <input type="number" class="form-control" id="amount" name="amount"  required>
        </div>

        <!-- Interest Rate (from Tontine) -->
        <div class="mb-1">
            <label for="interest" class="form-label">Interest Rate</label>
            <input type="text" class="form-control" id="interest" name="interest-rate" value="<?php echo $interest; ?>" readonly>
        </div>

        <!-- Interest Amount -->
        <div class="mb-1">
            <label for="interest_amount" class="form-label">Interest Amount</label>
            <input type="text" class="form-control" id="interest_amount" name="interest_amount" value="" readonly>
        </div>

        <!-- Total Amount (Loan + Interest) -->
        <div class="mb-1">
            <label for="total_amount" class="form-label">Total Amount</label>
            <input type="text" class="form-control" id="total_amount" name="total_amount" value="" readonly>
        </div>

        <!-- Payment Frequency -->
        <div class="mb-1">
            <label for="payment_frequency" class="form-label">Payment Frequency</label>
            <input type="text" class="form-control" id="payment_frequency" name="payment_frequency" value="<?php echo htmlspecialchars($payment_frequency); ?>" readonly>
        </div>

        <!-- Frequent Payment Date (only for Monthly payments) -->
        <?php if ($payment_frequency == 'Monthly') : ?>
            <div class="mb-3">
                <label for="frequent_payment_date" class="form-label">Frequent Payment Date</label>
                <input type="text" class="form-control" id="frequent_payment_date" name="frequent_payment_date" value="<?php echo htmlspecialchars($frequent_payment_date); ?>" readonly>
            </div>
        <?php endif; ?>

        <!-- User Phone Number -->
        <div class="mb-2">
            <label for="phone_number" class="form-label">Your Phone Number</label>
            <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo $user_phone; ?>" readonly>
        </div>

            <!-- Payment Frequency -->
        <div class="mb-1">
            <label for="payment_frequency" class="form-label"> Late Loan Repayment Amount </label>
            <input type="text" class="form-control" id="payment_frequency" name="late_loan_amount" value="<?php echo htmlspecialchars($loan_late_amount); ?>" readonly>
        </div>
        <!-- Submit Button -->
        <button type="submit" class="btn btn-submit mb-1" id="submitBtn">Send Loan Request</button>
    </form>
</div>


    <script>
    // Initialize the loan calculations on page load
    document.addEventListener('DOMContentLoaded', function () {
        const amountInput = document.getElementById('amount');
        const interestRate = parseFloat(document.getElementById('interest_rate').value);
        const interestAmountInput = document.getElementById('interest_amount');
        const totalAmountInput = document.getElementById('total_amount');
        const paymentFrequency = '<?php echo $payment_frequency; ?>'; // Payment frequency from PHP

        // Function to calculate the interest amount and total amount
        function calculateAmounts() {
            const amount = parseFloat(amountInput.value) || 0;
            const annualInterestAmount = (amount * interestRate) / 100; // Calculate annual interest
            const totalAmount = amount + annualInterestAmount; // Total amount = loan amount + interest

            // Display the annual interest amount and total amount
            interestAmountInput.value = annualInterestAmount.toFixed(2); // Display interest amount (annual)
            totalAmountInput.value = totalAmount.toFixed(2); // Display total amount (loan + interest)

            // If payment frequency is Weekly or Monthly, calculate payment schedule
         
        }

        // Add event listener to recalculate amounts when loan amount changes
        amountInput.addEventListener('input', calculateAmounts);

        // Initial calculation
        calculateAmounts();
    });
</script>

</body>
</html>
