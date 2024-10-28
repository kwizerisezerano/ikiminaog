<?php
session_start(); // Start the session to use session variables

// Include the database configuration file
require 'config.php'; // Make sure you have a config.php with your database connection settings

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Retrieve form data
$firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';

$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$terms_agreed = isset($_POST['terms']) ? 1 : 0; // Will be 1 if checked, 0 if not

// Initialize response array
$response = ['error' => false, 'message' => ''];

// Validate inputs
if (empty($firstname) || empty($lastname) || empty($phone_number)  || empty($password)) {
    $response['error'] = true;
    $response['message'] = 'All fields are required.';
    echo json_encode($response);
    exit;
}

// Check if phone number already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = :phone_number");
$stmt->execute([':phone_number' => $phone_number]);

if ($stmt->rowCount() > 0) {
    $response['error'] = true;
    $response['message'] = 'Phone Number already exists.';
    echo json_encode($response);
    exit;
}

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$otp = rand(100000, 999999); // Generate a random 6-digit OTP

// Insert new user into the database
$sql = "INSERT INTO users (firstname, lastname, phone_number,password, otp, verified, otp_used, otp_login, terms, created_at) 
        VALUES (:firstname, :lastname, :phone_number,  :password, :otp, 0, 0, 0, :terms, NOW())";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':firstname' => $firstname,
        ':lastname' => $lastname,
        ':phone_number' => $phone_number,
      
        ':password' => $hashedPassword,
        ':otp' => $otp,
        ':terms' => $terms_agreed // Inserts 1 if checked, 0 if not
    ]);

    // Here you would send the OTP to the user's phone number
    // Uncomment and implement your SMS sending logic if necessary
    // Message for OTP verification
    $sms_message = "
        Dear $firstname,\n
        Your OTP for verifying your account is: $otp.\n
        Please use this code to activate your account.\n
        Or click the link below to verify your account:\n
        http://localhost/ikimina/verify.php?phone_number=" . urlencode($phone_number) . "&otp=" . urlencode($otp) . "\n
    ";

    // Attempt to send SMS
    if (!hdev_sms::send('N-SMS', $phone_number, $sms_message)) {
        $response['error'] = true;
        $response['message'] = 'Failed to send SMS.';
        echo json_encode($response);
        exit;
    }


    $response['message'] = 'Registration successful! A verification OTP has been sent to your phone.';
    echo json_encode($response);
} catch (PDOException $e) {
    $response['error'] = true;
    $response['message'] = 'Database error: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}
?>
