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
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, image,idno,behalf_name,behalf_phone_number ,idno_picture,otp_behalf_used FROM users WHERE id = :id");
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
$stmt = $pdo->prepare("SELECT tontine_name, total_contributions FROM tontine WHERE id = :id");
$stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if tontine exists
if (!$tontine) {
    die("Tontine not found.");
}

// Get the total contributions for calculation
$total_contributions = $tontine['total_contributions'];

// Notification count
$total_notifications = 5;

 // Prepare and execute the query
        $stmt = $pdo->prepare("SELECT amount FROM tontine_join_requests WHERE tontine_id = :tontine_id AND user_id = :user_id");
        $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join <?php echo htmlspecialchars($tontine['tontine_name']); ?> - Ikimina MIS</title>
  <!-- Font Awesome (only one version needed) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- jQuery (for Bootstrap 4/5) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 Bundle (you can remove this if you only want to use Bootstrap 5) -->
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
        /* Custom CSS */
        body {
            background-color: #d6dce5;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        .form-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            margin: 60px auto 0; /* Adds space below the navbar */
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
        .form-check-label {
            font-size: 0.9rem;
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
                <a class="nav-link font-weight-bold text-white" href="#">
                    <i class="fas fa-user"></i> 
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

<div class="form-container mt-3">
    <h5 class="form-title">Welcome to  <?php echo htmlspecialchars($tontine['tontine_name']); ?></h5>
    
    <form id="joinForm" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="tontine_id" value="<?php echo $tontine_id; ?>">
        <input type="hidden" id="total_contributions" value="<?php echo $total_contributions; ?>">

        <!-- Loan Type Dropdown -->
        <div class="mb-3">
            <label for="loan_type" class="form-label">Loan Type</label>
            <select class="form-control" id="loan_type" name="loan_type" onchange="updateLoanRange()">
                <option value="small">Small</option>
                <option value="medium">Medium</option>
                <option value="large">Large</option>
            </select>
        </div>

        <!-- Loan Amount -->
        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" class="form-control" id="amount" name="amount" min="500" max="100000" >
            <small id="amountRange" class="form-text text-muted">Amount Range: 500 - 10,000 (Small), 10,000 - 50,000 (Medium), 50,000 - 100,000 (Large)</small>
        </div>

        <!-- Interest Rate -->
        <div class="mb-3">
            <label for="interest" class="form-label">Interest</label>
            <input type="text" class="form-control" id="interest" name="interest" value="5%" readonly>
        </div>

        <!-- Payment Frequency -->
        <div class="mb-3">
            <label for="payment_frequency" class="form-label">Payment Frequency</label>
            <input type="text" class="form-control" id="payment_frequency" name="payment_frequency" value="Monthly" readonly>
        </div>

        <!-- Total Amount -->
        <div class="mb-3">
            <label for="total_amount" class="form-label">Total Amount</label>
            <input type="text" class="form-control" id="total_amount" name="total_amount" readonly>
        </div>

        <!-- Upload Collateral (PDF) -->
        <div class="mb-3">
            <label for="collateral" class="form-label">Upload Collateral (PDF)</label>
            <input type="file" class="form-control" id="collateral" name="collateral" accept=".pdf">
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-submit" id="submitBtn">Submit Join Request</button>
    </form>
</div>


<script>
   // Update loan range and total amount based on loan type
    function updateLoanRange() {
        const loanType = document.getElementById('loan_type').value;
        const amountField = document.getElementById('amount');
        const amountRange = document.getElementById('amountRange');
        const totalAmountField = document.getElementById('total_amount');
        let min = 0;
        let max = 0;

        // Set loan amount range and total amount
        switch (loanType) {
            case 'small':
                min = 500;
                max = 10000;
                amountRange.textContent = 'Amount Range: 500 - 10,000 (Small)';
                break;
            case 'medium':
                min = 10000;
                max = 50000;
                amountRange.textContent = 'Amount Range: 10,000 - 50,000 (Medium)';
                break;
            case 'large':
                min = 50000;
                max = 100000;
                amountRange.textContent = 'Amount Range: 50,000 - 100,000 (Large)';
                break;
        }

        amountField.min = min;
        amountField.max = max;
        totalAmountField.value = `Loan Amount: ${min} - ${max}`;
    }

    // Initialize loan range on page load
    document.addEventListener('DOMContentLoaded', updateLoanRange);

  $('#joinForm').on('submit', function(e) {
    e.preventDefault();

    $.ajax({
        url: 'submit_contribution.php',
        type: 'POST',
        data: $(this).serialize(),
        success: function(response) {
            const res = JSON.parse(response);
            Swal.fire({
                title: res.title,
                text: res.message,
                icon: res.status === 'success' ? 'success' : 'error',
            }).then(() => {
                if (res.status === 'success' && res.redirect) {
                    window.location.href = res.redirect;
                }
            });
        },
        error: function() {
            Swal.fire({
                title: 'Server Error',
                text: 'Please try again later.',
                icon: 'error',
            });
        }
    });
});



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

</script>
</body>
</html>
