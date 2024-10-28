<?php
session_start();
include 'config.php'; // Ensure this file connects to your database

function generateOTP($length = 6) {
    return random_int(100000, 999999);
}

function sendMessage($phoneNumber, $message) {
    // Send SMS using sms_parse.php or any SMS provider you have integrated
    hdev_sms::send('N-SMS', $phoneNumber, $message);
    return "Message sent to $phoneNumber: $message";
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phoneNumber = $_POST['phone'];

    // Check if phone number is provided
    if (empty($phoneNumber)) {
        $message = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">Phone number is required</div><br>';
    } else {
        // Prepare query to check if the phone number exists in the database
        $query = "SELECT * FROM users WHERE phone_number = :phone";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':phone', $phoneNumber);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If user exists, send OTP
        if ($user) {
            $otp = generateOTP();

            // Update OTP for this user in the database
            $update_query = "UPDATE users SET otp_forgot = :otp WHERE id = :user_id";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->bindParam(':otp', $otp);
            $update_stmt->bindParam(':user_id', $user['id']);
            $update_stmt->execute();

            // Send OTP via SMS
            $smsMessage = "Dear {$user['firstname']}, your OTP to reset your password is: $otp";
            sendMessage($user['phone_number'], $smsMessage);

            // Set session and redirect to verifyforgot.php
            $_SESSION['forgot_user_id'] = $user['id'];
            $message = '<div class="bg-success text-white" style="border-radius: 8px; padding: 10px;">OTP has been sent to your phone number. Please check your SMS.</div><br>';

            echo '<script>
                    setTimeout(function() {
                        window.location.href = "verifyforgot.php";
                    }, 2000);
                  </script>';
        } else {
            // User not found
            $message = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">No user found with this phone number</div><br>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
           body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh; /* Full height of the viewport */
            margin: 0; /* Remove default margin */
            background-color: #f8f9fa; /* Light background for contrast */
        }
        .container {
            max-width: 400px;
            margin: 10px; /* Add some margin */
        }
    </style>
</head>
<body>
    <div class="container shadow p-4 bg-white rounded">
        <h2 class="text-center text-primary">IKIMINA MIS</h2>
        <p class="text-center text-muted">Enter your phone number to reset your password</p>
        <?php if ($message) echo $message; ?>
        <form method="POST" action="">
            <div class="form-group">
                <input type="text" class="form-control" name="phone" placeholder="Phone Number" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Submit</button>
        </form>
    </div>
</body>
</html>
