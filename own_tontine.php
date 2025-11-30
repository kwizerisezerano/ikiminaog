<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$total_notifications = 5;

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);

// Fetch all tontines created by the user
$tontineStmt = $pdo->prepare("SELECT id, tontine_name, logo, join_date, province, district, sector, total_contributions, occurrence, time, day, date, rules, purpose FROM tontine WHERE user_id = :user_id ORDER BY join_date DESC");

$tontineStmt->execute(['user_id' => $user_id]);
$tontines = $tontineStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User's Tontines</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #0f73adff;
            --primary-light: #e9f7fe;
            --primary-dark: #0a58ca;
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
        
        body { 
            background: #f8f9fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Header styling with container */
        .page-header-container {
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
            width: 100%;
        }
        
        .page-header {
            padding: 1.5rem 0;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--primary-color);
        }
        
        .page-subtitle {
            font-size: 0.95rem;
            color: #6c757d;
        }
        
        /* Search bar */
        .search-container {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .search-input {
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 8px 15px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: #f8f9fa;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(15, 115, 173, 0.15);
            background: white;
            outline: none;
        }
        
        /* Tontine card styling */
        .tontine-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.2s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .tontine-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: rgba(15, 115, 173, 0.2);
        }
        
        .card-header {
            padding: 1rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .tontine-logo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
            line-height: 1.3;
        }
        
        .card-subtitle {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        /* Information grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 6px;
            border-left: 3px solid #dee2e6;
        }
        
        .info-label {
            font-weight: 500;
            color: #495057;
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .info-label i {
            color: var(--primary-color);
            font-size: 0.8em;
        }
        
        .info-value {
            color: #2c3e50;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        /* Highlight item */
        .highlight-item {
            background: var(--primary-light);
            border-left: 3px solid var(--primary-color);
        }
        
        .highlight-item .info-label {
            color: var(--primary-dark);
        }
        
        .highlight-item .info-value {
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        /* Buttons */
        .card-actions {
            padding: 0 1rem 1rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-custom {
            border-radius: 6px;
            padding: 6px 12px;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .btn-primary-custom {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .btn-primary-custom:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-top: 1.5rem;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .empty-state-text {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }
        
        /* Navbar */
        .navbar {
         
            padding: 0.5rem 1rem;
        }
        
        .navbar .nav-link {
            color: white !important;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }
        
        .navbar .nav-link:hover {
            color: white !important;
        }
        
        .dropdown-menu {
            border-radius: 6px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .dropdown-item {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        .dropdown-item:hover {
            background-color: var(--primary-light);
            color: var(--primary-dark);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                flex-direction: column;
                text-align: center;
            }
            
            .tontine-logo {
                width: 60px;
                height: 60px;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark  bg-primary">
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
           
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="user_profile.php">Home</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="paymentsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Tontine
                    </a>
                    <div class="dropdown-menu" aria-labelledby="paymentsDropdown">
                        <a class="dropdown-item" href="create_tontine.php">Create tontine</a>
                        <a class="dropdown-item" href="own_tontine.php">Tontine you Own</a>
                        <a class="dropdown-item" href="joined_tontine.php">List of Ibimina you have joined</a>
                    </div>
                </li>
            </ul>

            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="#" style="gap: 8px;">
                        <div style="background-color: #ffffff; color: var(--primary-color); font-weight: bold; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 1rem; text-transform: uppercase;">
                            <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                        </div>
                        <?php echo htmlspecialchars($user_name); ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="#">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge"><?php echo $total_notifications; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="setting.php">
                        <i class="fas fa-cog"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="confirmLogout()">
                        <i class="fas fa-sign-out-alt"></i> Log Out
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Page Header (now properly contained) -->
    <div class="page-header-container">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">My Tontines</h1>
                <p class="page-subtitle">Manage and monitor your tontine investments</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Empty State (shown when no tontines) -->
        <?php if (empty($tontines)): ?>
        <div class="empty-state">
            <i class="fas fa-piggy-bank empty-state-icon"></i>
            <h3 class="empty-state-title">No Tontines Found</h3>
            <p class="empty-state-text">You haven't created any tontines yet. Start by creating your first tontine to begin your savings journey.</p>
            <a href="create_tontine.php" class="btn btn-custom btn-primary-custom">
                <i class="fas fa-plus"></i>
                Create Your First Tontine
            </a>
        </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="search-container">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text bg-transparent border-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                </div>
                <input type="text" id="search" class="form-control search-input border-0" placeholder="Search your tontines by name..." onkeyup="searchTontines()">
            </div>
        </div>

        <!-- Tontine Cards List -->
        <div class="row" id="tontine-list">
            <?php foreach ($tontines as $tontine): ?>
            <div class="col-lg-6 tontine-item">
                <div class="tontine-card">
                    <div class="card-header">
                        <img src="<?php echo htmlspecialchars($tontine['logo']); ?>" alt="Tontine Logo" class="tontine-logo">
                        <div>
                            <h5 class="card-title"><?php echo htmlspecialchars($tontine['tontine_name']); ?></h5>
                            <small class="card-subtitle">Active since <?php echo date('M Y', strtotime($tontine['join_date'])); ?></small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-map-marker-alt"></i> Location
                                </div>
                                <div class="info-value"><?php echo htmlspecialchars($tontine['province']); ?></div>
                            </div>
                            
                            <div class="info-item highlight-item">
                                <div class="info-label">
                                    <i class="fas fa-coins"></i> Contributions
                                </div>
                                <div class="info-value"><?php echo htmlspecialchars($tontine['total_contributions']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">
                                    <i class="fas fa-calendar-alt"></i> Schedule
                                </div>
                                <div class="info-value">
                                    <?php
                                    if ($tontine['occurrence'] == 'Daily') {
                                        echo "Daily at " . htmlspecialchars($tontine['time']);
                                    } elseif ($tontine['occurrence'] == 'Weekly') {
                                        echo "Weekly on " . htmlspecialchars($tontine['day']);
                                    } elseif ($tontine['occurrence'] == 'Monthly') {
                                        echo "Monthly on " . htmlspecialchars($tontine['date']);
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item mt-2">
                            <div class="info-label">
                                <i class="fas fa-bullseye"></i> Purpose
                            </div>
                            <div class="info-value"><?php echo htmlspecialchars($tontine['purpose']); ?></div>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-custom btn-primary-custom" onclick="window.location.href='tontine_profile.php?id=<?php echo $tontine['id']; ?>'">
                            <i class="fas fa-eye"></i>
                            View  Details
                        </button>
                       
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    function confirmLogout() {
        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to log out?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0f73adff',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, log out',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }

    function searchTontines() {
        let input = document.getElementById('search').value.toLowerCase();
        let tontineList = document.querySelectorAll('.tontine-item');

        tontineList.forEach(function (item) {
            let tontineName = item.querySelector('.card-title').textContent.toLowerCase();
            if (tontineName.includes(input)) {
                item.style.display = "block";
            } else {
                item.style.display = "none";
            }
        });
    }

    // Alert notification handling
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['alert'])): ?>
            const alertData = <?php echo json_encode($_SESSION['alert']); ?>;
            
            Swal.fire({
                icon: alertData.type,
                title: alertData.title,
                text: alertData.message,
                confirmButtonColor: '#0f73adff'
            });
            
            <?php unset($_SESSION['alert']); ?>
        <?php endif; ?>
    });
</script>
</body>
</html>