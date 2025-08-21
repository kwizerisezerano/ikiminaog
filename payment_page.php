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
        * {
            box-sizing: border-box;
        }

        body {
          
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }

        .payment-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .payment-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1), 
                        0 10px 20px rgba(0, 0, 0, 0.05);
            border: none;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .payment-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .payment-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="70" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="80" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
        }

        .payment-icon {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
        }

        .payment-icon i {
            font-size: 32px;
            color: white;
        }

        .payment-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            position: relative;
        }

        .payment-subtitle {
            opacity: 0.9;
            margin-top: 8px;
            font-size: 14px;
            position: relative;
        }

        .card-body {
            padding: 40px 30px;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            padding: 8px 16px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            font-size: 15px;
        }

        .form-label i {
            margin-right: 10px;
            color: #007bff;
        }

        .form-control {
            border: 2px solid #e8ecf0;
            border-radius: 12px;
            padding: 15px 20px;
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
            background: white;
        }

        .input-group-text {
            background: #007bff;
            color: white;
            border: 2px solid #007bff;
            font-weight: 700;
            padding: 15px 20px;
            border-radius: 12px 0 0 12px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .pay-btn {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            border-radius: 50px;
            padding: 18px 50px;
            font-size: 18px;
            font-weight: 700;
            color: white;
            box-shadow: 0 10px 30px rgba(0, 123, 255, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .pay-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transition: all 0.6s ease;
            transform: translate(-50%, -50%);
        }

        .pay-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .pay-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 123, 255, 0.4);
            color: white;
        }

        .pay-btn:active {
            transform: translateY(-1px);
        }

        .security-badge {
            text-align: center;
            margin-top: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }

        .security-badge i {
            color: #28a745;
            margin-right: 8px;
        }

        .security-text {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }

        .amount-display {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #007bff;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }

        .amount-label {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .amount-value {
            color: #007bff;
            font-size: 32px;
            font-weight: 700;
        }

        .phone-display {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px 20px;
            text-align: center;
            font-weight: 600;
            color: #2c3e50;
        }

        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .card-body {
                padding: 30px 20px;
            }
            
            .payment-header {
                padding: 25px 20px;
            }
            
            .back-btn {
                position: static;
                margin-bottom: 20px;
                display: inline-block;
            }
        }

        /* Loading animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Pulse animation for pay button */
        @keyframes pulse {
            0% { box-shadow: 0 10px 30px rgba(0, 123, 255, 0.3); }
            50% { box-shadow: 0 10px 40px rgba(0, 123, 255, 0.5); }
            100% { box-shadow: 0 10px 30px rgba(0, 123, 255, 0.3); }
        }

        .pay-btn {
            animation: pulse 3s infinite;
        }
    </style>
</head>
<body>
    <div class="container-fluid d-flex align-items-center justify-content-center min-vh-100">
        <div class="payment-container">
            <div class="card payment-card">
                <div class="payment-header">
                    <a href="missed_contribution.php?id=<?php echo $tontine_id; ?>" class="back-btn">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                    
                    <div class="payment-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    
                    <h1 class="payment-title">Missed Contribution</h1>
                    <p class="payment-subtitle">Complete your payment securely</p>
                </div>

                <div class="card-body">
                    <div class="amount-display">
                        <div class="amount-label">Amount to Pay</div>
                        <div class="amount-value">RWF <?php echo number_format($amount, 2); ?></div>
                    </div>

                    <form id="paymentForm" action="" method="POST">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-mobile-alt"></i>
                                Phone Number
                            </label>
                            <div class="phone-display">
                                <?php echo htmlspecialchars($phone_number); ?>
                            </div>
                            <input type="hidden" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>">
                            <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                        </div>
                        
                        <div class="text-center">
                            <button type="button" id="payBtn" class="btn pay-btn">
                                <i class="fas fa-credit-card mr-3"></i>
                                <span id="btnText">Pay Now</span>
                                <span id="btnSpinner" class="loading-spinner d-none ml-3"></span>
                            </button>
                        </div>
                        
                        <div class="security-badge">
                            <i class="fas fa-shield-alt"></i>
                            <span class="security-text">Your payment is secure and encrypted</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('payBtn').addEventListener('click', function(e) {
            e.preventDefault();
            
            const amount = <?php echo $amount; ?>;
            const phoneNumber = "<?php echo htmlspecialchars($phone_number); ?>";
            
            Swal.fire({
                title: 'Confirm Payment',
                html: `
                    <div class="text-center">
                        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #007bff; border-radius: 15px; padding: 25px; margin: 20px 0;">
                            <div style="color: #6c757d; font-size: 14px; margin-bottom: 8px;">Amount</div>
                            <div style="color: #007bff; font-size: 28px; font-weight: 700;">RWF ${amount.toLocaleString()}</div>
                        </div>
                        <div style="background: #f8f9fa; border-radius: 10px; padding: 15px; margin: 15px 0;">
                            <strong style="color: #2c3e50;">Phone:</strong> <span style="color: #007bff; font-weight: 600;">${phoneNumber}</span>
                        </div>
                        <p style="color: #6c757d; margin-top: 20px;">Are you sure you want to proceed with this payment?</p>
                    </div>
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
                    cancelButton: 'btn btn-secondary btn-lg mx-2',
                    popup: 'rounded-lg'
                },
                buttonsStyling: false,
                backdrop: 'rgba(0,0,0,0.4)'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading on button
                    const payBtn = document.getElementById('payBtn');
                    const btnText = document.getElementById('btnText');
                    const btnSpinner = document.getElementById('btnSpinner');
                    
                    payBtn.disabled = true;
                    btnText.textContent = 'Processing...';
                    btnSpinner.classList.remove('d-none');
                    
                    // Show loading SweetAlert
                    Swal.fire({
                        title: 'Processing Payment',
                        html: `
                            <div class="text-center" style="padding: 20px;">
                                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <h5 style="color: #007bff; margin-bottom: 15px;">Please wait...</h5>
                                <p class="text-muted">We are processing your payment securely</p>
                                <small class="text-warning">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Do not close this window or go back
                                </small>
                            </div>
                        `,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        backdrop: 'rgba(0,0,0,0.6)',
                        customClass: {
                            popup: 'rounded-lg'
                        }
                    });
                    
                    // Submit the form after a short delay
                    setTimeout(() => {
                        document.getElementById('paymentForm').submit();
                    }, 2000);
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
                cancelButtonColor: '#6c757d',
                customClass: {
                    popup: 'rounded-lg'
                }
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
                if (alert) {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }
            });
        }, 5000);

        // Add subtle animations on page load
        window.addEventListener('load', function() {
            const card = document.querySelector('.payment-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>