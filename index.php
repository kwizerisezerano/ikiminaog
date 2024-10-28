<?php
session_start();

include 'config.php'; // Ensure this file connects to your database

// Generate a random OTP
function generateOTP($length = 6) {
    return random_int(100000, 999999);
}

function sendMessage($phoneNumber, $message) {
    // Send SMS using sms_parse.php
    hdev_sms::send('N-SMS', $phoneNumber, $message);
    return "Message sent to $phoneNumber: $message";
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_submit'])) {
        $phoneNumber = $_POST['phone']; // Phone Number
        $password = $_POST['password'];

        // Check if phone number and password are provided
        if (empty($phoneNumber) || empty($password)) {
            $message = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">Both fields are required</div><br>';
        } else {
            // Prepare query to check user by phone number
            $query = "SELECT * FROM users WHERE phone_number = :phone"; // Corrected field name
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':phone', $phoneNumber);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify user exists and password matches
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    // Check if the user is verified
                    if ($user['verified'] == 0) {
                        $message = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">Please verify your account before logging in. Check your phone for a verification link.</div><br>';
                    } else {
                        // Generate OTP
                        $otp = generateOTP();

                        // Update the otp_login in the database
                        $update_query = "UPDATE users SET otp_login = :otp WHERE id = :user_id"; // Corrected field name
                        $update_stmt = $pdo->prepare($update_query);
                        $update_stmt->bindParam(':otp', $otp);
                        $update_stmt->bindParam(':user_id', $user['id']); // Corrected field name
                        $update_stmt->execute();

                        // Send OTP via SMS
                        $smsMessage = "Dear {$user['firstname']}, your OTP for login is: $otp";
                        sendMessage($user['phone_number'], $smsMessage); // Corrected field name

                        // Set session and success message
                        $_SESSION['user_id'] = $user['id']; // Corrected field name
                        $message = '<div class="bg-success text-white" style="border-radius: 8px; padding: 10px;">Login successful. Please check your SMS for the OTP.</div><br>';

                        // Include JavaScript for redirection
                        echo '<script>
                                setTimeout(function() {
                                    window.location.href = "loginverify.php";
                                }, 2000);
                              </script>';
                    }
                } else {
                    $message = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">Invalid password</div><br>';
                }
            } else {
                // User not found
                $message = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">User not found</div><br>';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IKIMINA MIS-Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        .form-header {
            text-align: center;
            color: #007bff;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .form-subheader {
            text-align: center;
            color: gray;
            font-size: 0.9rem;
        }
        .form-footer {
            text-align: center;
            font-size: 0.8rem;
            color: gray;
        }
        .error-text {
            color: red;
            font-size: 0.8rem;
            margin-top: 5px;
            display: none;
        }
        .form-group {
            position: relative;
        }
        .valid-icon {
            color: green;
            font-size: 1.2rem;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
            pointer-events: none;
        }
    </style>
<body>
    <div class="container shadow p-4 bg-white rounded">
        <h2 class="form-header">IKIMINA MIS</h2>
        <p class="form-subheader">Login to your account</p>
        <?php if ($message) echo $message; ?>
        <form method="POST" action="">
            <div class="form-group">
                <input type="text" class="form-control" name="phone" placeholder="Phone Number" required>
                <small class="error-text" id="phone-error"></small>
                <span class="valid-icon" id="phone-valid">✔</span>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" name="password" placeholder="Password" required>
                <small class="error-text" id="password-error"></small>
                <span class="valid-icon" id="password-valid">✔</span>
            </div>
            <button type="submit" name="login_submit" class="btn btn-primary btn-block">Login</button>
        </form>
        <p class="form-footer mt-3">Don't have an account? <a href="user_registration.php">Sign Up</a></p>
        <p class="form-footer mt-3"><a href="forgot_password.php">Forgot password? </a></p>
    </div>
</body>
</html>
