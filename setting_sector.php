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
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, image,idno,behalf_name,behalf_phone_number ,idno_picture,otp_behalf_used FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: index.php");
    exit();
}

// Sanitize user data
$otp_behalf_used = $user['otp_behalf_used'];
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
$first_name = htmlspecialchars($user['firstname']);
$last_name = htmlspecialchars($user['lastname']);
$phone_number = htmlspecialchars($user['phone_number']);

$user_idno = htmlspecialchars($user['idno']);

$user_behalf_name = htmlspecialchars($user['behalf_name']);

$user_behalf_contact = htmlspecialchars($user['behalf_phone_number']);
$idno_picture = !empty($user['idno_picture']) ? htmlspecialchars($user['idno_picture']) : 'default.jpg';
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
                    header("Location: setting_sector.php"); // Redirect to the profile page after upload
                    exit();
                } else {
                    $errors[] = "Failed to update the image in the database.";
                }
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }
// Check if the form is submitted for updating the profile
// Check if the form is submitted for updating the profile
// Check if the form is submitted for updating the profile
if (isset($_POST['update_profile'])) {
  // Trim input data
  $new_first_name = trim($_POST['first_name']);
  $new_last_name = trim($_POST['last_name']);
  $new_phone_number = trim($_POST['phone_number']);
  $idno = trim($_POST['idno']);
  $idno_type = $_POST['idnotype'];

  $idno_picture = null;

  // Check if the file is uploaded for IDNO picture
  if (isset($_FILES['idno_picture']) && $_FILES['idno_picture']['error'] == 0) {
      // Get file details
      $file_name = $_FILES['idno_picture']['name'];
      $file_tmp_name = $_FILES['idno_picture']['tmp_name'];
      $file_size = $_FILES['idno_picture']['size'];
      $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

      // Allowed file extensions
      $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

      // Check the file extension and size
      if (in_array($file_extension, $allowed_extensions)) {
          if ($file_size <= 5 * 1024 * 1024) {
              $new_file_name = uniqid('idno_', true) . '.' . $file_extension;
              $upload_dir = 'uploads/';
              $target_file = $upload_dir . $new_file_name;

              // Move the uploaded file to the target directory
              if (move_uploaded_file($file_tmp_name, $target_file)) {
                  $idno_picture = $target_file;
              } else {
                  $errors[] = "Error uploading the file. Please try again.";
              }
          } else {
              $errors[] = "File size exceeds the allowed limit of 5MB.";
          }
      } else {
          $errors[] = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
      }
  }

  // Validate required fields
  if (empty($new_first_name) || empty($new_last_name) || empty($new_phone_number) || empty($idno)) {
      $errors[] = "All fields are required.";
  }

  // Validate names
  if (!preg_match("/^[a-zA-Z ]+$/", $new_first_name)) {
      $errors[] = "First name must contain only letters and spaces.";
  }
  if (!preg_match("/^[a-zA-Z ]+$/", $new_last_name)) {
      $errors[] = "Last name must contain only letters and spaces.";
  }

  // Validate phone number
  if (!preg_match("/^\d{10,15}$/", $new_phone_number)) {
      $errors[] = "Phone number must contain digits only and be between 10 and 15 digits.";
  }

  // Validate IDNO based on type
  if ($idno_type === "Rwandan" && strlen($idno) !== 16) {
      $errors[] = "Rwandan National ID must be exactly 16 characters.";
  } elseif ($idno_type === "Foreign" && !preg_match("/^\d+$/", $idno)) {
      $errors[] = "Foreign IDNO must contain only digits.";
  }

  // Check for duplicate entries (phone number, IDNO)
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone_number = :phone_number AND id != :id");
  $stmt->bindParam(':phone_number', $new_phone_number);
  $stmt->bindParam(':id', $_SESSION['user_id']);
  $stmt->execute();
  if ($stmt->fetchColumn() > 0) {
      $errors[] = "The phone number is already registered to another user.";
  }
  // Check if the IDNO is already registered (for both Rwandan and Foreign)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE idno = :idno AND id != :id");
    $stmt->bindParam(':idno', $idno);
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "The IDNO is already registered to another user.";
    }

  // Proceed with the update if no errors
  if (empty($errors)) {
      $user_id = $_SESSION['user_id'];

      // Prepare the SQL statement to update the user profile
      $stmt = $pdo->prepare("UPDATE users SET
          firstname = :firstname,
          lastname = :lastname,
          phone_number = :phone_number,
          idno = :idno,
          idnotype = :idnotype,
          idno_picture = :idno_picture
          WHERE id = :id");

      $stmt->bindParam(':firstname', $new_first_name);
      $stmt->bindParam(':lastname', $new_last_name);
      $stmt->bindParam(':phone_number', $new_phone_number);
      $stmt->bindParam(':idno', $idno);
      $stmt->bindParam(':idnotype', $idno_type);
      $stmt->bindParam(':idno_picture', $idno_picture);
      $stmt->bindParam(':id', $user_id);

      // Execute the update
      if ($stmt->execute()) {
          echo "<script>alert('Profile updated successfully');</script>";
      } else {
          $errors[] = "Failed to update the profile. Please try again.";
      }
  }

  // Display any errors
  if (!empty($errors)) {
      echo '<script>';
      echo 'var errors = ' . json_encode($errors) . ';';
      echo 'if (errors.length > 0) { alert(errors.join("\\n")); }';
      echo '</script>';
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
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white" href="sector_dashboard.php">Verificatin Request</a>
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
                        <p><strong>Phone:</strong> <?php echo $phone_number; ?></p>
                        <p><strong>IDNO: </strong><?php echo $user_idno; ?></p>
 
                      
                         
                       
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
            <form action="" method="POST" id="profileForm" onsubmit="return validateForm()" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateProfileModalLabel">Update Profile</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- First Name -->
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo $first_name; ?>" required>
                    </div>
                    <!-- Last Name -->
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo $last_name; ?>" required>
                    </div>
                    <!-- Phone Number -->
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="text" name="phone_number" id="phone_number" class="form-control" value="<?php echo $phone_number; ?>" required>
                    </div>
                    <!-- IDNO Type -->
                    <div class="form-group">
                        <label for="idnotype">Choose IDNO Type</label>
                        <select name="idnotype" id="idnotype" class="form-control" onchange="toggleIDNOValidation()">
                            <option value="Rwandan">Rwandan</option>
                            <option value="Foreign">Foreign</option>
                        </select>
                    </div>
                    <!-- IDNO (conditional validation) -->
                    <div class="form-group">
                        <label for="idno">IDNO</label>
                        <input type="text" id="idno" name="idno" class="form-control" required value="<?php echo $user_idno; ?>">
                        <span id="idnoError" style="color: red; font-size: 0.9em;"></span>
                    </div>
                    <!-- Picture (Image Upload) -->
                    <div class="form-group">
        <label for="picture">Picture</label>
        <input type="file" name="idno_picture" id="picture" class="form-control-file" accept="image/*" required>
    </div>
  
                   
                </div>
                <div class="modal-footer">
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
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const phoneNumber = document.getElementById('phone_number').value.trim();
        const idno = document.getElementById('idno').value.trim();
        const idnoType = document.getElementById('idnotype').value;
        const picture = document.getElementById('picture').files[0];
        const onBehalfName = document.getElementById('on_behalf_name').value.trim();
        const onBehalfContact = document.getElementById('on_behalf_contact').value.trim();
        const errors = [];

        // Check required fields
        if (!firstName || !lastName || !phoneNumber || !idno || !onBehalfName || !onBehalfContact) {
            errors.push("All fields are required.");
        }

        // Validate name fields
        if (!/^[a-zA-Z ]+$/.test(firstName)) errors.push("First name must contain only letters and spaces.");
        if (!/^[a-zA-Z ]+$/.test(lastName)) errors.push("Last name must contain only letters and spaces.");

        // Validate phone number
        if (!/^\d{10,15}$/.test(phoneNumber)) {
            errors.push("Phone number must contain digits only and be between 10 and 15 digits.");
        }

        // Conditional validation for IDNO
        if (idnoType === "Rwandan") {
            if (!validateIDNO(idno)) {
                errors.push("Invalid Rwandan National ID format.");
            }
        } else {
            if (!/^\d+$/.test(idno)) {
                errors.push("Foreign IDNO must contain only digits.");
            }
        }

        // Validate picture (image file check)
        if (!picture || !['image/jpeg', 'image/png', 'image/jpg'].includes(picture.type)) {
            errors.push("Please upload a valid image file (JPEG or PNG format).");
        }

        // Validate on behalf person name and contact
        if (!/^[a-zA-Z ]+$/.test(onBehalfName)) {
            errors.push("On behalf person name must contain only letters and spaces.");
        }
        if (!/^\d{10,15}$/.test(onBehalfContact)) {
            errors.push("On behalf person contact must contain digits only and be between 10 and 15 digits.");
        }

        // Display errors if any
        if (errors.length > 0) {
            Swal.fire({
                icon: "error",
                title: "Validation Error",
                html: errors.join("<br>"),
                confirmButtonText: "OK"
            });
            return false;
        }

        return true;
    }

    function validateIDNO(idno) {
        if (idno.length !== 16) return false;

        const nationalIdentifier = idno[0];
        if (!['1', '2', '3'].includes(nationalIdentifier)) return false;

        const birthYear = parseInt(idno.substring(1, 5), 10);
        const currentYear = new Date().getFullYear();
        if (birthYear < 1900 || birthYear > currentYear) return false;

        const genderIdentifier = idno[5];
        if (!['8', '7'].includes(genderIdentifier)) return false;

        const birthOrderNumber = idno.substring(6, 13);
        if (!/^\d{7}$/.test(birthOrderNumber)) return false;

        const issueFrequency = idno[13];
        if (!/^\d$/.test(issueFrequency)) return false;

        const securityCode = idno.substring(14, 16);
        if (!/^\d{2}$/.test(securityCode)) return false;

        return true;
    }

    function toggleIDNOValidation() {
        const idnoType = document.getElementById('idnotype').value;
        const idnoInput = document.getElementById('idno');
        const errorSpan = document.getElementById('idnoError');

        if (idnoType === "Rwandan") {
            idnoInput.setAttribute("maxlength", "16");
            errorSpan.textContent = ""; // Clear error message
        } else {
            idnoInput.removeAttribute("maxlength"); // Allow more than 16 digits
            errorSpan.textContent = ""; // Clear error message
        }
    }

    // Real-time validation for IDNO with inline feedback
    document.getElementById('idno').addEventListener('input', function () {
        const idnoType = document.getElementById('idnotype').value;
        const value = this.value;
        const errorSpan = document.getElementById('idnoError');

        if (idnoType === "Rwandan") {
            // Allow up to 16 digits only
            if (!/^\d{0,16}$/.test(value)) {
                this.value = value.slice(0, -1); // Revert last input if invalid
            }
            if (this.value.length < 16) {
                errorSpan.textContent = "IDNO should be exactly 16 digits.";
            } else if (!validateIDNO(this.value)) {
                errorSpan.textContent = "Invalid IDNO format based on Rwandan rules.";
            } else {
                errorSpan.textContent = ""; // Clear error if valid
            }
        } else {
            // No restriction on length for foreign IDNO
            if (!/^\d*$/.test(value)) {
                this.value = value.slice(0, -1); // Revert last input if invalid
                errorSpan.textContent = "Foreign IDNO must contain only digits.";
            } else {
                errorSpan.textContent = ""; // Clear error if valid
            }
        }
    });
</script>

    <!-- Bootstrap and SweetAlert scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
