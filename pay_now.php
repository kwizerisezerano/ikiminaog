<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>
            alert('You must be logged in to access this page.');
            window.location.href = 'index.php';
          </script>";
    exit();
}

// Get the loan ID, amount, payment date, late repayment amount, and tontine_id from the URL
$loan_id = isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : null;
$amountgot = isset($_GET['amount']) ? (float)$_GET['amount'] : null;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : null;
$payment_date = isset($_GET['payment_date']) ? $_GET['payment_date'] : null;
$late_amount = isset($_GET['late_amount']) ? (float)$_GET['late_amount'] : 0.0;  // Optional late repayment amount
$tontine_id = isset($_GET['tontine_id']) ? (int)$_GET['tontine_id'] : null; // Get tontine_id
$phone_number = isset($_GET['phone']) ? $_GET['phone'] : null; // Get phone number (Allow string, not cast to int)

$transaction_ref = bin2hex(random_bytes(16));  // Generate a unique transaction reference

// Validate required parameters
if (!$loan_id || !$payment_date || !$tontine_id) {
    echo "<script>
            alert('Required parameters are missing.');
           window.location.href = 'loan_success.php?id=$tontine_id';
          </script>";
    exit();
}

try {
    // Fetch loan details from the database
    $loanStmt = $pdo->prepare("SELECT * FROM loan_requests WHERE id = :loan_id AND user_id = :user_id AND tontine_id = :tontine_id");
    $loanStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
    $loanStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $loanStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $loanStmt->execute();

    $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan) {
        echo "<script>
                alert('Loan not found.');
                 window.location.href = 'loan_success.php?id=$tontine_id';
              </script>";
        exit();
    }
    // Calculate the monthly payment
 
    $monthlyPayment = round($amountgot  / 12, 2);

    // **Check for duplicate payment**:
    $checkPaymentStmt = $pdo->prepare("SELECT * FROM loan_payments WHERE tontine_id = :tontine_id AND loan_id = :loan_id AND user_id = :user_id AND (payment_status = 'Approved' OR payment_status = 'Pending')");
    $checkPaymentStmt->bindParam(':loan_id', $loan_id, PDO::PARAM_INT);
    $checkPaymentStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $checkPaymentStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $checkPaymentStmt->execute();

    // If a payment already exists (either pending or completed), prevent insertion
    if ($checkPaymentStmt->rowCount() > 0) {
        echo "<script>
                alert('A payment for this loan has already been made or is pending.');
            window.location.href = 'loan_success.php?id=$tontine_id';
              </script>";
        exit();
    }

    // Proceed with the payment if no existing payment record
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Capture the phone number entered by the user
        $phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';

        // Process the payment (this assumes payment is successful and will be marked as 'Unpaid' initially)
        $pay = hdev_payment::pay($phone_number, $amount, $transaction_ref);

        if ($pay->status !== 'success') {
            echo "<script>
                    alert('Payment failed: " . $pay->message . "');
                 window.location.href = 'loan_success.php?id=$tontine_id';
                  </script>";
            exit();
        }

        // Insert the payment details into the loan_payments table
        $paymentStmt = $pdo->prepare("
            INSERT INTO loan_payments (user_id, loan_id, amount, payment_date, payment_status, transaction_ref, late_amount, tontine_id, phone_number)
            VALUES (:user_id, :loan_id, :amount, :payment_date, 'Pending', :transaction_ref, :late_amount, :tontine_id, :phone_number)
        ");

        $paymentStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'loan_id' => $loan_id,
            'amount' => round($amount), // Ensure amount is an integer
            'payment_date' => $payment_date,
            'transaction_ref' => $transaction_ref,
            'late_amount' => round($late_amount), // Ensure late amount is an integer
            'tontine_id' => $tontine_id,
            'phone_number' => $phone_number
        ]);

        // Payment successful, now redirect the user back to their loan list page
        echo "<script>
                alert('Payment successful!');
                 window.location.href = 'paid_loan_list.php?id=$tontine_id';
              </script>";
        exit();
    }

} catch (PDOException $e) {
    echo "<script>
            alert('Error: " . htmlspecialchars($e->getMessage()) . "');
          window.location.href = 'loan_success.php?id=$tontine_id';
          </script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Payment</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-container {
            max-width: 500px;
            width: 100%;
            margin: 20px;
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
    <div class="payment-container">
        <div class="card payment-card">
            <div class="payment-header">
                <a href="loan_success.php?id=<?php echo $tontine_id; ?>" class="back-btn">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
                
                <div class="payment-icon">
                    <i class="fas fa-money-check-alt"></i>
                </div>
                
                <h1 class="payment-title">Loan Payment</h1>
                <p class="payment-subtitle">Complete your payment securely</p>
            </div>

            <div class="card-body">
                <?php if ($loan): ?>
                    <div class="amount-display">
                        <div class="amount-label">Monthly Payment Amount</div>
                        <div class="amount-value">RWF <?php echo number_format((floor($monthlyPayment) == $monthlyPayment) ? $monthlyPayment : ceil($monthlyPayment), 2); ?></div>
                    </div>

                    <form id="paymentForm" action="pay_now.php?loan_id=<?php echo $loan_id; ?>&amount=<?php echo round($amountgot); ?>&payment_date=<?php echo urlencode($payment_date); ?>&late_amount=<?php echo round($late_amount); ?>&tontine_id=<?php echo $tontine_id; ?>" method="POST">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-mobile-alt"></i>
                                Phone Number
                            </label>
                            <input type="tel" class="form-control" id="phone_number" name="phone_number" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($phone_number); ?>" required>
                        </div>
                        
                        <input type="hidden" name="amount" value="<?php echo (floor($monthlyPayment) == $monthlyPayment) ? $monthlyPayment : ceil($monthlyPayment); ?>">
                        
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
                <?php else: ?>
                    <p class="text-danger text-center">Loan details could not be found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('payBtn').addEventListener('click', function(e) {
            e.preventDefault();
            
            const amount = <?php echo (floor($monthlyPayment) == $monthlyPayment) ? $monthlyPayment : ceil($monthlyPayment); ?>;
            const phoneNumber = document.getElementById('phone_number').value;
            
            if (!phoneNumber) {
                Swal.fire({
                    title: 'Missing Information',
                    text: 'Please enter your phone number to proceed with payment.',
                    icon: 'warning',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#007bff'
                });
                return;
            }
            
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
                        <p style="color: #6c757d; margin-top: 20px;">Are you sure you want to proceed with this loan payment?</p>
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