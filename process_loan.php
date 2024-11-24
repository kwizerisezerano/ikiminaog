<?php
session_start();
require 'config.php';  // Database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Redirect if not logged in
    exit();
}

// Fetch user ID from session
$user_id = $_SESSION['user_id'];

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form values
    $tontine_id = isset($_POST['tontine_id']) ? intval($_POST['tontine_id']) : 0;
    $loan_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $interest_rate = isset($_POST['interest-rate']) ? floatval($_POST['interest-rate']) : 0;
    $interest_amount = isset($_POST['interest_amount']) ? floatval($_POST['interest_amount']) : 0;
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $payment_frequency = isset($_POST['payment_frequency']) ? htmlspecialchars($_POST['payment_frequency']) : '';
    $payment_date = isset($_POST['frequent_payment_date']) ? htmlspecialchars($_POST['frequent_payment_date']) : null;
    $phone_number = isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : '';
    $late_loan_repayment_amount = isset($_POST['late_loan_amount']) ? htmlspecialchars($_POST['late_loan_amount']) : 0.00; // Default to 0 if not provided

    // Validate loan amount
    if ($loan_amount <= 0) {
        die("Invalid loan amount.");
    }

    // Fetch tontine details
    $stmt = $pdo->prepare("SELECT * FROM tontine WHERE id = :id");
    $stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $stmt->execute();
    $tontine = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if tontine exists
    if (!$tontine) {
        die("Tontine not found.");
    }

    // Calculate interest (client-side or optional for validation)
    $calculated_interest_amount = $loan_amount * ($interest_rate / 100);
    $calculated_total_amount = $loan_amount + $calculated_interest_amount;

    // Check if payment_frequency or payment_date are empty and handle them accordingly
    $payment_frequency = empty($payment_frequency) ? 'Monthly' : $payment_frequency; // Fix to avoid null value
    $payment_date = empty($payment_date) ? null : $payment_date;

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
                        'Pending', 
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
    $stmt->bindParam(':payment_date', $payment_date, PDO::PARAM_STR); // Handle payment_date if it's null
    $stmt->bindParam(':phone_number', $phone_number);
    $stmt->bindParam(':late_loan_repayment_amount', $late_loan_repayment_amount, PDO::PARAM_STR);  // Correct binding

    // Execute the query and check if the insertion is successful
    if ($stmt->execute()) {
        // Redirect to the success page
        header("Location: loan_success.php");
        exit();
    } else {
        // Error handling
        $errorInfo = $stmt->errorInfo();
        die("Error executing query: " . $errorInfo[2]);
    }
} else {
    // If the form is not submitted correctly
    die("Invalid request.");
}
?>
