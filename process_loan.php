<?php
session_start();
require 'config.php';  // Database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Required</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
        <style>
            body { font-family: "Inter", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 0; }
            .swal2-popup { border-radius: 16px !important; font-family: "Inter", sans-serif !important; }
        </style>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: "warning",
                title: "Authentication Required",
                text: "You need to log in first to access this page.",
                confirmButtonColor: "#4f46e5",
                confirmButtonText: "Go to Login"
            }).then((result) => {
                window.location.href = "index.php";
            });
        </script>
    </body>
    </html>';
    exit();
}

// Fetch user ID from session
$user_id = $_SESSION['user_id'];

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form values with proper sanitization
    $tontine_id = isset($_POST['tontine_id']) ? intval($_POST['tontine_id']) : 0;
    $loan_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $interest_rate = isset($_POST['interest-rate']) ? floatval($_POST['interest-rate']) : 0;
    $interest_amount = isset($_POST['interest_amount']) ? floatval($_POST['interest_amount']) : 0;
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $payment_frequency = isset($_POST['payment_frequency']) ? htmlspecialchars($_POST['payment_frequency']) : '';
    $payment_date = isset($_POST['frequent_payment_date']) ? htmlspecialchars($_POST['frequent_payment_date']) : null;
    $phone_number = isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : '';
    $late_loan_repayment_amount = isset($_POST['late_loan_amount']) ? floatval($_POST['late_loan_amount']) : 0.00;

    // Enhanced validation
    $errors = [];
    
    if ($loan_amount <= 0) {
        $errors[] = "Invalid loan amount. Please enter a valid loan amount.";
    }
    
    if ($tontine_id <= 0) {
        $errors[] = "Invalid tontine selection.";
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone number is required.";
    }

    // If there are validation errors, show them
    if (!empty($errors)) {
        $error_message = implode("\\n", $errors);
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Validation Error</title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
            <style>
                body { font-family: "Inter", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 0; }
                .swal2-popup { border-radius: 16px !important; font-family: "Inter", sans-serif !important; }
            </style>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Validation Error",
                    text: "' . $error_message . '",
                    confirmButtonColor: "#ef4444",
                    confirmButtonText: "Try Again"
                }).then((result) => {
                    window.location.href = "loan.php?id=' . $tontine_id . '";
                });
            </script>
        </body>
        </html>';
        exit();
    }

    // Fetch tontine details
    try {
        $stmt = $pdo->prepare("SELECT * FROM tontine WHERE id = :id");
        $stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
        $stmt->execute();
        $tontine = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if tontine exists
        if (!$tontine) {
            throw new Exception("Tontine not found.");
        }
    } catch (Exception $e) {
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Tontine Error</title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
            <style>
                body { font-family: "Inter", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 0; }
                .swal2-popup { border-radius: 16px !important; font-family: "Inter", sans-serif !important; }
            </style>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Tontine Not Found",
                    text: "The requested tontine could not be found.",
                    confirmButtonColor: "#ef4444",
                    confirmButtonText: "Go Back"
                }).then((result) => {
                    window.location.href = "joined_tontine.php";
                });
            </script>
        </body>
        </html>';
        exit();
    }

    // Check for duplicate loan requests (pending or approved)
    try {
        $stmt = $pdo->prepare("SELECT * FROM loan_requests WHERE user_id = :user_id AND tontine_id = :tontine_id AND (status = 'Pending' OR status = 'Approved')");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
        $stmt->execute();
        $existing_loan = $stmt->fetch(PDO::FETCH_ASSOC);

        // If there's an existing loan request, prevent duplication
        if ($existing_loan) {
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Duplicate Request</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
                <style>
                    body { font-family: "Inter", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 0; }
                    .swal2-popup { border-radius: 16px !important; font-family: "Inter", sans-serif !important; }
                </style>
            </head>
            <body>
                <script>
                    Swal.fire({
                        icon: "warning",
                        title: "Duplicate Request",
                        text: "You already have a pending or approved loan request for this tontine.",
                        confirmButtonColor: "#f59e0b",
                        confirmButtonText: "View My Loans"
                    }).then((result) => {
                        window.location.href = "my_loans.php";
                    });
                </script>
            </body>
            </html>';
            exit();
        }
    } catch (Exception $e) {
        error_log("Error checking duplicate loans: " . $e->getMessage());
    }

    // Calculate interest (server-side validation)
    $calculated_interest_amount = $loan_amount * ($interest_rate / 100);
    $calculated_total_amount = $loan_amount + $calculated_interest_amount;

    // Validate calculated amounts
    if (abs($calculated_interest_amount - $interest_amount) > 0.01 || 
        abs($calculated_total_amount - $total_amount) > 0.01) {
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Calculation Error</title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
            <style>
                body { font-family: "Inter", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 0; }
                .swal2-popup { border-radius: 16px !important; font-family: "Inter", sans-serif !important; }
            </style>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Calculation Error",
                    text: "There was an error in the loan calculations. Please try again.",
                    confirmButtonColor: "#ef4444",
                    confirmButtonText: "Try Again"
                }).then((result) => {
                    window.location.href = "loan.php?id=' . $tontine_id . '";
                });
            </script>
        </body>
        </html>';
        exit();
    }

    // Check payment frequency and date handling
    $payment_frequency = empty($payment_frequency) ? 'Monthly' : $payment_frequency;
    $payment_date = empty($payment_date) ? null : $payment_date;

    // Check if user has enough approved contributions for the requested loan amount
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) AS total_contributions FROM contributions WHERE user_id = :user_id AND tontine_id = :tontine_id AND payment_status = 'Approved'");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
        $stmt->execute();
        $contributions = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_contributions = $contributions['total_contributions'] ?? 0;

        // Determine loan status based on contributions
        $loan_status = ($loan_amount <= $total_contributions) ? 'Approved' : 'Pending';

        // Prepare SQL query for loan request insertion
        $stmt = $pdo->prepare("INSERT INTO loan_requests (
                            user_id, 
                            tontine_id, 
                            loan_amount, 
                            interest_rate, 
                            interest_amount, 
                            total_amount, 
                            payment_frequency, 
                            payment_date, 
                            phone_number, 
                            status, 
                            created_at, 
                            updated_at,                       
                            late_loan_repayment_amount 
                        ) VALUES (
                            :user_id, 
                            :tontine_id, 
                            :loan_amount, 
                            :interest_rate, 
                            :interest_amount, 
                            :total_amount, 
                            :payment_frequency, 
                            :payment_date, 
                            :phone_number, 
                            :status, 
                            NOW(), 
                            NOW(),
                            :late_loan_repayment_amount
                        )");

        // Bind parameters
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':tontine_id', $tontine_id);
        $stmt->bindParam(':loan_amount', $loan_amount);
        $stmt->bindParam(':interest_rate', $interest_rate);
        $stmt->bindParam(':interest_amount', $interest_amount);
        $stmt->bindParam(':total_amount', $total_amount);
        $stmt->bindParam(':payment_frequency', $payment_frequency, PDO::PARAM_STR);
        $stmt->bindParam(':payment_date', $payment_date, PDO::PARAM_STR);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':status', $loan_status, PDO::PARAM_STR);
        $stmt->bindParam(':late_loan_repayment_amount', $late_loan_repayment_amount);

        // Execute the query
        if ($stmt->execute()) {
            // Fetch user info for SMS
            $stmt = $pdo->prepare("SELECT firstname, phone_number FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

            // Create the SMS message
            if ($loan_status == 'Approved') {
                $sms_message = "Dear " . $user_info['firstname'] . ",\nYour loan request of " . number_format($loan_amount, 2) . " has been successfully approved.\nYou will receive it within 3 days.\nThank you for using our service.";
                $success_title = "Loan Approved!";
                $success_text = "Your loan request has been approved and you will receive the funds within 3 days.";
                $success_icon = "success";
            } else {
                $sms_message = "Dear " . $user_info['firstname'] . ",\nYour loan request of " . number_format($loan_amount, 2) . " is pending.\nYou will be notified once it is processed.\nThank you for your patience.";
                $success_title = "Loan Request Submitted";
                $success_text = "Your loan request is pending review. You will be notified once it\'s processed.";
                $success_icon = "info";
            }

            // Attempt to send SMS (with error handling)
            $sms_sent = false;
            try {
                if (class_exists('hdev_sms')) {
                    $sms_sent = hdev_sms::send('N-SMS', $user_info['phone_number'], $sms_message);
                }
            } catch (Exception $e) {
                error_log("SMS sending failed: " . $e->getMessage());
            }

            // Success page with SweetAlert
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Loan Request Success</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
                <style>
                    body { 
                        font-family: "Inter", sans-serif; 
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                        margin: 0; 
                        padding: 0; 
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .swal2-popup { 
                        border-radius: 16px !important; 
                        font-family: "Inter", sans-serif !important; 
                    }
                    .success-container {
                        background: rgba(255, 255, 255, 0.95);
                        backdrop-filter: blur(20px);
                        border-radius: 24px;
                        padding: 2rem;
                        text-align: center;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
                        max-width: 400px;
                        margin: 2rem;
                    }
                </style>
            </head>
            <body>
                <div class="success-container">
                    <div style="color: #10b981; font-size: 3rem; margin-bottom: 1rem;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 style="color: #4f46e5; margin-bottom: 1rem;">Processing...</h2>
                    <p style="color: #6b7280;">Please wait while we process your request.</p>
                </div>
                <script>
                    setTimeout(() => {
                        Swal.fire({
                            icon: "' . $success_icon . '",
                            title: "' . $success_title . '",
                            html: `
                                <div style="text-align: left;">
                                    <p><strong>Loan Amount:</strong> ' . number_format($loan_amount, 2) . '</p>
                                    <p><strong>Interest Rate:</strong> ' . $interest_rate . '%</p>
                                    <p><strong>Total Amount:</strong> ' . number_format($total_amount, 2) . '</p>
                                    <p><strong>Status:</strong> <span style="color: ' . ($loan_status == 'Approved' ? '#10b981' : '#f59e0b') . '; font-weight: 600;">' . $loan_status . '</span></p>
                                    <hr style="margin: 1rem 0;">
                                    <p style="font-size: 0.9rem; color: #6b7280;">' . $success_text . '</p>' . 
                                    (!$sms_sent ? '<p style="font-size: 0.8rem; color: #ef4444;"><em>Note: SMS notification could not be sent.</em></p>' : '') . '
                                </div>
                            `,
                            confirmButtonColor: "' . ($loan_status == 'Approved' ? '#10b981' : '#4f46e5') . '",
                            confirmButtonText: "Continue",
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            window.location.href = "joined_tontine.php";
                        });
                    }, 1000);
                </script>
            </body>
            </html>';
            exit();

        } else {
            // Database error
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Database error: " . $errorInfo[2]);
        }

    } catch (Exception $e) {
        error_log("Loan processing error: " . $e->getMessage());
        
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Processing Error</title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
            <style>
                body { font-family: "Inter", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 0; }
                .swal2-popup { border-radius: 16px !important; font-family: "Inter", sans-serif !important; }
            </style>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: "error",
                    title: "Processing Error",
                    text: "An error occurred while processing your loan request. Please try again.",
                    confirmButtonColor: "#ef4444",
                    confirmButtonText: "Try Again"
                }).then((result) => {
                    window.location.href = "loan.php?id=' . $tontine_id . '";
                });
            </script>
        </body>
        </html>';
        exit();
    }

} else {
    // Invalid request method
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invalid Request</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
        <style>
            body { font-family: "Inter", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 0; }
            .swal2-popup { border-radius: 16px !important; font-family: "Inter", sans-serif !important; }
        </style>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: "error",
                title: "Invalid Request",
                text: "Invalid request method. Please use the proper form.",
                confirmButtonColor: "#ef4444",
                confirmButtonText: "Go Back"
            }).then((result) => {
                window.location.href = "loan_success.php?id=" + tontineId;
            });
        </script>
    </body>
    </html>';
    exit();
}
?>