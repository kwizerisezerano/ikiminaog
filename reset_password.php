<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: forgot_password.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];

    // Password validation (backend)
    if ($newPassword !== $confirmPassword) {
        $message = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">Passwords do not match</div><br>';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $newPassword)) {
        $message = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">Password must be at least 8 characters long and include uppercase, lowercase, numbers, and special characters.</div><br>';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $query = "UPDATE users SET password = :password, otp_login = NULL WHERE id = :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        $message = '<div class="bg-success text-white" style="border-radius: 8px; padding: 10px;">Password has been reset successfully</div><br>';
        session_destroy();
        echo '<script>
                setTimeout(function() {
                    window.location.href = "index.php";
                }, 2000);
              </script>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 400px;
            margin: 10px;
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
        .form-group {
            position: relative;
        }
    </style>
    <script>
        function validatePassword() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const message = document.getElementById('message');

            // Regular expression for password validation
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (!regex.test(password)) {
                message.innerHTML = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">Password must be at least 8 characters long and include uppercase, lowercase, numbers, and special characters.</div><br>';
                return false;
            } else if (password !== confirmPassword) {
                message.innerHTML = '<div class="bg-danger text-white" style="border-radius: 8px; padding: 10px;">Passwords do not match</div><br>';
                return false;
            }
            return true;
        }
    </script>
</head>
<body>
    <div class="container shadow p-4 bg-white rounded">
        <h2 class="form-header">IKIMINA MIS</h2>
        <p class="form-subheader">Reset Your Password</p>
        <div id="message"><?php if ($message) echo $message; ?></div>
        <form method="POST" onsubmit="return validatePassword()">
            <div class="form-group">
                <input type="password" class="form-control" name="new_password" id="new_password" placeholder="New Password" required>
            </div>
            <div class="form-group">
                <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
        </form>
        <p class="form-footer mt-3"><a href="index.php">Back to Login</a></p>
    </div>
</body>
</html>
