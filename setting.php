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
$first_name = htmlspecialchars($user['firstname']);
$last_name = htmlspecialchars($user['lastname']);
$phone_number = htmlspecialchars($user['phone_number']);
$user_image = !empty($user['image']) ? htmlspecialchars($user['image']) : 'default.jpg';

// Notification count
$total_notifications = 5;

$errors = [];
$success_message = "";

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle image upload if the form is submitted
    if (isset($_FILES['profile_image'])) {
        $file_name = $_FILES['profile_image']['name'];
        $file_size = $_FILES['profile_image']['size'];
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
        
        // Allowed file types
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        // Validate file
        if (!in_array($file_type, $allowed_extensions)) {
            $errors[] = "Invalid file type. Please upload a JPG, JPEG, PNG, or GIF image.";
        }

        if ($file_size > 2097152) { // Limit file size to 2MB
            $errors[] = "File size must not exceed 2 MB.";
        }

        if (empty($errors)) {
            // Define the upload path and generate a unique filename
            $upload_path = "uploads/" . uniqid() . '.' . $file_type;

            // Move the uploaded file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Update the database with the new image path
                $stmt = $pdo->prepare("UPDATE users SET image = :image WHERE id = :id");
                $stmt->bindParam(':image', $upload_path);
                $stmt->bindParam(':id', $user_id);
                if ($stmt->execute()) {
                    header("Location: user_profile.php"); // Redirect to the profile page after upload
                    exit();
                } else {
                    $errors[] = "Failed to update the image in the database.";
                }
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    // Profile update
    if (isset($_POST['update_profile'])) {
        $new_first_name = trim($_POST['first_name']);
        $new_last_name = trim($_POST['last_name']);
        $new_phone_number = trim($_POST['phone_number']);

        // Validate inputs
        if (empty($new_first_name) || empty($new_last_name) || empty($new_phone_number)) {
            $errors[] = "All fields are required.";
        }

        if (!preg_match("/^[a-zA-Z ]+$/", $new_first_name)) {
            $errors[] = "First name must contain only letters and spaces.";
        }

        if (!preg_match("/^[a-zA-Z ]+$/", $new_last_name)) {
            $errors[] = "Last name must contain only letters and spaces.";
        }

        if (!preg_match("/^\d{10,15}$/", $new_phone_number)) {
            $errors[] = "Phone number must contain digits only and be between 10 and 15 digits.";
        }
        

        // Check if the new phone number already exists
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone_number = :phone_number AND id != :id");
            $stmt->bindParam(':phone_number', $new_phone_number);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $errors[] = "The phone number you are typing is already assigned to another user. Please use a different phone number.";
            }
        }

        // Update profile if no errors
        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE users SET firstname = :firstname, lastname = :lastname, phone_number = :phone_number WHERE id = :id");
            $stmt->bindParam(':firstname', $new_first_name);
            $stmt->bindParam(':lastname', $new_last_name);
            $stmt->bindParam(':phone_number', $new_phone_number);
            $stmt->bindParam(':id', $user_id);

            if ($stmt->execute()) {
                $success_message = "Profile updated successfully!";
                header("Location: user_profile.php"); // Redirect to profile page
                exit();
            } else {
                $errors[] = "Failed to update profile in the database.";
            }
        }

        // If there are no errors, simulate updating the profile
    if (empty($errors)) {
        // Your database update logic would go here
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: success.php");
        exit();
    } else {
        // If there are errors, encode them for JavaScript
        echo '<script>
            var errors = ' . json_encode($errors) . ';
            if (errors.length > 0) {
                errors.forEach(function(error) {
                    Swal.fire({
                        icon: "error",
                        title: "Error!",
                        text: error,
                        confirmButtonText: "OK"
                    });
                });
            }
        </script>';
    }

    }
}
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
                    Join Ikimina
                </a>
                <div class="dropdown-menu" aria-labelledby="paymentsDropdown">
                   <a class="dropdown-item" href="create_tontine.php">Create tontine</a>
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



    <!-- Profile Card -->
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Profile</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <img src="<?php echo $user_image; ?>" alt="Profile Picture" class="rounded-circle mb-3" width="100" height="100" onclick="openModal('<?php echo $user_image; ?>')">
                        <button class="btn btn-link" data-toggle="modal" data-target="#uploadImageModal"><i class="fas fa-camera"></i> Update Photo</button>
                    </div>
                    <div class="col-md-8">
                        <h4><?php echo $user_name; ?></h4>
                        <p>Phone: <?php echo $phone_number; ?></p>
                        <button class="btn btn-primary" id="updateProfileBtn" data-toggle="modal" data-target="#updateProfileModal">Update Profile</button>
                    </div>
                </div>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger mt-3">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Update Profile Modal -->
    <div class="modal fade" id="updateProfileModal" tabindex="-1" aria-labelledby="updateProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="" method="POST" id="profileForm" onsubmit="return validateForm()">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateProfileModalLabel">Update Profile</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo $first_name; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo $last_name; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <input type="text" name="phone_number" id="phone_number" class="form-control" value="<?php echo $phone_number; ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button> -->
                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Image Modal -->
    <div class="modal fade" id="uploadImageModal" tabindex="-1" aria-labelledby="uploadImageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadImageModalLabel">Update Profile Picture</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="profile_image">Choose New Profile Image</label>
                            <input type="file" name="profile_image" id="profile_image" class="form-control" required>
                        </div>
                        <small class="text-muted">Allowed types: JPG, JPEG, PNG, GIF | Max size: 2 MB</small>
                    </div>
                    <div class="modal-footer">
                        <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button> -->
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image View Modal -->
<div class="modal fade" id="viewImageModal" tabindex="-1" aria-labelledby="viewImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: transparent; border: none;">
            <div class="modal-body p-0">
                <img id="profileImageView" src="" alt="Profile Picture" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>


    <script>
        function openModal(imageSrc) {
            document.getElementById('profileImageView').src = imageSrc;
            $('#viewImageModal').modal('show');
        }

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
        function validateForm() {
    // Get form fields
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const phoneNumber = document.getElementById('phone_number').value.trim();
    const errors = [];

    // Check if fields are empty
    if (!firstName || !lastName || !phoneNumber) {
        errors.push("All fields are required.");
    }

    // Validate first name
    if (!/^[a-zA-Z ]+$/.test(firstName)) {
        errors.push("First name must contain only letters and spaces.");
    }

    // Validate last name
    if (!/^[a-zA-Z ]+$/.test(lastName)) {
        errors.push("Last name must contain only letters and spaces.");
    }

    // Validate phone number
    if (!/^\d{10,15}$/.test(phoneNumber)) {
        errors.push("Phone number must contain digits only and be between 10 and 15 digits.");
    }

    // Show errors if any
    if (errors.length > 0) {
        errors.forEach(function(error) {
            Swal.fire({
                icon: "error",
                title: "Validation Error",
                text: error,
                confirmButtonText: "OK"
            });
        });
        return false; // Prevent form submission
    }

    return true; // Allow form submission
}


    </script>

    <!-- Bootstrap and SweetAlert scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
