<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get the Tontine ID from the URL or POST
$tontine_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$tontine_id) {
    header("Location: tontines.php");
    exit();
}

// Pagination setup for loan requests
$perPage = 5; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure the page number is at least 1
$start = ($page - 1) * $perPage;

try {
    // Fetch the total count of loan requests
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM loan_requests
        WHERE tontine_id = :tontine_id
        AND user_id = :user_id
    ");
    $countStmt->execute([
        'tontine_id' => $tontine_id,
        'user_id' => $_SESSION['user_id'],
    ]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch loan requests for the logged-in user in the given tontine with pagination
    $stmt = $pdo->prepare("
        SELECT lr.id, lr.loan_amount, lr.interest_rate, lr.interest_amount, lr.total_amount, 
               lr.payment_frequency, lr.payment_date, lr.status, lr.phone_number, lr.created_at, lr.late_loan_repayment_amount
        FROM loan_requests lr
        WHERE lr.tontine_id = :tontine_id
        AND lr.user_id = :user_id
        ORDER BY lr.created_at DESC
        LIMIT :start, :perPage
    ");
    $stmt->bindValue(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);  // Filter by logged-in user
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch tontine details
    $tontineStmt = $pdo->prepare("SELECT tontine_name FROM tontine WHERE id = :id");
    $tontineStmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $tontineStmt->execute();
    $tontine = $tontineStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Loan Requests</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .container {
            margin-top: 50px;
        }
        footer {
            margin-top: 50px;
            text-align: center;
            color: black;
            font-weight: bold;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="user_profile.php">Home</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="paymentsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Tontine
                    </a>
                    <div class="dropdown-menu" aria-labelledby="paymentsDropdown">
                        <a class="dropdown-item" href="create_tontine.php">Create tontine</a>
                        <a class="dropdown-item" href="own_tontine.php">Tontine you Own</a>
                        <a class="dropdown-item" href="joined_tontine.php">List of Ibimina you have joined</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="contributionsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Contributions
                    </a>
                    <div class="dropdown-menu" aria-labelledby="contributionsDropdown">
                        <a class="dropdown-item" href="#">Send contributions</a>
                        <a class="dropdown-item" href="#">View Total Contributions</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="loansDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Loans
                    </a>
                    <div class="dropdown-menu" aria-labelledby="loansDropdown">
                        <a class="dropdown-item" href="#">View loan status</a>
                        <a class="dropdown-item" href="#">Apply for loan</a>
                        <a class="dropdown-item" href="#">Pay for loan</a>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="#">Notifications</a>
                </li>
            </ul>

            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="javascript:void(0);" onclick="confirmLogout();">
                        Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-1">
        <h1 class="text-center">Your Loan Requests for <?php echo htmlspecialchars($tontine['tontine_name']); ?></h1>
        <?php if (!empty($loans)): ?>
          <table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Loan Amount</th>
            <th>Interest Rate</th>
            <th>Interest Amount</th>
            <th>Total Amount</th>
            <th>Payment Frequency</th>
            <th>Payment Date</th>
            <th>Late Loan Repayment Amount</th>
            <th>Status</th>
            <th>Phone Number</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($loans as $loan): ?>
    <tr>
        <td><?php echo htmlspecialchars($loan['id']); ?></td>
        <td><?php echo htmlspecialchars($loan['loan_amount']); ?></td>
        <td><?php echo htmlspecialchars($loan['interest_rate']); ?>%</td>
        <td><?php echo htmlspecialchars($loan['interest_amount']); ?></td>
        <td><?php echo htmlspecialchars($loan['total_amount']); ?></td>
        <td><?php echo htmlspecialchars($loan['payment_frequency']); ?></td>
        <td><?php echo htmlspecialchars($loan['payment_date']); ?></td>
        <td><?php echo htmlspecialchars($loan['late_loan_repayment_amount']); ?></td>
        <td><?php echo htmlspecialchars($loan['status']); ?></td> <!-- Status should persist -->
        <td><?php echo htmlspecialchars($loan['phone_number']); ?></td>
        <td>
        <a href="pay_now.php?loan_id=<?php echo $loan['id']; ?>&amount=<?php echo $loan['total_amount']; ?>&payment_date=<?php echo urlencode($loan['payment_date']); ?>&late_amount=<?php echo $loan['late_loan_repayment_amount']; ?>&phone=<?php echo urlencode($loan['phone_number']); ?>&tontine_id=<?php echo $tontine_id; ?>" class="btn btn-success btn-sm">
                Pay Now
            </a>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Pagination -->
<?php
    $totalPages = ceil($totalCount / $perPage);
    if ($totalPages > 1) {
        echo '<nav aria-label="Page navigation"><ul class="pagination">';
        for ($i = 1; $i <= $totalPages; $i++) {
            echo '<li class="page-item"><a class="page-link" href="?id=' . $tontine_id . '&page=' . $i . '">' . $i . '</a></li>';
        }
        echo '</ul></nav>';
    }
?>
        <?php else: ?>
            <p class="alert alert-info">You have no loan requests for this tontine.</p>
        <?php endif; ?>
    </div>
</body>
</html>
