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

// Pagination setup
$perPage = 5; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure the page number is at least 1
$start = ($page - 1) * $perPage;

try {
    // Fetch the total count of penalties for the given tontine and user
    $countStmt = $pdo->prepare("SELECT COUNT(*) AS total
        FROM penalties
        WHERE tontine_id = :tontine_id AND user_id = :user_id");
    $countStmt->execute([
        'tontine_id' => $tontine_id,
        'user_id' => $_SESSION['user_id']
    ]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Fetch penalties for the logged-in user in the given tontine with pagination
    $stmt = $pdo->prepare("SELECT p.id, p.penalty_amount, p.infraction_date, p.reason, p.missed_contribution_date, p.status
        FROM penalties p
        WHERE p.tontine_id = :tontine_id AND status='Unpaid'
        AND p.user_id = :user_id
        ORDER BY p.infraction_date DESC
        LIMIT :start, :perPage");
    $stmt->bindValue(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);  // Filter by logged-in user
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();

    $penalties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch the user's phone number
    $userStmt = $pdo->prepare("SELECT phone_number FROM users WHERE id = :user_id");
    $userStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $userStmt->execute();
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

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
    <title>Your Penalties</title>
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
                    <!-- <div class="dropdown-menu" aria-labelledby="paymentsDropdown">
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
            </ul> -->
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <h1 class="text-center">Your Penalties for  <?php echo htmlspecialchars($tontine['tontine_name']); ?></h1>
        <?php if (!empty($penalties)): ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Infraction Date</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Missed Contribution Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($penalties as $penalty): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($penalty['id']); ?></td>
                            <td><?php echo htmlspecialchars($penalty['infraction_date']); ?></td>
                            <td><?php echo htmlspecialchars($penalty['penalty_amount']); ?></td>
                            <td><?php echo htmlspecialchars($penalty['reason']); ?></td>
                            <td><?php echo htmlspecialchars($penalty['missed_contribution_date']); ?></td>
                            <td><?php echo htmlspecialchars($penalty['status']); ?></td>
                            <td>
                            <a href="pay_penalty.php?penalty_id=<?php echo $penalty['id']; ?>&tontine_id=<?php echo $tontine_id; ?>&amount=<?php echo $penalty['penalty_amount']; ?>&phone=<?php echo urlencode($user['phone_number']); ?>" class="btn btn-primary btn-sm">
    Pay
</a>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="text-center">
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= ceil($totalCount / $perPage); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php else: ?>
            <p class="text-center">No penalties found.</p>
        <?php endif; ?>
    </div>

    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure you want to log out?',
                text: "You will be logged out of your account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'No, stay logged in',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>
</body>
</html>
