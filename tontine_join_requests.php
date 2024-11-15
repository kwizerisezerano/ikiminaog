<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user ID from session
$user_id = $_SESSION['user_id'];

$error = "";
$tontineName = "";
$total_notifications = 5;

// Fetch user details
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
} else {
    $user_name = "Unknown User";  // or handle as needed
}

// Get the tontine ID from the URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Set the number of results per page
$results_per_page = 10;

// Calculate the total number of pages
$query = "SELECT COUNT(*) FROM tontine_join_requests WHERE tontine_id = :id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$total_requests = $stmt->fetchColumn();
$total_pages = ceil($total_requests / $results_per_page);

// Determine the current page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $results_per_page;

// Fetch tontine name and join requests with pagination and search
$search_query = isset($_POST['search_query']) ? '%' . $_POST['search_query'] . '%' : '%%';

try {
    $tontineStmt = $pdo->prepare("SELECT tontine_name FROM tontine WHERE id = :id");
    $tontineStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $tontineStmt->execute();
    $tontine = $tontineStmt->fetch(PDO::FETCH_ASSOC);

    if ($tontine) {
        $tontineName = $tontine['tontine_name'];

        // Fetch join requests
        $stmt = $pdo->prepare("
            SELECT tjr.id, tjr.number_place, tjr.amount, tjr.payment_method, tjr.status, 
                   tjr.request_date, tjr.terms, tjr.transaction_ref, t.tontine_name, 
                   u.firstname, u.lastname
            FROM tontine_join_requests tjr
            JOIN tontine t ON tjr.tontine_id = t.id
            JOIN users u ON tjr.user_id = u.id
            WHERE tjr.tontine_id = :id AND (
                u.firstname LIKE :search_query OR 
                u.lastname LIKE :search_query OR 
                tjr.transaction_ref LIKE :search_query
            )
            LIMIT :start_from, :results_per_page
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':search_query', $search_query, PDO::PARAM_STR);
        $stmt->bindParam(':start_from', $start_from, PDO::PARAM_INT);
        $stmt->bindParam(':results_per_page', $results_per_page, PDO::PARAM_INT);
        $stmt->execute();
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$requests) {
            $error = "No requests found for the provided Tontine ID.";
        }
    } else {
        $error = "Tontine not found.";
    }
} catch (Exception $e) {
    $error = "Error fetching request details: " . $e->getMessage();
}


// Fetch the count of each request status
$statuses = ['Pending', 'Approved', 'Rejected'];
$status_counts = [];

foreach ($statuses as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tontine_join_requests WHERE tontine_id = :id AND status = :status");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->execute();
    $status_counts[$status] = $stmt->fetchColumn();
}

// Total applications count
$total_applications = array_sum($status_counts);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        .card {
    border: 1px solid;
    border-radius: 0.25rem; /* Optional: to make the corners rounded */
    min-height: 40px; /* Adjust this value based on your content */
    display: flex; /* Ensure flexbox is applied on the card as well */
    flex-direction: column;
    align-items: center; /* Center content horizontally */
}

.card-body {
    display: flex;
    flex-direction: column;
    justify-content: center; /* Center content vertically */
    align-items: center; /* Center content horizontally */
    padding: 10px 6px;
    height: 100%; /* Ensures the card body takes up the full height of the card */
}
.table-container {
    width: 100%; /* Increase width to expand slightly beyond container */
    margin: 0 auto; /* Center container within the main body */
    padding: 0; /* Remove extra padding */
    /* margin-left: -70px; */
}

.table-custom-width {
    width: 100%; /* Table takes full width of container */
    margin-left: 0; /* Align table contents to the left */
    padding: 0;
}
/* .actions{
    width: 210px;
} */
    </style>
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
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="penaltiesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Penalties
                </a>
                <div class="dropdown-menu" aria-labelledby="penaltiesDropdown">
                    <a class="dropdown-item" href="#">View Paid Penalties</a>
                    <a class="dropdown-item" href="#">View Unpaid Penalties</a>
                    <a class="dropdown-item" href="#">Pay Penalties</a>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold text-white" href="#">Notifications</a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link font-weight-bold text-white" href="#">
                    <i class="fas fa-user"></i> 
                    <?php echo htmlspecialchars($user_name); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link position-relative font-weight-bold text-white" href="#">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $total_notifications; ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold text-white" href="setting.php">
                    <i class="fas fa-cog"></i>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold text-white" href="#" onclick="confirmLogout()">
                    <i class="fas fa-sign-out-alt"></i> Log Out
                </a>
            </li>
        </ul>
    </div>
</nav>

<div class="container ">
    <div class="text-center">
        <h3>Welcome to, <strong><?php echo htmlspecialchars($tontineName); ?> Dashboard</strong></h3>
        <p>Join Request Details</p>
    </div>

    <!-- Dashboard Overview Boxes -->
    <div class="row text-center mb-4">
    <div class="col-md-3">
        <div class="card bg-outline-info text-info">
            <div class="card-body">
                <h6>Total Applications</h6>
                <p><?php echo $total_applications; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-outline-primary text-primary">
            <div class="card-body">
                <h6>Total Pending Applications</h6>
                <p><?php echo $status_counts['Pending']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-outline-success text-success">
            <div class="card-body">
                <h6>Total Approved Applications</h6>
                <p><?php echo $status_counts['Approved']; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-outline-danger text-danger">
            <div class="card-body">
                <h6>Total Rejected Applications</h6>
                <p><?php echo $status_counts['Rejected']; ?></p>
            </div>
        </div>
    </div>
</div>


    <!-- Join Request Details -->
    <div class="mt-1">
        <?php if ($error): ?>
            <div class="alert alert-danger text-center" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php else: ?>
            <div class="table-responsive table-container">
    <table class="table table-bordered table-custom-width">
                    <thead class="thead-dark">
                    <tr>
                <th>ID</th>
                <th class="no-display">User</th>
                <th class="no-display">Number of Places</th>
                <th class="no-display">Amount</th>
                <th>Payment Method</th>
                <th>Status</th>
                <th>Request Date</th>
                <th>Terms Accepted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $count = 1; // Initialize counter
            foreach ($requests as $request): ?>
                <tr>
                    <td><?php echo $count++; ?></td> <!-- Display count and increment -->
                    <td class="no-display"><?php echo htmlspecialchars($request['firstname'] . ' ' . $request['lastname']); ?></td>
                    <td class="no-display"><?php echo htmlspecialchars($request['number_place']); ?></td>
                    <td class="no-display"><?php echo htmlspecialchars($request['amount']); ?></td>
                    <td><?php echo htmlspecialchars($request['payment_method']); ?></td>
                    <td><?php echo htmlspecialchars($request['status']); ?></td>
                    <td><?php echo htmlspecialchars($request['request_date']); ?></td>
                    <td><?php echo htmlspecialchars($request['terms']); ?></td>
                    <td class="actions">
                                <a href="view_request.php?id=<?php echo $request['id']; ?>" class="btn btn-info btn-sm">View</a>
                                   

                                </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
                </table>
            </div>
        <?php endif; ?>

      
    </div>
     <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?id=<?php echo $id; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?id=<?php echo $id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?id=<?php echo $id; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
</div>
<script>
    function confirmLogout() {
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, log out!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
}

</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
