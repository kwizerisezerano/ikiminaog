<?php
session_start();
include 'config.php'; // Connect to the database using PDO

$message = '';

// Check if the session for 'forgot_user_id' exists
if (!isset($_SESSION['forgot_user_id'])) {
    // Redirect to the OTP request page with an error message if the session is missing
    header("Location: forgot_password.php?error=session_expired");
    exit();
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Combine OTP input into a single string
    $otp_entered = isset($_POST['otp']) ? implode('', $_POST['otp']) : '';

    // Retrieve user ID from session
    $userId = $_SESSION['forgot_user_id'];

    try {
        // Check OTP and user ID in the database
        $query = "SELECT * FROM users WHERE id = :user_id AND otp_forgot = :otp";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':otp', $otp_entered, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Set session for password reset and redirect
            $_SESSION['reset_user_id'] = $userId;
            echo '<script>window.location.href = "reset_password.php";</script>';
            exit();
        } else {
            $message = '<div class="alert alert-danger">Invalid OTP. Please try again.</div>';
        }
    } catch (PDOException $e) {
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
        /* Style adjustments for a clean interface */
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .verify-form-container {
            max-width: 400px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
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
        }
    </style>
</head>
<body>
    <div class="verify-form-container">
        <h2>Verify OTP</h2>
        <p>Enter the OTP sent to your registered phone number</p>
        <?php if ($message) echo $message; ?>
        <form method="post" action="" id="otpForm">
            <div class="otp-inputs">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" name="otp[]" class="otp-input" maxlength="1" required oninput="moveFocus(this)" pattern="[0-9]*">
                <?php endfor; ?>
            </div>
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

            // Automatically submit the form if the last input is filled
            const otpInputs = document.querySelectorAll('.otp-input');
            const allFilled = Array.from(otpInputs).every(input => input.value.length === 1);
            if (allFilled) {
                document.getElementById('otpForm').submit();
            }
        }

        // Restrict input to numbers only
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
