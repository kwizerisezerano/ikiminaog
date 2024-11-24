<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";
// Fetch user ID from session
$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$user_name = $user ? htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) : "Unknown User";

// Set the number of results per page
$results_per_page =5 ;

// Calculate the starting point for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $results_per_page;

// Check if the search query is set
$search_query = isset($_POST['search_query']) ? '%' . htmlspecialchars($_POST['search_query']) . '%' : '%%';

try {
    // Prepare the SQL statement to fetch tontine details with pagination and search
    $stmt = $pdo->prepare("
    SELECT * 
    FROM tontine 
    WHERE tontine_name LIKE :search_query AND status='Not Justified'
    LIMIT $start_from, $results_per_page
");

    $stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle no results case
    if (!$requests) {
        $error = "No requests found.";
    }
} catch (Exception $e) {
    $error = "Error fetching request details: " . $e->getMessage();
}

// Fetch the count of each request status
$statuses = ['Not Justified', 'Justification Request sent', 'Justified', 'Rejected'];
$status_counts = [];

foreach ($statuses as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tontine WHERE status = :status");
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->execute();
    $status_counts[$status] = $stmt->fetchColumn();
}
$total_notifications=5;
// Total applications count
$total_applications = array_sum($status_counts);

// Calculate the total number of pages
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tontine WHERE tontine_name LIKE :search_query");
$stmt->bindValue(':search_query', $search_query, PDO::PARAM_STR);
$stmt->execute();
$total_requests = $stmt->fetchColumn();
$total_pages = ceil($total_requests / $results_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile Page</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
          /* Reduce the height of table cells */
     .table td, .table th {
        padding: 7px 12px; /* Reduced padding for smaller cells */
        font-size: 0.9rem; /* Smaller font size for better readability */
    }

    /* Make the table more compact and aligned */
    .table {
        width: 100%;
        margin-bottom: 0;
    }
        .container {
    max-width: 1000%; /* Increase container width */
}

.job-card {
    width: 100%; /* Full width for each card */
    max-width: 1000%; /* Set a maximum width for readability */
    border: 1px solid #ddd;
    border-radius: 8px;
    margin: 10px auto; /* Center each card within the container */
    padding: 15px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    background-color: #fff;
    transition: transform 0.3s ease;
    display: flex;
    align-items: center;
}

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        .job-logo {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 15px;
        }
        .job-info {
            flex-grow: 1;
        }
        .job-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
        .company-name {
            font-size: 1rem;
            color: #888;
        }
        .timer {
            color: #ff5e57;
            font-weight: bold;
        }
        .icon {
            color: #007bff;
            margin-right: 8px;
        }
  
        /* Custom styles can be added here */
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            margin-bottom: 20px;
        }
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
</head>
<body>
      <!-- Navbar -->
      <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="sector_dashboard_rejected.php">Rejected Tontines</a>
                </li>
                  <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="sector_dashboard.php">Permition Request</a>
                </li>
                  <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="sector_dashboard_justified.php">Approved Tontines</a>
                </li>
                   <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="sector_dashboard_all.php"> Permition Request Not Sent </a>
                </li>
                <li class="nav-item dropdown" hidden>
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="paymentsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Appeal
                    </a>
                    <div class="dropdown-menu" aria-labelledby="paymentsDropdown">
                        <a class="dropdown-item" href="create_tontine.php">Create tontine</a>
                        <a class="dropdown-item" href="own_tontine.php">Tontine you Own</a>
                     
                        <a class="dropdown-item" href="joined_tontine.php">List of Ibimina you have joined</a>
                    </div>
                </li>
              
                </li>
                <li class="nav-item dropdown" hidden>
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="contributionsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Contributions
                    </a>
                    <div class="dropdown-menu" aria-labelledby="contributionsDropdown">
                        <a class="dropdown-item" href="#">Send contributions</a>
                        <a class="dropdown-item" href="#">View Total Contributions</a>
                    </div>
                </li>
                <li class="nav-item dropdown" hidden>
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="loansDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Loans
                    </a>
                    <div class="dropdown-menu" aria-labelledby="loansDropdown">
                        <a class="dropdown-item" href="#">View loan status</a>
                        <a class="dropdown-item" href="#">Apply for loan</a>
                        <a class="dropdown-item" href="#">Pay for loan</a>
                    </div>
                </li>
                <li class="nav-item dropdown" hidden>
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="penaltiesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Penalties
                    </a>
                    <div class="dropdown-menu" aria-labelledby="penaltiesDropdown">
                        <a class="dropdown-item" href="#">View Paid Penalties</a>
                        <a class="dropdown-item" href="#">View Unpaid Penalties</a>
                        <a class="dropdown-item" href="#">Pay Penalties</a>
                    </div>
                </li>
                <!-- <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="#">Notifications</a>
                </li> -->
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
                    <a class="nav-link font-weight-bold text-white" href="setting_sector.php">
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
    <div class="container">
        <div class="text-center mt-3">
            <h3>Welcome to Sector Dashboard</h3>
            <p>Verification Request Details</p>
        </div>
        <div class="row text-center mb-4 justify-content-center">
            <div class="col-md-2">
                <div class="card bg-outline-info text-info">
                    <div class="card-body">
                        <h6>Total Applications</h6>
                        <p><?php echo $total_applications; ?></p>
                    </div>
                </div>
            </div>
             <div class="col-md-2">
                <div class="card bg-outline-primary text-primary">
                    <div class="card-body">
                        <h6> Request sent</h6>
                        <p><?php echo $status_counts['Justification Request sent']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-outline-primary text-primary">
                    <div class="card-body">
                        <h6>Not Justfied </h6>
                        <p><?php echo $status_counts['Not Justified']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-outline-success text-success">
                    <div class="card-body">
                        <h6>Justified </h6>
                        <p><?php echo $status_counts['Justified']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-outline-danger text-danger">
                    <div class="card-body">
                        <h6>Rejected </h6>
                        <p><?php echo $status_counts['Rejected']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="table-responsive">
                    <nav>
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search_query=<?php echo isset($_GET['search_query']) ? htmlspecialchars($_GET['search_query']) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
            <table class="table table-striped table-hover">
    <thead class="thead-dark">
        <tr>
            <th>#</th>
            <th>Tontine ID</th>
            <th>Name</th>
            <th>Status</th>
            <th>Creation Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($requests)) : ?>
            <?php foreach ($requests as $index => $request) : ?>
                <tr>
                    <td><?= $index + 1 + $start_from; ?></td>
                    <td><?= $request['id']; ?></td>
                    <td><?= htmlspecialchars($request['tontine_name']); ?></td>
                      <td><?= htmlspecialchars($request['status']); ?></td>
                    <td><?= htmlspecialchars($request['created_at']); ?></td>
                  
                    <td>
                  
                        
                        <!-- View Button -->
                        <button class="btn btn-info btn-sm view-btn" data-id="<?= $request['id']; ?>">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="4" class="text-center text-muted">No requests found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

            </div>
        
        <?php endif; ?>
    </div>


    <!-- Remaining content goes here -->

    <script>

    document.addEventListener('DOMContentLoaded', function () {
    // Justify Button
    document.querySelectorAll('.justify-btn').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.dataset.id;
            Swal.fire({
                title: 'Confirm Justification',
                text: "Are you sure you want to justify this request?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Justify it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // AJAX call to justify the request
                    console.log(`Justify request ID: ${id}`);
                }
            });
        });
    });

    // Reject Button
    document.querySelectorAll('.reject-btn').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.dataset.id;
            Swal.fire({
                title: 'Confirm Rejection',
                text: "Are you sure you want to reject this request?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Reject it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // AJAX call to reject the request
                    console.log(`Reject request ID: ${id}`);
                }
            });
        });
    });

    // View Button
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.dataset.id;
            // Navigate to the view page or show details
            window.location.href = `tontine_profile_sector.php?id=${id}`;
        });
    });
});


        
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure?',
                text: "You want to log out!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, log out!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to logout script or perform logout action
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>
</body>
</html>
