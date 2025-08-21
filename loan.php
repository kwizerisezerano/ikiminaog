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
$user_phone = htmlspecialchars($user['phone_number']);

// Get tontine ID from the URL
$tontine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch tontine details
$stmt = $pdo->prepare("SELECT tontine_name, interest, payment_frequency, frequent_payment_date, frequent_payment_day, late_contribution_penalty, late_loan_repayment_amount FROM tontine WHERE id = :id");
$stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if tontine exists
if (!$tontine) {
    die("Tontine not found.");
}

// Extract tontine variables for easier use
$interest = $tontine['interest'];
$payment_frequency = $tontine['payment_frequency'];
$frequent_payment_date = $tontine['frequent_payment_date'];
$frequent_payment_day = $tontine['frequent_payment_day'];
$late_contribution_penalty = $tontine['late_contribution_penalty'];
$loan_late_amount = $tontine['late_loan_repayment_amount'];
$total_notifications = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join <?php echo htmlspecialchars($tontine['tontine_name']); ?> - Ikimina MIS</title>
    
    <!-- External CSS Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ==================== CSS VARIABLES ==================== */
        :root {
            --primary-color: #1c64d1ff;
            --primary-dark: #094badff;
            --secondary-color: #f8fafc;
            --success-color: #102fb9ff;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-color: #1f2937;
            --light-gray: #f1f5f9;
            --border-color: #e2e8f0;
            --text-color: #374151;
            --text-light: #6b7280;
        }

        /* ==================== GLOBAL STYLES ==================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;       
            min-height: 100vh;
            color: var(--text-color);
        }

        /* ==================== LAYOUT CONTAINERS ==================== */
        .main-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem 1rem;
            margin-top: -60px;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(12, 42, 180, 0.2);
            width: 100%;
            max-width: 480px;
            border: 1px solid rgba(32, 63, 167, 0.2);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color), var(--warning-color));
            border-radius: 24px 24px 0 0;
        }

        /* ==================== TYPOGRAPHY ==================== */
        .form-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
        }

        .form-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            border-radius: 2px;
        }

        /* ==================== FORM ELEMENTS ==================== */
        .form-group {
            margin-bottom: 1rem;
            position: relative;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            background: rgba(248, 250, 252, 0.8);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            outline: none;
            transform: translateY(-1px);
        }

        .form-control:read-only {
            background: rgba(241, 245, 249, 0.8);
            color: var(--text-light);
            cursor: not-allowed;
        }

        /* ==================== INPUT GROUPS WITH ICONS ==================== */
        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            z-index: 10;
        }

        .form-control.with-icon {
            padding-left: 2.5rem;
        }

        /* ==================== BUTTONS ==================== */
        .btn-submit {
            width: 100%;
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 1rem;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .menu-container {
    text-align: center;
    margin-bottom: 1.5rem;
    margin-top:1rem;
}

.menu-link {
    display: inline-block;
    text-decoration: none;
    background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
    color: #fff;
    font-weight: 600;
    padding: 0.6rem 1.2rem;
    border-radius: 12px;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    box-shadow: 0 4px 12px rgba(28, 100, 209, 0.25);
}

.menu-link:hover {
    background: linear-gradient(45deg, var(--primary-dark), var(--primary-color));
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(30, 72, 134, 0.35);
    text-decoration: none;
    color: #fff;
}


        /* ==================== CARD COMPONENTS ==================== */
        .info-card {
            background: rgba(79, 70, 229, 0.1);
            border: 1px solid rgba(79, 70, 229, 0.2);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: var(--primary-color);
        }

        .amount-display {
            background: linear-gradient(45deg, rgba(10, 98, 150, 0.1), rgba(16, 101, 212, 0.1));
            border: 2px solid rgba(8, 64, 148, 0.3);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            margin: 1rem 0;
        }

        .amount-display .amount-label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 0.25rem;
        }

        .amount-display .amount-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--success-color);
        }

        /* ==================== LOADING STATE ==================== */
        .loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .loading .btn-submit::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ==================== DROPDOWN STYLES ==================== */
        .dropdown-menu {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .dropdown-item {
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            margin: 0.25rem;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        /* ==================== SWEETALERT CUSTOMIZATION ==================== */
        .swal2-popup {
            border-radius: 16px !important;
            font-family: 'Inter', sans-serif !important;
        }

        .swal2-title {
            color: var(--primary-color) !important;
        }

        /* ==================== RESPONSIVE DESIGN ==================== */
        @media (max-width: 768px) {
            .form-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .form-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="form-container">
            <!-- Back Navigation Link -->
<div class="menu-container">
    <a href="tontine_profile_member.php?id=<?php echo $tontine_id; ?>" class="menu-link">
        <i class="fas fa-arrow-left me-2"></i> Back to Tontine Profile
    </a>
</div>


            <!-- Page Title -->
            <h2 class="form-title">
                <i class="fas fa-money-bill-wave me-2"></i>
                Loan Request
            </h2>
            
            <!-- Tontine Information Card -->
            <div class="info-card">
                <strong><i class="fas fa-info-circle me-1"></i> <?php echo htmlspecialchars($tontine['tontine_name']); ?></strong>
                <div class="mt-1">Interest Rate: <?php echo $interest; ?>% | Frequency: <?php echo $payment_frequency; ?></div>
            </div>

            <!-- Loan Request Form -->
            <form id="loanForm" method="POST" action="process_loan.php">
                <!-- Hidden Fields -->
                <input type="hidden" name="tontine_id" value="<?php echo $tontine_id; ?>">
                <input type="hidden" id="interest_rate" value="<?php echo $interest; ?>">

                <!-- Loan Amount Input -->
                <div class="form-group">
                    <label for="amount" class="form-label">
                        <i class="fas fa-dollar-sign"></i> Loan Amount
                    </label>
                    <div class="input-group">
                        <input type="number" class="form-control with-icon" id="amount" name="amount" 
                               placeholder="Enter loan amount" min="1" step="0.01" required>
                        <i class="fas fa-money-bill-wave input-icon"></i>
                    </div>
                </div>

                <!-- Interest Rate Display -->
                <div class="form-group">
                    <label for="interest" class="form-label">
                        <i class="fas fa-percentage"></i> Interest Rate
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control with-icon" id="interest" 
                               name="interest-rate" value="<?php echo $interest; ?>%" readonly>
                        <i class="fas fa-chart-line input-icon"></i>
                    </div>
                </div>

                <!-- Amount Calculations Display -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="amount-display">
                            <div class="amount-label">Interest Amount</div>
                            <div class="amount-value" id="interest_display">0.00</div>
                            <input type="hidden" id="interest_amount" name="interest_amount" value="">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="amount-display">
                            <div class="amount-label">Total Amount</div>
                            <div class="amount-value" id="total_display">0.00</div>
                            <input type="hidden" id="total_amount" name="total_amount" value="">
                        </div>
                    </div>
                </div>

                <!-- Payment Frequency Display -->
                <div class="form-group">
                    <label for="payment_frequency" class="form-label">
                        <i class="fas fa-calendar-alt"></i> Payment Frequency
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control with-icon" id="payment_frequency" 
                               name="payment_frequency" value="<?php echo htmlspecialchars($payment_frequency); ?>" readonly>
                        <i class="fas fa-clock input-icon"></i>
                    </div>
                </div>

                <!-- Monthly Payment Date (Conditional) -->
                <?php if ($payment_frequency == 'Monthly') : ?>
                    <div class="form-group">
                        <label for="frequent_payment_date" class="form-label">
                            <i class="fas fa-calendar-check"></i> Payment Date
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control with-icon" id="frequent_payment_date" 
                                   name="frequent_payment_date" value="<?php echo htmlspecialchars($frequent_payment_date); ?>" readonly>
                            <i class="fas fa-calendar-day input-icon"></i>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Phone Number Display -->
                <div class="form-group">
                    <label for="phone_number" class="form-label">
                        <i class="fas fa-phone"></i> Your Phone Number
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control with-icon" id="phone_number" 
                               name="phone_number" value="<?php echo $user_phone; ?>" readonly>
                        <i class="fas fa-mobile-alt input-icon"></i>
                    </div>
                </div>

                <!-- Late Payment Penalty Display -->
                <div class="form-group">
                    <label for="late_loan_amount" class="form-label">
                        <i class="fas fa-exclamation-triangle"></i> Late Repayment Penalty
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control with-icon" id="late_loan_amount" 
                               name="late_loan_amount" value="<?php echo htmlspecialchars($loan_late_amount); ?>" readonly>
                        <i class="fas fa-warning input-icon"></i>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-submit" id="submitBtn">
                    <i class="fas fa-paper-plane me-2"></i>
                    Send Loan Request
                </button>
            </form>
        </div>
    </div>

    <!-- External JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ==================== DOCUMENT READY INITIALIZATION ====================
        document.addEventListener('DOMContentLoaded', function () {
            // Get DOM elements
            const amountInput = document.getElementById('amount');
            const interestRate = parseFloat(document.getElementById('interest_rate').value);
            const interestAmountInput = document.getElementById('interest_amount');
            const totalAmountInput = document.getElementById('total_amount');
            const interestDisplay = document.getElementById('interest_display');
            const totalDisplay = document.getElementById('total_display');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('loanForm');

            // ==================== CALCULATION FUNCTIONS ====================
            function calculateAmounts() {
                const amount = parseFloat(amountInput.value) || 0;
                const annualInterestAmount = (amount * interestRate) / 100;
                const totalAmount = amount + annualInterestAmount;

                // Update hidden form inputs
                interestAmountInput.value = annualInterestAmount.toFixed(2);
                totalAmountInput.value = totalAmount.toFixed(2);

                // Update display elements with formatted numbers
                interestDisplay.textContent = annualInterestAmount.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                totalDisplay.textContent = totalAmount.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                // Enable/disable submit button based on amount validity
                if (amount > 0) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                } else {
                    submitBtn.disabled = true;
                }
            }

            // ==================== EVENT LISTENERS ====================
            // Recalculate amounts when loan amount changes
            amountInput.addEventListener('input', calculateAmounts);

            // Form submission handler with confirmation
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const loanAmount = parseFloat(amountInput.value);
                
                // Validate loan amount
                if (!loanAmount || loanAmount <= 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Amount',
                        text: 'Please enter a valid loan amount greater than 0.',
                        confirmButtonColor: '#4f46e5'
                    });
                    return;
                }

                // Show confirmation dialog
                Swal.fire({
                    icon: 'question',
                    title: 'Confirm Loan Request',
                    html: `
                        <div class="text-start">
                            <p><strong>Loan Amount:</strong> ${loanAmount.toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                            <p><strong>Interest:</strong> ${interestAmountInput.value}</p>
                            <p><strong>Total Amount:</strong> ${totalAmountInput.value}</p>
                            <p class="mt-3 text-muted">Are you sure you want to submit this loan request?</p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonColor: '#4f46e5',
                    cancelButtonColor: '#ef4444',
                    confirmButtonText: 'Yes, Submit Request',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        submitBtn.classList.add('loading');
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                        
                        // Submit the form
                        form.submit();
                    }
                });
            });

            // ==================== INITIALIZATION ====================
            // Perform initial calculation
            calculateAmounts();
        });

        // ==================== UTILITY FUNCTIONS ====================
        function confirmLogout() {
            Swal.fire({
                icon: 'question',
                title: 'Confirm Logout',
                text: 'Are you sure you want to logout?',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Logout',
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