<?php
session_start(); // Start the session

// Database connection
require 'config.php';

// Fetch user ID from session
$user_id = $_SESSION['user_id']; // Replace with your actual session variable for user ID

// Fetch user details
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user) {
    $user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
    $phone_number = htmlspecialchars($user['phone_number']);
} else {
    $user_name = 'User';
    $phone_number = 'N/A';
}

// Simulated total notifications (you should fetch this from your database)
$total_notifications = 5; // Example count
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <!-- Font Awesome -->
    <style>
        .modal-content {
            background-color: transparent !important; /* Removes background color */
            border: none !important; /* Removes border */
        }
        .modal-body {
            padding: 0; /* Removes padding to make it pure image */
        }
        #modalImage {
            border-radius: 0; /* Ensure the image has no border-radius */
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Navbar with menu and submenus -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="paymentsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Join Ikimina
                    </a>
                    <div class="dropdown-menu" aria-labelledby="paymentsDropdown">
                        <a class="dropdown-item" href="#">Available list of Ibimina you may join</a>
                        <a class="dropdown-item" href="#">List of Ibimina you have joined</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="accountDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Account
                    </a>
                    <div class="dropdown-menu" aria-labelledby="accountDropdown">
                        <a class="dropdown-item" href="#">View Profile</a>
                        <a class="dropdown-item" href="#">Update Profile</a>
                        <a class="dropdown-item" href="#">Account Status</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="contributionsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Contributions
                    </a>
                    <div class="dropdown-menu" aria-labelledby="contributionsDropdown">
                        <a class="dropdown-item" href="#">Send contributions</a>
                        <a class="dropdown-item" href="#">View Total Contributions</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="loansDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Loans
                    </a>
                    <div class="dropdown-menu" aria-labelledby="loansDropdown">
                        <a class="dropdown-item" href="#">View loan status</a>
                        <a class="dropdown-item" href="#">Apply for loan</a>
                        <a class="dropdown-item" href="#">Pay for loan</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="penaltiesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Penalties
                    </a>
                    <div class="dropdown-menu" aria-labelledby="penaltiesDropdown">
                        <a class="dropdown-item" href="#">View Paid Penalties</a>
                        <a class="dropdown-item" href="#">View Unpaid Penalties</a>
                        <a class="dropdown-item" href="#">Pay Penalties</a>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Notifications</a>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link position-relative" href="#"><i class="fas fa-bell"></i>
                        <span class="notification-badge"><?php echo $total_notifications; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#"><i class="fas fa-cog"></i></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> Log Out</a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Profile Card -->
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Profile</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <img src="images/yves.jpg" alt="Profile Picture" class="rounded-circle mb-3" width="100" height="100" onclick="openModal('images/yves.jpg')">
                        <button class="btn btn-link" onclick="updatePhoto()"><i class="fas fa-camera"></i> Update Photo</button>
                    </div>
                    <div class="col-md-8">
                        <h4><?php echo $user_name; ?></h4>
                        <p>Phone: <?php echo $phone_number; ?></p>
                        <button class="btn btn-link" onclick="updateProfile()"><i class="fas fa-user-edit"></i> Update</button>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Account Options</h5>
                        <p><i class="fas fa-language"></i> Language: <strong>English</strong></p>
                        <p><i class="fas fa-clock"></i> Time Zone: <strong>GMT-08:00 Pacific Time</strong></p>
                        <p><i class="fas fa-flag"></i> Nationality: <strong>Rwanda</strong></p>
                    </div>
                    <div class="col-md-6">
                        <h5>Contact Information</h5>
                        <p><i class="fas fa-envelope"></i> Email: <strong>tabitakwizerisezerano@gmail.com</strong></p>
                        <p><i class="fas fa-phone"></i> Phone: <strong><?php echo $phone_number; ?></strong></p>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <button class="btn btn-primary" onclick="confirmSaveChanges()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for enlarged image -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0">
                <div class="modal-body text-center p-0">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="position: absolute; top: 10px; right: 10px; background: none; border: none; color: white; font-size: 1.5rem;">&times;</button>
                    <img id="modalImage" src="" class="img-fluid" alt="Enlarged Image">
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to confirm logout
        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure you want to log out?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'No, cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php'; // Replace with your logout script
                }
            });
        }

        // Function to confirm saving changes
        function confirmSaveChanges() {
            Swal.fire({
                title: 'Changes saved!',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }

        // Open modal to display enlarged image
        function openModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            $('#imageModal').modal('show');
        }

        // Function to update profile photo
        function updatePhoto() {
            Swal.fire('Update photo functionality to be implemented!');
        }

        // Function to update profile
        function updateProfile() {
            Swal.fire('Update profile functionality to be implemented!');
        }
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
