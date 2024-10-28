<?php
session_start();
include 'config.php'; // Ensure this file connects to your database using PDO

$message = '';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and trim the OTP input values
    $otp_entered = isset($_POST['otp']) ? implode('', $_POST['otp']) : ''; // Combine OTP input into one string

    // Debugging output to log OTP
    error_log("Checking OTP: $otp_entered"); 

    // Get user ID from session
    $userId = $_SESSION['forgot_user_id'];

    // Validate OTP
    try {
        $query = "SELECT * FROM users WHERE id = :user_id AND otp_forgot = :otp";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':otp', $otp_entered, PDO::PARAM_STR); // Treat OTP as string
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // OTP is valid, redirect to password reset page
            $_SESSION['reset_user_id'] = $userId;
            echo '<script>window.location.href = "reset_password.php";</script>';
            exit();
        } else {
            // Log invalid attempts for debugging
            error_log("Invalid OTP: $otp_entered"); // Log to server error log
            $message = '<div class="alert alert-danger">Invalid OTP. Please try again.</div>';
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage()); // Log database error
        $message = '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP - Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: "Open Sans", sans-serif;
        }
        .verify-form-container {
            max-width: 400px;
            width: 100%;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .verify-form-container h2 {
            margin-bottom: 20px;
            color: #007bff;
            font-weight: bold;
        }
        .otp-inputs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .otp-input {
            width: 50px;
            height: 50px;
            font-size: 24px;
            text-align: center;
            border: 1px solid #6c757d;
            border-radius: 5px;
            outline: none;
            transition: border-color 0.3s;
        }
        .otp-input:focus {
            border-color: #007bff;
        }
        .alert {
            margin-top: 15px;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="verify-form-container">
        <h2>Verify OTP</h2>
        <p>Enter the OTP sent to your registered phone number</p>
       
        <?php if ($message) echo $message; ?>
        <form method="post" action="">
            <div class="otp-inputs">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" name="otp[]" class="otp-input" maxlength="1" required oninput="moveFocus(this)" pattern="[0-9]*">
                <?php endfor; ?>
            </div>
            <button type="submit" class="btn btn-primary">Verify</button>
        </form>
    </div>

    <script>
        function moveFocus(currentInput) {
            if (currentInput.value.length === 1 && currentInput.nextElementSibling) {
                currentInput.nextElementSibling.focus();
            }
            if (currentInput.value.length === 0 && currentInput.previousElementSibling) {
                currentInput.previousElementSibling.focus();
            }
        }

        // Allow only numeric input
        document.querySelectorAll('.otp-input').forEach(input => {
            input.addEventListener('keydown', function(event) {
                if (event.key !== 'Backspace' && event.key !== 'Delete' && (event.key < '0' || event.key > '9')) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
