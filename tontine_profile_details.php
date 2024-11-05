<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Sanitize user data
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
$total_notifications = 5;

// Get ID dynamically from query string or default to 1
$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Query to get tontine details including the logo
$stmt = $pdo->prepare("SELECT tontine_name, logo, join_date, province, district, sector, total_contributions, occurrence, time, day, date, rules, purpose FROM tontine WHERE id = :id");
$stmt->execute(['id' => $id]);
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if tontine details were found
if (!$tontine) {
    die("Tontine details not found.");
}

// Build the path for the logo image
$logoFilePath = htmlspecialchars($tontine['logo']);
if (empty($tontine['logo']) || !file_exists($logoFilePath)) {
    $logoFilePath = 'uploads/default_logo.png';
}

// Determine what to display based on the occurrence type (case-insensitive)
$occurrenceDisplay = '';
switch (strtolower($tontine['occurrence'])) {
    case 'daily':
        $occurrenceDisplay = '<p><strong>Time:</strong> ' . htmlspecialchars($tontine['time']) . '</p>';
        break;
    case 'weekly':
        $occurrenceDisplay = '<p><strong>Day:</strong> ' . htmlspecialchars($tontine['day']) . '</p>';
        break;
    case 'monthly':
        $occurrenceDisplay = '<p><strong>Date:</strong> ' . htmlspecialchars($tontine['date']) . '</p>';
        break;
    default:
        $occurrenceDisplay = '<p><strong>Occurrence:</strong> ' . htmlspecialchars($tontine['occurrence']) . '</p>';
        break;
}

// Query to get the members of the tontine
$stmtMembers = $pdo->prepare("SELECT member_list FROM tontine WHERE id = :tontine_id");
$stmtMembers->execute(['tontine_id' => $id]);
$members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tontine Profile</title>
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
        body {
            background-image: url('path/to/background-image.jpg'); /* Replace with the actual path to your background image */
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            font-family: Arial, sans-serif;
            color: #333;
        }
        .profile-container {
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            max-width: 800px;
            background-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: flex-start; /* Align items to the top */
        }
        .profile-logo {
            width: 150px;
            height: auto;
            margin-right: 20px; /* Space between logo and content */
            border-radius: 8px;
        }
        .profile-info {
            flex: 1; /* Take the remaining space */
        }
        .profile-header {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        .profile-info p {
            font-size: 16px;
            margin: 5px 0;
        }
        .button-group {
    display: flex; /* Align buttons in a row */
    gap: 10px; /* Space between buttons */
    margin-top: 20px; /* Space above buttons */
}
        .btn-custom {
            font-size: 14px;
            padding: 8px 12px;
            min-width: 110px;
        }
        .members-list {
            margin-top: 20px;
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

<div class="container profile-container">
    <img src="<?php echo $logoFilePath; ?>" alt="Tontine Logo" class="profile-logo">
    <div class="profile-info">
        <h2 class="profile-header"><?php echo htmlspecialchars($tontine['tontine_name']); ?></h2>
        <p><strong>Join Date:</strong> <?php echo htmlspecialchars($tontine['join_date']); ?></p>
        <p><strong>Province:</strong> <?php echo htmlspecialchars($tontine['province']); ?></p>
        <p><strong>District:</strong> <?php echo htmlspecialchars($tontine['district']); ?></p>
        <p><strong>Sector:</strong> <?php echo htmlspecialchars($tontine['sector']); ?></p>
        <p><strong>Total Contributions:</strong> <?php echo htmlspecialchars($tontine['total_contributions']); ?> RWF</p>
        <?php echo $occurrenceDisplay; ?>
        <p><strong>Rules:</strong> <?php echo htmlspecialchars($tontine['rules']); ?></p>
        <p><strong>Purpose:</strong> <?php echo htmlspecialchars($tontine['purpose']); ?></p>
        
    
        
        <div class="profile-buttons">
              <button class="btn btn-info btn-custom" onclick="updateTontine()">Update</button>
            <button class="btn btn-danger btn-custom" onclick="deleteTontine()">Delete</button>
         
          
        </div>
    </div>
</div>


<script>
    function updateTontine() {
        window.location.href = "update_tontine.php?id=<?php echo $id; ?>"; // Redirect to the update page
    }

  
    function deleteTontine() {
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
                window.location.href = "delete_tontine.php?id=<?php echo $id; ?>"; // Redirect to the delete page
            }
        });
    }

    function confirmLogout() {
        Swal.fire({
            title: 'Are you sure you want to log out?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, log out'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "logout.php"; // Redirect to the logout page
            }
        });
    } 

   
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
