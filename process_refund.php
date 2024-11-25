<?php
// Get JSON input from the request body
$data = json_decode(file_get_contents('php://input'), true);

// Initialize response array
$response = ['success' => false];

// Check if all the required data is provided
if (isset($data['request_id'], $data['user_id'], $data['payment_method'])) {
    // Extract data
    $request_id = $data['request_id'];
    $user_id = $data['user_id'];
    $payment_method = $data['payment_method'];

    // Make sure the payment method is valid (non-empty, valid format, etc.)
    if (!empty($payment_method)) {
        try {
            // Include database connection (if not already included)
            require 'config.php';

            // Check if the request exists and fetch current status
            $stmt = $pdo->prepare("SELECT status FROM tontine_join_requests WHERE id = :request_id AND user_id = :user_id");
            $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                $response['message'] = 'No request found for the provided ID and user.';
                echo json_encode($response);
                exit; // Stop further processing
            }

            if ($result['status'] === 'Refunded') {
                $response['message'] = 'Refund already processed.';
                echo json_encode($response);
                exit; // Stop further processing
            }

            // Fetch the user's first name from the database
            $stmt = $pdo->prepare("SELECT firstname FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $first_name = $user['firstname'];

                // Fetch the Tontine name using the request ID
                $stmt = $pdo->prepare("SELECT t.tontine_name FROM tontine_join_requests tjr JOIN tontine t ON tjr.tontine_id = t.id WHERE tjr.id = :request_id AND tjr.user_id = :user_id");
                $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();

                // Check if Tontine name is found
                if ($stmt->rowCount() > 0) {
                    $tontine = $stmt->fetch(PDO::FETCH_ASSOC);
                    $tontine_name = $tontine['tontine_name'];

                    // Update the status of the request to 'Refunded'
                    $stmt = $pdo->prepare("UPDATE tontine_join_requests SET status = 'Refunded' WHERE id = :request_id AND user_id = :user_id");
                    $stmt->bindParam(':request_id', $request_id, PDO::PARAM_INT);
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt->execute();

                    // Check if the update was successful
                    if ($stmt->rowCount() > 0) {
                        // Refund was successful, send SMS with personalized message
                        $sms_message = "Dear $first_name, your refund  for the Tontine '$tontine_name' has been processed. Your refund will be credited within three days.";

                        // Assuming the SMS API is set up and working:
                        if (hdev_sms::send('N-SMS', $payment_method, $sms_message)) {
                            $response['success'] = true;
                        } else {
                            $response['message'] = 'Refund processed, but SMS notification failed to send.';
                        }
                    } else {
                        $response['message'] = 'Refund processing failed. No rows updated in the database.';
                    }
                } else {
                    $response['message'] = 'Tontine not found for the given request ID and user ID.';
                }
            } else {
                $response['message'] = 'User not found.';
            }
        } catch (Exception $e) {
            // Catch and handle any exceptions that occur during the process
            error_log('Error processing refund: ' . $e->getMessage());  // Log the error
            $response['message'] = 'Something went wrong while processing the refund. Please try again later.';
        }
    } else {
        $response['message'] = 'Invalid payment method provided.';
    }
} else {
    $response['message'] = 'Required data is missing or incomplete.';
}

// Return the response as JSON
echo json_encode($response);
