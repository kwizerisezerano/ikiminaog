<?php
session_start();
include('config.php'); // Database connection

if (isset($_GET['phone_number'])) {
    $on_behalf_contact = $_GET['phone_number'];

    // Retrieve OTP and used status for the on behalf contact number
    $stmt = $pdo->prepare("SELECT otp_behalf, otp_behalf_used FROM users WHERE behalf_phone_number = :phone_number");
    $stmt->bindParam(':phone_number', $on_behalf_contact, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch();

    if ($user) {
        // Check if OTP has already been used
        if ($user['otp_behalf_used'] == 1) {
            // Redirect to index.php if OTP has already been used
            header("Location: index.php");
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Join the OTP array into a single string
            $entered_otp = isset($_POST['otp']) ? implode('', $_POST['otp']) : '';  
            $entered_otp = trim($entered_otp);
            $errors = [];

            // Validate OTP
            if (empty($entered_otp)) {
                $errors[] = "OTP is required.";
            }

            if ($entered_otp != $user['otp_behalf'] || $user['otp_behalf_used'] == 1) {
                $errors[] = "Invalid OTP or OTP has already been used.";
            }

            if (empty($errors)) {
                // OTP is correct, update OTP used status
                $updateStmt = $pdo->prepare("UPDATE users SET otp_behalf_used = 1 WHERE behalf_phone_number = :phone_number");
                $updateStmt->bindParam(':phone_number', $on_behalf_contact, PDO::PARAM_STR);
                $updateStmt->execute();

                // Redirect to index.php after successful OTP verification
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'OTP Verified',
                        text: 'You will be redirected shortly.',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'index.php';
                        }
                    });
                </script>";
                exit;
            } else {
                // Show errors if OTP is incorrect
                $_SESSION['otp_error'] = implode('<br>', $errors);
            }
        }
    } else {
        // No user found for the provided phone number
        $_SESSION['otp_error'] = "No user found with this phone number.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
        <h2 class="form-header text-primary">IKIMINA MIS</h2>
        <p>Enter the OTP sent to the on behalf contact number: <?php echo htmlspecialchars($on_behalf_contact); ?></p>

        <?php
        // Display error or success message using SweetAlert
        if (isset($_SESSION['otp_error'])) {
            echo '<script>
                Swal.fire({
                    icon: "error",
                    title: "OTP Error",
                    html: "' . $_SESSION['otp_error'] . '",
                    confirmButtonText: "OK"
                });
            </script>';
            unset($_SESSION['otp_error']); // Clear the error after displaying
        }
        ?>

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
