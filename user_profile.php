
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
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, image FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: index.php");
    exit();
}

// Sanitize user data
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);


// Notification count
$total_notifications = 5;


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
                    <a class="nav-link font-weight-bold text-white" href="user_profile.php">Home</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="paymentsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Tontine
                    </a>
                    <div class="dropdown-menu" aria-labelledby="paymentsDropdown">
                        <a class="dropdown-item" href="create_tontine.php">Create tontine</a>
                        <a class="dropdown-item" href="own_tontine.php">Tontine you Own</a>
                        <a class="dropdown-item" href="#">Available list of Ibimina you may join</a>
                        <a class="dropdown-item" href="#">List of Ibimina you have joined</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="accountDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Account
                    </a>
                    <div class="dropdown-menu" aria-labelledby="accountDropdown">
                        <a class="dropdown-item" href="#">View Profile</a>
                        <a class="dropdown-item" href="#">Update Profile</a>
                        <a class="dropdown-item" href="#">Account Status</a>
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
<div class="container mt-1">
<div class="form-group">
    <input type="text" id="search" class="form-control" placeholder="Search Tontines by name..." onkeyup="searchTontines()">
</div>

    <h5>Recent Tontines</h5>
    <div class="row recent-tontines">
    <?php
    // Fetch the 3 most recently created tontines based on the created_at field
    $recentQuery = $pdo->query("SELECT * FROM tontine ORDER BY created_at DESC LIMIT 3");

    while ($tontine = $recentQuery->fetch(PDO::FETCH_ASSOC)) {
        $logo = !empty($tontine['logo']) ? $tontine['logo'] : 'default-logo.png';
        $timeInfo = '';
        if ($tontine['occurrence'] == 'monthly') {
            $timeInfo = "<i class='fas fa-calendar-alt icon'></i> Date: {$tontine['created_at']}";
        } elseif ($tontine['occurrence'] == 'weekly') {
            $timeInfo = "<i class='fas fa-clock icon'></i> Day: {$tontine['day']}";
        } elseif ($tontine['occurrence'] == 'daily') {
            $timeInfo = "<i class='fas fa-clock icon'></i> Time: {$tontine['time']}";
        }

        echo "
        <div class='col-md-4'>
            <div class='job-card'>
                <img src='$logo' alt='Tontine Logo' class='job-logo'>
                <div class='job-info'>
                    <span class='company-name'>{$tontine['tontine_name']}</span>
               
     <div class='job-title'>Contribution:{$tontine['total_contributions']}RWF/Place [{$tontine['occurrence']}]</div>
                   
                    <div class='timer'>$timeInfo</div>
                    <div class='details'>
                        <i class='fas fa-map-marker-alt icon'></i>{$tontine['province']}, {$tontine['district']}, {$tontine['sector']}
                    </div>
                   <button class='btn btn-outline-primary mt-2' onclick='confirmJoinTontine(" . $tontine['id'] . ")'>View Details</button>
                </div>
            </div>
        </div>
        ";
    }
    ?>
</div>


    <h5>All Tontines</h5>
    <div class="job-listings">
        <?php
        // Fetch all tontines
        $query = $pdo->query("SELECT * FROM tontine");

        while ($tontine = $query->fetch(PDO::FETCH_ASSOC)) {
            $logo = !empty($tontine['logo']) ? $tontine['logo'] : 'default-logo.png';
            $timeInfo = '';
            if ($tontine['occurrence'] == 'monthly') {
                $timeInfo = "<i class='fas fa-calendar-alt icon'></i> Date: {$tontine['join_date']}";
            } elseif ($tontine['occurrence'] == 'weekly') {
                $timeInfo = "<i class='fas fa-clock icon'></i> Day: {$tontine['day']}";
            } elseif ($tontine['occurrence'] == 'daily') {
                $timeInfo = "<i class='fas fa-clock icon'></i> Time: {$tontine['time']}";
            }

            echo "
            <div class='job-card'>
                <img src='$logo' alt='Tontine Logo' class='job-logo'>
                <div class='job-info'>
                    <span class='company-name'>{$tontine['tontine_name']}</span>
                    <div class='job-title'>{$tontine['total_contributions']} [{$tontine['occurrence']}]</div>
                    <div class='details'>
                        <i class='fas fa-map-marker-alt icon'></i>{$tontine['province']}, {$tontine['district']}, {$tontine['sector']}
                    </div>
                    <div class='timer mt-2'>
                        $timeInfo
                    </div>
                  <button class='btn btn-outline-primary mt-2' onclick='confirmJoinTontine(" . $tontine['id'] . ")'>View Details</button>
                </div>
            </div>";
        }
        ?>
    </div>
</div>


    <!-- Remaining content goes here -->

    <script>
function confirmJoinTontine(tontineId) {
    // Show SweetAlert confirmation dialog
    Swal.fire({
        title: 'Are you sure you want to view more details about  this tontine?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, show more details ',
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect to tontine_profile_member.php with the tontine ID as a query parameter
            window.location.href = `tontine_profile_member.php?id=${tontineId}`;
        }
    });
}




        function searchTontines() {
    // Get the value from the search input
    const searchInput = document.getElementById('search').value.toLowerCase();
    
    // Get all the tontine cards
    const tontineCards = document.querySelectorAll('.job-card');

    // Loop through the cards and hide/show based on the search input
    tontineCards.forEach(card => {
        const tontineName = card.querySelector('.company-name').textContent.toLowerCase();
        
        // Check if the tontine name includes the search input
        if (tontineName.includes(searchInput)) {
            card.style.display = 'flex'; // Show card
        } else {
            card.style.display = 'none'; // Hide card
        }
    });
}

        
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
