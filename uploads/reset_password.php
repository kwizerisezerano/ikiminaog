<?php
session_start();
include 'config.php';

if (!isset($_SESSION['reset_user_id'])) {
    header('Location: forgot_password.php?error=session_expired');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    $user_id = $_SESSION['reset_user_id'];

    if ($newPassword !== $confirmPassword) {
        $message = '<div class="alert alert-danger" role="alert">Passwords do not match.</div>';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $newPassword)) {
        $message = '<div class="alert alert-danger" role="alert">Password must be at least 8 characters long, including uppercase, lowercase, numbers, and special characters.</div>';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $query = "UPDATE users SET password = :password, otp_forgot = NULL WHERE id = :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        $message = '<div class="alert alert-success" role="alert">Password has been reset successfully.</div>';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .reset-password-container {
            max-width: 400px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .form-header {
            font-size: 1.5rem;
            color: #007bff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .form-subheader {
            font-size: 0.9rem;
            color: gray;
            margin-bottom: 20px;
        }
        .form-footer {
            font-size: 0.8rem;
            color: gray;
        }
    </style>
    <script>
        function validatePassword() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const message = document.getElementById('message');

            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (!regex.test(password)) {
                message.innerHTML = '<div class="alert alert-danger">Password must be at least 8 characters long, including uppercase, lowercase, numbers, and special characters.</div>';
                return false;
            } else if (password !== confirmPassword) {
                message.innerHTML = '<div class="alert alert-danger">Passwords do not match.</div>';
                return false;
            }
            return true;
        }
    </script>
</head>
<body>
    <div class="reset-password-container">
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
