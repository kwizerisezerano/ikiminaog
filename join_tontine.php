<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user ID from session
$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, image,idno,behalf_name,behalf_phone_number ,idno_picture,otp_behalf_used FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: index.php");
    exit();
}
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
// Get tontine ID from the URL
$tontine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch tontine details
$stmt = $pdo->prepare("SELECT tontine_name, total_contributions FROM tontine WHERE id = :id");
$stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if tontine exists
if (!$tontine) {
    die("Tontine not found.");
}

// Get the total contributions for calculation
$total_contributions = $tontine['total_contributions'];

// Notification count
$total_notifications = 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join <?php echo htmlspecialchars($tontine['tontine_name']); ?> - Ikimina MIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <style>
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -0px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 0.80rem;
        }
        /* Custom CSS */
        body {
            background-color: #d6dce5;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        .form-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            margin: 60px auto 0; /* Adds space below the navbar */
        }
        .form-title {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-section {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .btn-submit {
            width: 100%;
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
            border: none;
        }
        .btn-submit:hover {
            background-color: #0056b3;
        }
        .form-check-label {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <!-- Navbar content omitted for brevity -->
</nav>

<div class="form-container mt-3">
    <h5 class="form-title">Welcome to Join <?php echo htmlspecialchars($tontine['tontine_name']); ?></h5>
    
    <p class="form-section">Financial Information</p>

    <form action="submit_join_request.php" method="POST">
        <input type="hidden" name="tontine_id" value="<?php echo $tontine_id; ?>">
        <input type="hidden" id="total_contributions" value="<?php echo $total_contributions; ?>">

        <div class="mb-3">
            <label for="number_place" class="form-label">Number of Place</label>
            <select class="form-select" id="number_place" name="number_place" required>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="text" class="form-control" id="amount" name="amount" readonly>
        </div>

        <div class="mb-3">
            <label for="payment_method" class="form-label">Payment Method</label>
            <input type="text" class="form-control" id="payment_method" name="payment_method" placeholder="Enter your Mobile Money Number" value="<?php echo $user['phone_number'];?>" readonly>
        </div>

        <!-- Terms checkbox with link -->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="terms" onclick="toggleTermsLink()">
            <label class="form-check-label" for="terms">
                I have read and agree to the 
                <a id="termsLink" href="view_terms_join.php?id=<?php echo $tontine_id; ?>" target="_blank" style="text-decoration: none;">terms and conditions</a>
            </label>
        </div>

        <button type="submit" class="btn btn-submit" id="submitBtn" disabled>Submit Join Request</button>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript to calculate the amount based on selected number of places and total contributions
    document.getElementById('number_place').addEventListener('change', function() {
        const numberPlace = parseInt(this.value);
        const totalContributions = parseFloat(document.getElementById('total_contributions').value);
        const amount = numberPlace * totalContributions;
        document.getElementById('amount').value = amount.toFixed(2);
    });

    // Initialize amount based on default number of places
    document.getElementById('number_place').dispatchEvent(new Event('change'));

    function toggleTermsLink() {
        var submitBtn = document.getElementById("submitBtn");
        submitBtn.disabled = !document.getElementById("terms").checked;
    }
</script>
</body>
</html>
