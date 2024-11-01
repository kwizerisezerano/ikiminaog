<?php
session_start();
include 'config.php';

$message = '';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $otp_entered = isset($_POST['otp']) ? implode('', $_POST['otp']) : '';

    error_log("Checking OTP for Phone: $phone_number, OTP: $otp_entered"); 

    try {
        $query = "SELECT * FROM users WHERE phone_number = :phone_number AND otp = :otp AND otp_used = 0";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
        $stmt->bindParam(':otp', $otp_entered, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $update_query = "UPDATE users SET verified = 1, otp_used = 1, updated_at = CURRENT_TIMESTAMP WHERE phone_number = :phone_number";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);

            if ($update_stmt->execute()) {
                $message = '<div class="alert alert-success">Your account is activated!</div>';
                
                // Immediately redirect to index.php after successful verification
                header("Location: index.php");
                exit(); // Ensure no further code is executed after redirect
            } else {
                $message = '<div class="alert alert-danger">Error verifying account. Please try again.</div>';
            }
        } else {
            error_log("Invalid OTP: Phone: $phone_number, OTP: $otp_entered"); 
            $message = '<div class="alert alert-danger">Invalid or already used OTP. Please try again.</div>';
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage()); 
        $message = '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

if (isset($_GET['phone_number'])) {
    $phone_number = $_GET['phone_number'];
} else {
    header("Location: user_registration.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Account - IKIMINA MIS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

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
    </style>
</head>
<body>
    <div class="verify-form-container">
        <h2 class="form-header">IKIMINA MIS</h2>
        <p class="form-subheader">Verify Your Account<br>by entering the code sent to your phone</p>
       
        <?php if ($message) echo $message; ?>
        <form id="otpForm" method="post" action="">
            <input type="hidden" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>">
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

            // Automatically submit the form when the last input is filled
            const otpInputs = document.querySelectorAll('.otp-input');
            const allFilled = Array.from(otpInputs).every(input => input.value.length === 1);

            if (allFilled) {
                document.getElementById('otpForm').submit(); // Submit the form
            }
        }

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
