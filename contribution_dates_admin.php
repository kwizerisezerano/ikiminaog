<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$total_notifications=5;
// Fetch user ID from session
$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, image, idno, behalf_name, behalf_phone_number, idno_picture, otp_behalf_used FROM users WHERE id = :id");
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
// Get the selected view (monthly, weekly, or daily)
$view = isset($_GET['view']) ? $_GET['view'] : 'daily'; // default to daily

// Set the number of records per page based on the view
switch($view) {
    case 'monthly':
        $records_per_page = 5;
        break;
    case 'weekly':
        $records_per_page = 10;
        break;
    case 'daily':
    default:
        $records_per_page = 20;
        break;
}

// Get the current page from URL (default to 1 if not set)
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;
// Fetch contribution dates with pagination based on occurrence
try {
    $query = "
    WITH RECURSIVE contribution_dates AS (
        SELECT 
            join_date AS contribution_date,
            occurrence,
            ADDDATE(join_date, INTERVAL 1 YEAR) AS end_date
        FROM tontine
        WHERE id = :id
        UNION ALL
        SELECT 
            CASE
                WHEN occurrence = 'Daily' THEN DATE_ADD(contribution_date, INTERVAL 1 DAY)
                WHEN occurrence = 'Weekly' THEN DATE_ADD(contribution_date, INTERVAL 7 DAY)
                WHEN occurrence = 'Monthly' THEN DATE_ADD(contribution_date, INTERVAL 1 MONTH)
            END AS contribution_date,
            occurrence,
            end_date
        FROM contribution_dates
        WHERE contribution_date < end_date
    )
    SELECT 
        contribution_date 
    FROM contribution_dates
    ORDER BY contribution_date ASC
    LIMIT :start_from, :records_per_page;
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $stmt->bindValue(':start_from', $start_from, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total contribution dates for pagination
    $stmt = $pdo->prepare("
        WITH RECURSIVE contribution_dates AS (
            SELECT 
                join_date AS contribution_date,
                occurrence,
                ADDDATE(join_date, INTERVAL 1 YEAR) AS end_date
            FROM tontine
            WHERE id = :id
            UNION ALL
            SELECT 
                CASE
                    WHEN occurrence = 'Daily' THEN DATE_ADD(contribution_date, INTERVAL 1 DAY)
                    WHEN occurrence = 'Weekly' THEN DATE_ADD(contribution_date, INTERVAL 7 DAY)
                    WHEN occurrence = 'Monthly' THEN DATE_ADD(contribution_date, INTERVAL 1 MONTH)
                END AS contribution_date,
                occurrence,
                end_date
            FROM contribution_dates
            WHERE contribution_date < end_date
        )
        SELECT COUNT(*) AS total_count FROM contribution_dates;
    ");
    $stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $stmt->execute();
    $total_dates = $stmt->fetchColumn();

    $total_pages = ceil($total_dates / $records_per_page);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join <?php echo htmlspecialchars($tontine['tontine_name']); ?> - Ikimina MIS</title>
  <!-- Font Awesome (only one version needed) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- jQuery (for Bootstrap 4/5) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 Bundle (you can remove this if you only want to use Bootstrap 5) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
     <style>
     /* Reduce the height of table cells */
     .table td, .table th {
        padding: 8px 12px; /* Reduced padding for smaller cells */
        font-size: 0.9rem; /* Smaller font size for better readability */
    }

    /* Make the table more compact and aligned */
    .table {
        width: 100%;
        margin-bottom: 0;
    }

    /* Pagination style */
    .pagination {
        margin-top: 20px;
        margin-bottom: 20px; /* Add space below pagination */
    }

    /* Display data in parallel, horizontally */
    .contribution-dates-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: space-between;
    }

    .contribution-date-item {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        width: 48%; /* Each item takes half the width of the container */
        margin-bottom: 15px;
    }

    .contribution-date-item p {
        font-weight: bold;
        color: #007bff;
    }

    /* Adjust table for smaller screens */
    @media (max-width: 768px) {
        .table td, .table th {
            padding: 1px 10px; /* More compact padding for smaller screens */
        }
        .contribution-dates-wrapper {
            flex-direction: column; /* Stack items vertically on smaller screens */
        }
        .contribution-date-item {
            width: 100%; /* Full width on mobile */
        }
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
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link font-weight-bold text-white" href="user_profile.php">Home</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="paymentsDropdown" data-toggle="dropdown">
                    Tontine
                </a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="create_tontine.php">Create tontine</a>
                    <a class="dropdown-item" href="own_tontine.php">Tontine you Own</a>
                    <a class="dropdown-item" href="joined_tontine.php">List of Ibimina you have joined</a>
                </div>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link font-weight-bold text-white d-flex align-items-center" href="#" style="gap: 8px;">
                    <div style="background-color: #ffffff; color: #007bff; font-weight: bold; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 1rem; text-transform: uppercase;">
                        <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                    </div>
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


<div class="container mt-4">
    <h3 class="text-primary">Contribution Dates for <?= htmlspecialchars($tontine['tontine_name']) ?></h3>
    <p>Total Contribution Days: <strong><?= $total_dates ?></strong></p>

    <!-- Pagination at the top -->
    <nav>
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $page - 1 ?>&view=<?= $view ?>">Previous</a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $i ?>&view=<?= $view ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $page + 1 ?>&view=<?= $view ?>">Next</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <?php if (!empty($dates)): ?>
        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Contribution Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dates as $index => $row): ?>
                    <tr>
                        <td><?= $start_from + $index + 1 ?></td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($row['contribution_date']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination at the bottom -->
        <nav>
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $page - 1 ?>&view=<?= $view ?>">Previous</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $i ?>&view=<?= $view ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $page + 1 ?>&view=<?= $view ?>">Next</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php else: ?>
        <div class="alert alert-warning">No contribution dates found.</div>
    <?php endif; ?>
</div>
<script>
    function confirmLogout() {
        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to log out?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, log out',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }
</script>
</body>
</html>
