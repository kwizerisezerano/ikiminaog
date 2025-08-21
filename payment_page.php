<?php
session_start();
require 'config.php';

// Enable error reporting for development (remove this in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get the contribution details from the URL parameters
$contribution_id = isset($_GET['contribution_id']) ? (int)$_GET['contribution_id'] : null;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : null;
$phone_number = isset($_GET['phone_number']) ? $_GET['phone_number'] : null;
$tontine_id = isset($_GET['tontine_id']) ? (int)$_GET['tontine_id'] : null;

// Redirect to the contributions page if any of the details are missing
if (!$contribution_id || !$user_id || !$amount || !$phone_number || !$tontine_id) {
    header("Location: tontines.php");
    exit();
}

try {
    // Fetch contribution details and missed_id
    $stmt = $pdo->prepare("SELECT mc.id, mc.missed_amount, mc.status, u.firstname, u.lastname, u.phone_number 
                           FROM missed_contributions mc
                           JOIN users u ON mc.user_id = u.id
                           WHERE mc.id = :contribution_id AND mc.user_id = :user_id");
    $stmt->execute([
        'contribution_id' => $contribution_id,
        'user_id' => $user_id
    ]);

    $contribution = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contribution) {
        header("Location: tontines.php");
        exit();
    }

    // Handle payment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Generate a unique transaction reference
        $transaction_ref = bin2hex(random_bytes(16));
        
        // Check if a payment already exists for the same missed_id, user_id, and tontine_id
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM missed_contribution_payment 
                               WHERE missed_id = :missed_id AND user_id = :user_id AND tontine_id = :tontine_id AND payment_status='Approved'");
        $stmt->execute([
            'missed_id' => $contribution['id'],
            'user_id' => $user_id,
            'tontine_id' => $tontine_id
        ]);
        $payment_exists = $stmt->fetchColumn() > 0;

        if ($payment_exists) {
            $_SESSION['error_message'] = "A payment for this contribution already exists.";
            header("Location: missed_contribution.php?id=" . $tontine_id);
            exit();
        }

        // Process payment
        $pay = hdev_payment::pay($phone_number, $amount, $transaction_ref);

        if ($pay->status !== 'success') {
            $_SESSION['error_message'] = "Payment failed: " . $pay->message;
            header("Location: missed_contribution.php?id=" . $tontine_id);
            exit();
        }

        // Insert the payment into the missed_contribution_payment table
        $stmt = $pdo->prepare("
            INSERT INTO missed_contribution_payment (user_id, tontine_id, amount, phone_number, transaction_ref, payment_status, missed_id)
            VALUES (:user_id, :tontine_id, :amount, :phone_number, :transaction_ref, :payment_status, :missed_id)
        ");
        $stmt->execute([
            'user_id' => $user_id,
            'tontine_id' => $tontine_id,
            'amount' => $amount,
            'phone_number' => $phone_number,
            'transaction_ref' => $transaction_ref,
            'payment_status' => 'Pending',  // Initially set as Pending
            'missed_id' => $contribution['id'],
        ]);

        // Get the inserted payment ID to check its status later
        $payment_id = $pdo->lastInsertId();

        // Set payment ID in session to check status later
        $_SESSION['check_payment_status'] = $payment_id;
        $_SESSION['payment_message'] = "Payment request submitted successfully. Please wait for confirmation.";
        
        header("Location: missed_contribution.php?id=" . $tontine_id);
        exit();
    }

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Payment processing failed. Please try again.";
    header("Location: missed_contribution.php?id=" . $tontine_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Page</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .card-body {
            padding: 30px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.4);
        }
        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        h4 {
            color: #343a40;
            font-weight: 600;
            margin-bottom: 25px;
            text-align: center;
        }
        .contribution-details p {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            border-radius: 5px;
        }
        .payment-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        .payment-icon i {
            font-size: 48px;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                      <div class="text-center mt-3">
                    <a href="missed_contribution.php?id=<?php echo $tontine_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Contributions
                    </a>
                </div>
                    <div class="card-body">
                        
                        <h4><i class="fas fa-exclamation-triangle text-warning"></i> Missed Contribution Payment</h4>
                        
                   
                        <!-- Payment Form -->
                        <form id="paymentForm" action="" method="POST">
                            <div class="form-group">
                                <label for="amount"><i class="fas fa-money-bill-wave text-primary"></i>  Amount to Pay</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">RWF</span>
                                    </div>
                                    <input type="text" class="form-control" id="amount" name="amount" value="<?php echo number_format($amount, 2); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone_number"><i class="fas fa-mobile-alt text-primary"></i> Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" readonly>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="button" id="payBtn" class="btn btn-primary btn-lg">
                                    <i class="fas fa-credit-card mr-2"></i>Pay Now
                                </button>
                            </div>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt"></i> Your payment is secure and encrypted
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
                
              
            </div>
        </div>
    </div>

    <script>
        document.getElementById('payBtn').addEventListener('click', function(e) {
            e.preventDefault();
            
            const amount = document.getElementById('amount').value;
            const phoneNumber = document.getElementById('phone_number').value;
            
            Swal.fire({
                title: 'Confirm Payment',
                html: `
                    <div style="text-align: left; margin: 20px 0;">
                        <p><strong>Amount:</strong> RWF ${amount}</p>
                        <p><strong>Phone:</strong> ${phoneNumber}</p>
                    </div>
                    <p>Are you sure you want to proceed with this payment?</p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-credit-card mr-2"></i>Yes, Pay Now',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancel',
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-primary btn-lg mx-2',
                    cancelButton: 'btn btn-secondary btn-lg mx-2'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Processing Payment...',
                        html: `
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <p class="mt-3">Please wait while we process your payment</p>
                                <small class="text-muted">Do not close this window</small>
                            </div>
                        `,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit the form after a short delay to show the loading animation
                    setTimeout(() => {
                        document.getElementById('paymentForm').submit();
                    }, 1500);
                }
            });
        });

        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure you want to log out?',
                text: "You will be logged out of your account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'No, stay logged in',
                reverseButtons: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Auto-hide any existing alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>