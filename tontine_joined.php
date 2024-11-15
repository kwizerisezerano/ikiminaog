<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);

// Fetch join requests where the logged-in user has joined tontines
$joinRequestsStmt = $pdo->prepare("SELECT tjr.id, tjr.number_place, tjr.amount, tjr.payment_method,tjr.transaction_ref, tjr.status, tjr.request_date, t.tontine_name 
    FROM tontine_join_requests tjr
    JOIN tontine t ON tjr.tontine_id = t.id
    WHERE tjr.user_id = :user_id
    ORDER BY tjr.request_date DESC");
$joinRequestsStmt->execute(['user_id' => $user_id]);
$joinRequests = $joinRequestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Process each join request to check and update payment status
foreach ($joinRequests as $request) {
    // Get transaction reference and current status
    $ref_id = $request['transaction_ref']; // Assuming payment_method contains the transaction reference
    $id = $request['id'];
    $status = $request['status'];

    // Check if the join request is still pending
    if ($status == "Pending") {
        // Check payment status from payment gateway
        $paymentResponse = hdev_payment::get_pay($ref_id);

        // Assuming the gateway response returns an object with a status field
        if (isset($paymentResponse->status)) {
            // Map payment status to join request status
            if ($paymentResponse->status == 'success') {
                $newStatus = "Approved";
            } elseif ($paymentResponse->status == 'failed') {
                $newStatus = "Failure";
            } else {
                $newStatus = "Pending";
            }

            // Update the status in the database
            $updateStmt = $pdo->prepare("UPDATE tontine_join_requests SET status = :status WHERE id = :id");
            $updateStmt->bindValue(':status', $newStatus);
            $updateStmt->bindValue(':id', $id);
            $updateStmt->execute();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Joined Tontines</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 30px; }
        .table-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
  
    <div class="container">
        <h4 class="text-center">Tontines Joined by <?php echo $user_name; ?></h4>
        <div class="table-container">
            <?php if (!empty($joinRequests)): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Tontine</th>
                            <th>Number of Places</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                        
                            <th>Request Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($joinRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['tontine_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['number_place']); ?></td>
                                <td><?php echo htmlspecialchars($request['amount']); ?></td>
                                <td><?php echo htmlspecialchars($request['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($request['status']); ?></td>
                                <td><?php echo htmlspecialchars($request['request_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-center">No tontines joined yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
