<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get the penalty ID, tontine ID, amount, and user's phone number from the URL
$penalty_id = isset($_GET['penalty_id']) ? (int)$_GET['penalty_id'] : null;
$tontine_id = isset($_GET['tontine_id']) ? (int)$_GET['tontine_id'] : null;
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : null;
$phone_number = isset($_GET['phone']) ? $_GET['phone'] : null;

// Validate required parameters
if (!$penalty_id || !$tontine_id || !$amount || !$phone_number) {
    header("Location: tontines.php");
    exit();
}

try {
    // Fetch penalty details from the database
    $penaltyStmt = $pdo->prepare("SELECT p.*, u.firstname, u.lastname FROM penalties p 
                                  JOIN users u ON p.user_id = u.id 
                                  WHERE p.id = :penalty_id AND p.tontine_id = :tontine_id AND p.user_id = :user_id");
    $penaltyStmt->bindParam(':penalty_id', $penalty_id, PDO::PARAM_INT);
    $penaltyStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $penaltyStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $penaltyStmt->execute();
    
    $penalty = $penaltyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$penalty) {
        header("Location: tontines.php");
        exit();
    }
    
    // Check if a payment has already been made for this penalty
    $checkPaymentStmt = $pdo->prepare("SELECT * FROM penalty_payment WHERE user_id = :user_id AND penalty_id = :penalty_id AND tontine_id = :tontine_id AND payment_status='Approved'");
    $checkPaymentStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $checkPaymentStmt->bindParam(':penalty_id', $penalty_id, PDO::PARAM_INT);
    $checkPaymentStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $checkPaymentStmt->execute();

    if ($checkPaymentStmt->rowCount() > 0) {
        $_SESSION['payment_error'] = 'Payment already made for this penalty.';
        header("Location: missed_penalties.php?id=" . $tontine_id);
        exit();
    }

    // Handle payment submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $transaction_ref = bin2hex(random_bytes(16));
        
        // Process payment
        $pay = hdev_payment::pay($phone_number, $amount, $transaction_ref);

        if ($pay->status !== 'success') {
            $_SESSION['error_message'] = "Payment failed: " . $pay->message;
            header("Location: missed_penalties.php?id=" . $tontine_id);
            exit();
        }

        // Insert the payment details into the penalty_payment table
        $paymentStmt = $pdo->prepare("
            INSERT INTO penalty_payment (user_id, tontine_id, penalty_id, amount, phone_number, payment_status, transaction_ref)
            VALUES (:user_id, :tontine_id, :penalty_id, :amount, :phone_number, 'Pending', :transaction_ref)
        ");
        
        $paymentStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'tontine_id' => $tontine_id,
            'penalty_id' => $penalty_id,
            'amount' => $amount,
            'phone_number' => $phone_number,
            'transaction_ref' => $transaction_ref
        ]);
        
        $_SESSION['payment_message'] = "Payment request submitted successfully. Please wait for confirmation.";
        header("Location: missed_penalties.php?id=" . $tontine_id);
        exit();
    }

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Payment processing failed. Please try again.";
    header("Location: missed_penalties.php?id=" . $tontine_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Penalty</title>
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
            background: linear-gradient(135deg, #0b59beff 0%, #0a66ceff 100%);
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
            color: #3562dcff;
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
            border-color: #357ddcff;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
            background: white;
        }

        .input-group-text {
            background: #3583dcff;
            color: white;
            border: 2px solid #dc3545;
            font-weight: 700;
            padding: 15px 20px;
            border-radius: 12px 0 0 12px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .pay-btn {
            background: linear-gradient(135deg, #1f62c7ff 0%, #1744d6ff 100%);
            border: none;
            border-radius: 50px;
            padding: 18px 50px;
            font-size: 18px;
            font-weight: 700;
            color: white;
            box-shadow: 0 10px 30px rgba(53, 78, 220, 0.3);
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
            background: rgba(24, 27, 196, 0.2);
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
            box-shadow: 0 15px 40px rgba(7, 23, 172, 0.4);
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
            border: 2px solid #0733aaff;
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
            color: #0a3dacff;
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

       

        .penalty-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .penalty-detail-item:last-child {
            margin-bottom: 0;
        }

        .penalty-detail-label {
            color: #856404;
            font-weight: 600;
            font-size: 14px;
        }

        .penalty-detail-value {
            color: #1d4f81ff;
            font-weight: 700;
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
            0% { box-shadow: 0 10px 30px rgba(220, 53, 69, 0.3); }
            50% { box-shadow: 0 10px 40px rgba(220, 53, 69, 0.5); }
            100% { box-shadow: 0 10px 30px rgba(220, 53, 69, 0.3); }
        }

        .pay-btn {
            animation: pulse 3s infinite;
        }

       

        .warning-alert i {
            color: #856404;
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

       
    </style>
</head>
<body>
    <div class="container-fluid d-flex align-items-center justify-content-center min-vh-100">
        <div class="payment-container">
            <div class="card payment-card">
                <div class="payment-header">
                    <a href="missed_penalties.php?id=<?php echo $tontine_id; ?>" class="back-btn">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                    
                    <div class="payment-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    
                    <h1 class="payment-title">Penalty Payment</h1>
                    <p class="payment-subtitle">Complete your penalty payment securely</p>
                </div>

                <div class="card-body">
                                   

                    <div class="amount-display">
                        <div class="amount-label">Penalty Amount</div>
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
                                <span id="btnText">Pay Penalty</span>
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
            const penaltyReason = "<?php echo !empty($penalty['reason']) ? htmlspecialchars($penalty['reason']) : 'No reason specified'; ?>";
            
            Swal.fire({
                title: 'Confirm Penalty Payment',
                html: `
                    <div class="text-center">
                        <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 2px solid #ffc107; border-radius: 15px; padding: 20px; margin: 20px 0;">
                            <div style="color: #856404; font-size: 14px; margin-bottom: 8px;">
                                <i class="fas fa-gavel mr-2"></i>Penalty Reason
                            </div>
                            <div style="color: #2c3e50; font-weight: 600; font-size: 16px; margin-bottom: 15px;">${penaltyReason}</div>
                        </div>
                        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px solid #dc3545; border-radius: 15px; padding: 25px; margin: 20px 0;">
                            <div style="color: #6c757d; font-size: 14px; margin-bottom: 8px;">Penalty Amount</div>
                            <div style="color: #dc3545; font-size: 28px; font-weight: 700;">RWF ${amount.toLocaleString()}</div>
                        </div>
                        <div style="background: #f8f9fa; border-radius: 10px; padding: 15px; margin: 15px 0;">
                            <strong style="color: #2c3e50;">Phone:</strong> <span style="color: #dc3545; font-weight: 600;">${phoneNumber}</span>
                        </div>
                        <p style="color: #6c757d; margin-top: 20px;">Are you sure you want to proceed with this penalty payment?</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-credit-card mr-2"></i>Yes, Pay Penalty',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancel',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-danger btn-lg mx-2',
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
                        title: 'Processing Penalty Payment',
                        html: `
                            <div class="text-center" style="padding: 20px;">
                                <div class="spinner-border text-danger mb-3" style="width: 3rem; height: 3rem;" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <h5 style="color: #dc3545; margin-bottom: 15px;">Please wait...</h5>
                                <p class="text-muted">We are processing your penalty payment securely</p>
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