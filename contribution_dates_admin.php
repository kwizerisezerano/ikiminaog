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
    <title>Contribution Schedule - <?php echo htmlspecialchars($tontine['tontine_name']); ?> | Ikimina MIS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        /* Professional styling */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-gray: #f8f9fa;
            --border-color: #e3e6ea;
            --shadow: 0 2px 10px rgba(0,0,0,0.08);
            --shadow-hover: 0 4px 20px rgba(0,0,0,0.12);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* Navbar styles remain unchanged */
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

        /* Main content styling */
        .main-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-top: 2rem;
            overflow: hidden;
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
            color: white;
            padding: 2rem;
            position: relative;
        }

        .header-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>') repeat;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .tontine-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .content-section {
            padding: 2rem;
        }

        /* Table styling */
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: var(--light-gray);
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            color: var(--secondary-color);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            padding: 1rem;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background: rgba(0, 123, 255, 0.04);
            transform: translateY(-1px);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .row-number {
            width: 60px;
            text-align: center;
            color: var(--secondary-color);
            font-weight: 500;
        }

        .date-cell {
            font-weight: 500;
            color: var(--primary-color);
        }

        /* Pagination styling */
        .pagination-wrapper {
            background: var(--light-gray);
            padding: 1.5rem;
            margin: 0;
            border-top: 1px solid var(--border-color);
        }

        .pagination {
            margin: 0;
            justify-content: center;
        }

        .page-link {
            border: 1px solid var(--border-color);
            color: var(--primary-color);
            padding: 0.5rem 0.75rem;
            margin: 0 2px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary-color);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--border-color);
            margin-bottom: 1rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .header-section {
                padding: 1.5rem 1rem;
            }

            .tontine-title {
                font-size: 1.4rem;
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .content-section {
                padding: 1rem;
            }

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 500px;
            }

            .pagination-wrapper {
                padding: 1rem;
            }

            .page-link {
                padding: 0.375rem 0.5rem;
                font-size: 0.875rem;
            }
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Status indicators */
        .status-upcoming { color: var(--primary-color); }
        .status-today { color: var(--success-color); font-weight: 600; }
        .status-overdue { color: var(--danger-color); }

        /* Breadcrumb styling */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: white;
        }

        .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.6);
        }
    </style>
</head>
<body>
  

    <div class="container-fluid" style="max-width: 1200px;">
        <div class="main-container">
            <!-- Header Section -->
            <div class="header-section">
                <div class="header-content">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="user_profile.php"><i class="fas fa-home"></i> Home</a></li>
                            <li class="breadcrumb-item"><a href="joined_tontine.php">Tontines</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Contribution Schedule</li>
                        </ol>
                    </nav>

                    <div class="tontine-title">
                        <i class="fas fa-calendar-alt"></i>
                        <?= htmlspecialchars($tontine['tontine_name']) ?>
                    </div>
                    <p class="mb-0" style="opacity: 0.9;">Contribution Schedule & Payment Dates</p>

                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($total_dates) ?></div>
                            <div class="stat-label">Total Contribution Days</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $page ?>/<?= $total_pages ?></div>
                            <div class="stat-label">Current Page</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $records_per_page ?></div>
                            <div class="stat-label">Records per Page</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Section -->
            <div class="content-section">
                <?php if (!empty($dates)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="row-number">#</th>
                                    <th>
                                        <i class="fas fa-calendar-day mr-2"></i>
                                        Contribution Date
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $today = date('Y-m-d');
                                foreach ($dates as $index => $row): 
                                    $date = date('Y-m-d', strtotime($row['contribution_date']));
                                    $status_class = '';
                                    if ($date == $today) {
                                        $status_class = 'status-today';
                                    } elseif ($date < $today) {
                                        $status_class = 'status-overdue';
                                    } else {
                                        $status_class = 'status-upcoming';
                                    }
                                ?>
                                    <tr>
                                        <td class="row-number"><?= $start_from + $index + 1 ?></td>
                                        <td class="date-cell <?= $status_class ?>">
                                            <i class="fas fa-calendar-check mr-2"></i>
                                            <?= date('l, F j, Y', strtotime($row['contribution_date'])) ?>
                                            <?php if ($date == $today): ?>
                                                <span class="badge badge-success ml-2">Today</span>
                                            <?php elseif ($date < $today): ?>
                                                <span class="badge badge-danger ml-2">Overdue</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <nav aria-label="Contribution dates pagination">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $page - 1 ?>&view=<?= $view ?>">
                                            <i class="fas fa-chevron-left mr-1"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?id=<?= $tontine_id ?>&page=1&view=<?= $view ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $i ?>&view=<?= $view ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $total_pages ?>&view=<?= $view ?>"><?= $total_pages ?></a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?id=<?= $tontine_id ?>&page=<?= $page + 1 ?>&view=<?= $view ?>">
                                            Next <i class="fas fa-chevron-right ml-1"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>No Contribution Dates Found</h4>
                        <p>There are currently no contribution dates available for this tontine.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to log out?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'Cancel',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger mx-2',
                    cancelButton: 'btn btn-secondary mx-2'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Add smooth scrolling and loading states
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading state to pagination links
            const paginationLinks = document.querySelectorAll('.pagination .page-link');
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!this.parentElement.classList.contains('active')) {
                        const originalText = this.innerHTML;
                        this.innerHTML = '<div class="loading"></div>';
                        // Note: In a real application, you might want to prevent the default
                        // and handle this with AJAX instead
                    }
                });
            });

            // Animate table rows on load
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html>