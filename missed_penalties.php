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
        WHERE p.tontine_id = :tontine_id
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

    // Calculate total penalty amount
    $totalPenalties = array_sum(array_column($penalties, 'penalty_amount'));

} catch (PDOException $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Penalties - <?php echo htmlspecialchars($tontine['tontine_name']); ?></title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
             --primary-color: #1c64d1ff;
            --primary-hover: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --border-radius: 0.75rem;
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.6;
            font-size: 14px;
        }

        /* Header Styles */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="white" opacity="0.1"><polygon points="0,0 1000,0 1000,100 0,20"/></svg>');
            background-size: cover;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .breadcrumb-nav {
            margin-bottom: 1rem;
        }

        .breadcrumb {
            background: transparent;
            margin-bottom: 0;
            padding: 0;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb-item a:hover {
            color: white;
        }

        .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.9);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Stats Cards */
        .stats-section {
            margin-bottom: 2rem;
        }

        .stats-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            height: 100%;
        }

        .stats-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stats-card .icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stats-card.danger .icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .stats-card.warning .icon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stats-card .value {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .stats-card .label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Table Styles */
        .table-container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table-header {
            background: var(--light-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .table-header h5 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table {
            margin: 0;
            font-size: 0.875rem;
        }

        .table thead th {
            background: var(--light-bg);
            border-bottom: 2px solid var(--border-color);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            padding: 1rem 1.5rem;
            border-top: none;
        }

        .table tbody td {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: rgba(220, 38, 38, 0.025);
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
        }

        .status-paid {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .status-unpaid {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }

        /* Amount Display */
        .amount-display {
            font-weight: 600;
            color: var(--danger-color);
            font-size: 0.9375rem;
        }

        /* Action Buttons */
        .btn-pay {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-pay:hover {
            background: linear-gradient(135deg, var(--primary-hover) 0%, #991b1b 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Pagination */
        .pagination {
            justify-content: center;
            gap: 0.25rem;
        }

        .page-link {
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
        }

        .page-link:hover {
            background-color: var(--light-bg);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
        }

        .empty-state .icon {
            width: 4rem;
            height: 4rem;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
        }

        .empty-state h4 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .table-responsive {
                font-size: 0.8125rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.75rem 1rem;
            }
        }

        /* Animation */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease-out;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="page-header">
        <div class="container">
            <div class="header-content">
                <!-- Breadcrumb Navigation -->
                <nav class="breadcrumb-nav">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="user_profile.php">
                                <i class="fas fa-home me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="tontine_profile_member.php?id=<?php echo $tontine_id; ?>">Back to tontine profile</a>
                        </li>
                        <li class="breadcrumb-item active">Your Penalties</li>
                    </ol>
                </nav>

                <!-- Page Title -->
                <h1 class="page-title">
                    <i class="fas fa-gavel"></i>
                    Your Penalties
                </h1>
                <p class="page-subtitle">
                    <?php echo htmlspecialchars($tontine['tontine_name']); ?> - Manage your penalty payments
                </p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Statistics Section -->
        <?php if (!empty($penalties)): ?>
            <div class="stats-section fade-in">
                <div class="row">
                    <div class="col-md-6 col-lg-4">
                        <div class="stats-card danger">
                            <div class="icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="value"><?php echo count($penalties); ?></div>
                            <div class="label">Total Penalties</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="stats-card warning">
                            <div class="icon">
                                RWF
                            </div>
                            <div class="value"><?php echo number_format($totalPenalties, 2); ?></div>
                            <div class="label">Total Amount Due</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Penalties Table -->
            <div class="table-container fade-in">
                <div class="table-header">
                    <h5>
                        <i class="fas fa-list-alt"></i>
                        Penalty Details
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th scope="col">
                                    <i class="fas fa-hashtag me-1"></i>ID
                                </th>
                                <th scope="col">
                                    <i class="fas fa-calendar-alt me-1"></i>Infraction Date
                                </th>
                                <th scope="col">
                                    <i class="fas fa-money-bill-wave me-1"></i>Amount
                                </th>
                                <th scope="col">
                                    <i class="fas fa-exclamation-circle me-1"></i>Reason
                                </th>
                                <th scope="col">
                                    <i class="fas fa-calendar-times me-1"></i>Missed Date
                                </th>
                                <th scope="col">
                                    <i class="fas fa-info-circle me-1"></i>Status
                                </th>
                                <th scope="col">
                                    <i class="fas fa-cogs me-1"></i>Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($penalties as $penalty): ?>
                                <tr>
                                    <td>
                                        <span class="text-muted">#<?php echo str_pad($penalty['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                    </td>
                                    <td>
                                        <span><?php echo date('M d, Y', strtotime($penalty['infraction_date'])); ?></span>
                                        <br>
                                        <small class="text-muted">
                                            <?php 
                                                $daysAgo = (new DateTime())->diff(new DateTime($penalty['infraction_date']))->days;
                                                echo $daysAgo . ' days ago';
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="amount-display">
                                            RWF <?php echo number_format($penalty['penalty_amount'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php echo htmlspecialchars($penalty['reason']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($penalty['missed_contribution_date']): ?>
                                            <span><?php echo date('M d, Y', strtotime($penalty['missed_contribution_date'])); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = 'status-pending';
                                        $statusIcon = 'fas fa-clock';
                                        if (strtolower($penalty['status']) === 'paid') {
                                            $statusClass = 'status-paid';
                                            $statusIcon = 'fas fa-check-circle';
                                        } elseif (strtolower($penalty['status']) === 'unpaid') {
                                            $statusClass = 'status-unpaid';
                                            $statusIcon = 'fas fa-times-circle';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <i class="<?php echo $statusIcon; ?>"></i>
                                            <?php echo htmlspecialchars($penalty['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (strtolower($penalty['status']) !== 'paid'): ?>
                                            <a href="pay_penalty.php?penalty_id=<?php echo $penalty['id']; ?>&tontine_id=<?php echo $tontine_id; ?>&amount=<?php echo $penalty['penalty_amount']; ?>&phone=<?php echo urlencode($user['phone_number']); ?>" 
                                               class="btn-pay">
                                                <i class="fas fa-credit-card"></i>
                                                Pay Now
                                            </a>
                                        <?php else: ?>
                                            <span class="text-success fw-semibold">
                                                <i class="fas fa-check-circle me-1"></i>
                                                Paid
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if (ceil($totalCount / $perPage) > 1): ?>
                <div class="d-flex justify-content-center mt-4 fade-in">
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min(ceil($totalCount / $perPage), $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < ceil($totalCount / $perPage)): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?php echo $tontine_id; ?>&page=<?php echo $page + 1; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state fade-in">
                <div class="icon">
                    <i class="fas fa-thumbs-up"></i>
                </div>
                <h4>No Penalties Found!</h4>
                <p>Great news! You have no penalties for this tontine. Keep up the good work with your contributions!</p>
                <div class="mt-3">
                    <a href="user_profile.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Fade-in animation
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.fade-in').forEach(el => {
                observer.observe(el);
            });
        });

        // Logout confirmation function
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure you want to log out?',
                text: "You will be logged out of your account.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'No, stay logged in',
                reverseButtons: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Enhanced payment confirmation
        document.querySelectorAll('.btn-pay').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const url = new URL(this.href);
                const amount = url.searchParams.get('amount');
                
                Swal.fire({
                    title: 'Confirm Penalty Payment',
                    text: `You are about to pay RWF ${parseFloat(amount).toFixed(2)} for this penalty. Do you want to proceed?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Pay Now',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = this.href;
                    }
                });
            });
        });

        // Tooltip initialization
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>