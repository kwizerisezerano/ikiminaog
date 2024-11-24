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
$tontineStmt = $pdo->prepare("SELECT id, tontine_name, logo, join_date, province, district, sector, total_contributions, occurrence, time, day, date, rules, purpose FROM tontine WHERE user_id = :user_id");
$tontineStmt->execute(['user_id' => $user_id]);
$tontines = $tontineStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User's Tontines</title>
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
        body { background-color: #f8f9fa; }
        
        /* Container for each tontine card */
        .tontine-card {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        /* Logo styling */
        .tontine-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            margin-right: 15px;
        }
        
        /* Content on the right of the logo */
        .card-content {
            flex: 1;
        }
        
        /* Card buttons */
        .card-buttons {
            margin-top: 10px;
        }

        .btn-custom {
            font-size: 0.9rem;
            margin-right: 5px;
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
<div class="container">
    <h5 class="text-center mt-4">Tontines Created by <?php echo htmlspecialchars($user_name); ?></h5>
    
    <!-- Search Bar -->
    <div class="form-group">
        <input type="text" id="search" class="form-control" placeholder="Search Tontines by name..." onkeyup="searchTontines()">
    </div>

    <!-- Tontine Cards List -->
    <div class="row" id="tontine-list">
        <?php foreach ($tontines as $tontine): ?>
            <div class="col-md-4 tontine-item">
                <div class="tontine-card">
                    <img src="<?php echo htmlspecialchars($tontine['logo']); ?>" alt="Tontine Logo" class="tontine-logo">
                    <div class="card-content">
                        <h6 class="card-title"><?php echo htmlspecialchars($tontine['tontine_name']); ?></h6>
                        <p><strong>Province:</strong> <?php echo htmlspecialchars($tontine['province']); ?></p>
                        <p><strong>District:</strong> <?php echo htmlspecialchars($tontine['district']); ?></p>
                        <p><strong>Sector:</strong> <?php echo htmlspecialchars($tontine['sector']); ?></p>
                        <p><strong>Total Contributions:</strong> <?php echo htmlspecialchars($tontine['total_contributions']); ?></p>
                        <p><strong>Rules:</strong> <?php echo htmlspecialchars($tontine['rules']); ?></p>
                        <p><strong>Purpose:</strong> <?php echo htmlspecialchars($tontine['purpose']); ?></p>
                        
                        <?php
                        // Display occurrence-based information
                        if ($tontine['occurrence'] == 'Daily') {
                            echo "<p><strong>Occurrence:</strong> Daily at " . htmlspecialchars($tontine['time']) . "</p>";
                        } elseif ($tontine['occurrence'] == 'Weekly') {
                            echo "<p><strong>Occurrence:</strong> Weekly on " . htmlspecialchars($tontine['day']) . "</p>";
                        } elseif ($tontine['occurrence'] == 'Monthly') {
                            echo "<p><strong>Occurrence:</strong> Monthly on " . htmlspecialchars($tontine['date']) . "</p>";
                        }
                        ?>
<div class="card-buttons d-flex justify-content-start">
   
    <button class="btn btn-outline-primary btn-custom" onclick="window.location.href='tontine_profile.php?id=<?php echo $tontine['id']; ?>'">
        <i class="fas fa-user-circle"></i>  Profile
    </button>
     
</div>

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
            confirmButtonText: 'Yes, log out',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }

    function updateTontine(id) {
        window.location.href = "update_tontine.php?id=" + id;
    }

    function deleteTontine(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete_tontine.php?id=" + id;
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
</script>
</body>
</html>
