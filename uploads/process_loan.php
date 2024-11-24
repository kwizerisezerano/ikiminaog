<?php
session_start();
require 'config.php';  // Database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>
            alert('You need to log in first!');
            window.location.href = 'index.php';
          </script>";
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
        echo "<script>
                alert('Invalid loan amount. Please enter a valid loan amount.');
                setTimeout(function() {
                    window.location.href = 'loan.php?id=$tontine_id';
                }, 2000); // Delay redirection for 2 seconds
              </script>";
        exit();
    }

    // Fetch tontine details
    $stmt = $pdo->prepare("SELECT * FROM tontine WHERE id = :id");
    $stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $stmt->execute();
    $tontine = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if tontine exists
    if (!$tontine) {
        echo "<script>
                alert('Tontine not found.');
                setTimeout(function() {
                    window.location.href = 'loan.php?id=$tontine_id';
                }, 2000); // Delay redirection for 2 seconds
              </script>";
        exit();
    }

    // Check for duplicate loan requests (pending or approved)
    $stmt = $pdo->prepare("SELECT * FROM loan_requests WHERE user_id = :user_id AND tontine_id = :tontine_id AND (status = 'Pending' OR status = 'Approved')");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->execute();
    $existing_loan = $stmt->fetch(PDO::FETCH_ASSOC);

    // If there's an existing loan request, prevent duplication
    if ($existing_loan) {
        echo "<script>
                alert('You already have a pending or approved loan request for this tontine.');
                setTimeout(function() {
                    window.location.href = 'loan.php?id=$tontine_id';
                }, 2000); // Delay redirection for 2 seconds
              </script>";
        exit();
    }

    // Calculate interest (client-side or optional for validation)
    $calculated_interest_amount = $loan_amount * ($interest_rate / 100);
    $calculated_total_amount = $loan_amount + $calculated_interest_amount;

    // Check if payment_frequency or payment_date are empty and handle them accordingly
    $payment_frequency = empty($payment_frequency) ? 'Monthly' : $payment_frequency; // Fix to avoid null value
    $payment_date = empty($payment_date) ? null : $payment_date;

    // Check if user has enough approved contributions for the requested loan amount
    $stmt = $pdo->prepare("SELECT SUM(amount) AS total_contributions FROM contributions WHERE user_id = :user_id AND tontine_id = :tontine_id AND payment_status = 'Approved'");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->execute();
    $contributions = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_contributions = $contributions['total_contributions'] ?? 0;

    // Check if the requested loan amount is greater than the total contributions
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
    $stmt->bindParam(':payment_date', $payment_date, PDO::PARAM_STR); // Handle payment_date if it's null
    $stmt->bindParam(':phone_number', $phone_number);
    $stmt->bindParam(':status', $loan_status, PDO::PARAM_STR); // Loan status based on the eligibility
    $stmt->bindParam(':late_loan_repayment_amount', $late_loan_repayment_amount, PDO::PARAM_STR);

    // Execute the query and check if the insertion is successful
    if ($stmt->execute()) {
        // Fetch user info for SMS
        $stmt = $pdo->prepare("SELECT firstname, phone_number FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Create the SMS message
        if ($loan_status == 'Approved') {
            $sms_message = "
                Dear " . $user_info['firstname'] . ",\n
                Your loan request of " . number_format($loan_amount, 2) . " has been successfully approved.\n
                You will receive it within 3 days.\n
                Thank you for using our service.
            ";
        } else {
            $sms_message = "
                Dear " . $user_info['firstname'] . ",\n
                Your loan request of " . number_format($loan_amount, 2) . " is pending.\n
                You will be notified once it is processed.\n
                Thank you for your patience.
            ";
        }

        // Send the SMS (ensure you have the right method for SMS sending)
        if (!hdev_sms::send('N-SMS', $user_info['phone_number'], $sms_message)) {
            echo "<script>
                    alert('Failed to send SMS notification.');
                    setTimeout(function() {
                        window.location.href = 'loan_success.php?tontine_id=$tontine_id';
                    }, 2000); // Delay redirection for 2 seconds
                  </script>";
            exit();
        }

        // Redirect to success page with success message
        echo "<script>
        alert('Loan request submitted successfully and SMS notification sent.');
        setTimeout(function() {
            window.location.href = 'loan_success.php?tontine_id=<?php echo $tontine_id; ?>';
        }, 1000); // Delay redirection for 2 seconds
      </script>";
exit();
       } else {
        // Error handling
        $errorInfo = $stmt->errorInfo();
        echo "<script>
                alert('Error executing query: " . $errorInfo[2] . "');
                setTimeout(function() {
                    window.location.href = 'loan.php?id=$tontine_id';
                }, 2000); // Delay redirection for 2 seconds
              </script>";
        exit();
    }
} else {
    // If the form is not submitted correctly
    echo "<script>
            alert('Invalid request.');
            setTimeout(function() {
                window.location.href = 'loan.php?id=$tontine_id';
            }, 2000); // Delay redirection for 2 seconds
          </script>";
    exit();
}
?>
