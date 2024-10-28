<?php
session_start();
include 'config.php'; // Ensure this file connects to your database using PDO

$message = '';
$message_type = ''; // Variable to store the type of message (success/error)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_submit'])) {
        $otp_entered = implode('', $_POST['otp']); // Combine the inputs into one string

        // Fetch the user's details from the session
        $user_id = $_SESSION['user_id'];

        // Fetch the OTP from the database using PDO
        $query = "SELECT otp_login FROM users WHERE id = :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the OTP entered matches the one in the database
        if ($user && $otp_entered == $user['otp_login']) {
            $message = 'OTP Verified! You may proceed.';
            $message_type = 'success';

            // Redirect after a successful verification
            echo "
            <script>
                setTimeout(function() {
                    window.location.href = 'home.php'; // Change 'home.php' to your actual home page URL
                }, 2000);
            </script>
            ";
        } else {
            $message = 'Invalid OTP. Please try again.';
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IKIMINA MIS - OTP Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
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
            background-color: #ffffff; /* White background for form */
            border-radius: 10px; /* Rounded corners */
            padding: 40px; /* Padding inside the container */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); /* Subtle shadow */
        }
        .form-header {
            text-align: center;
            color: #007bff;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px; /* Space below the header */
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
            background-color: #f8f9fa; /* Input background color */
            color: #495057; /* Input text color */
            border-radius: 5px; /* Rounded corners for inputs */
            outline: none;
            transition: border-color 0.3s;
        }
        .otp-input:focus {
            border-color: #007bff; /* Change border color on focus */
        }
        .btn {
            width: 100%;
            background-color: #007bff; /* Primary button color */
            border: none;
            color: #ffffff; /* Button text color */
            font-weight: 600;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #0056b3; /* Darker color on hover */
        }
        .error-message {
            color: #dc3545; /* Error message color */
            text-align: center; /* Center the error message */
            margin-bottom: 20px; /* Space below the message */
        }
        .form-subheader {
            text-align: center;
            color: gray;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container shadow p-4">
        <h2 class="form-header">IKIMINA MIS</h2>
        <p class="form-subheader">Verify OTP</p>

        <!-- Display the error or success message -->
        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'error' ? 'error-message' : 'success-message'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="otp-inputs">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" name="otp[]" class="otp-input" maxlength="1" required oninput="moveFocus(this)" onkeydown="handleKeyDown(event, this)" pattern="[0-9]*">
                <?php endfor; ?>
            </div>
            <button type="submit" name="verify_submit" class="btn">Verify</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function moveFocus(currentInput) {
            if (currentInput.value.length === 1 && currentInput.nextElementSibling) {
                currentInput.nextElementSibling.focus();
            }
        }

        function handleKeyDown(event, currentInput) {
            if (event.key === 'Backspace' && currentInput.value === '' && currentInput.previousElementSibling) {
                currentInput.previousElementSibling.focus();
            }
        }
    </script>
</body>
</html>
