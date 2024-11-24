<?php
session_start();
require 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user ID from session
$user_id = $_SESSION['user_id'];

$error = "";
$tontineName = "";
$total_notifications = 5;

// Fetch user details
$stmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
} else {
    $user_name = "Unknown User";  // or handle as needed
}

// Get the tontine join request ID from the URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch the tontine join request details based on the request ID
$stmt = $pdo->prepare("SELECT * FROM tontine_join_requests WHERE id = :id");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if ($request) {
    // Get user details related to the request
    $user_id_from_request = $request['user_id']; // Assuming `user_id` exists in `tontine_join_requests`

    // Fetch user details from `users` table
    $stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, idno_picture,idno FROM users WHERE id = :user_id");

    $stmt->bindParam(':user_id', $user_id_from_request, PDO::PARAM_INT);
    $stmt->execute();
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
    $idno_picture = $user_details ? htmlspecialchars($user_details['idno_picture']) : "Not Available";
    

    if ($user_details) {
        $user_firstname = htmlspecialchars($user_details['firstname']);
        $user_lastname = htmlspecialchars($user_details['lastname']);
       
        $user_phone = htmlspecialchars($user_details['phone_number']);
    } else {
        $user_firstname = $user_lastname = $user_email = $user_phone = "Not Available";
    }

    // Fetch additional financial and emergency contact details if needed (based on your table schema)
    $financial_stmt = $pdo->prepare("SELECT * FROM tontine_join_requests WHERE id = :id");  // Assuming a financial table
    $financial_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $financial_stmt->execute();
    $financial_details = $financial_stmt->fetch(PDO::FETCH_ASSOC);

    // Emergency contact (assuming related data exists)
    $emergency_stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");  // Assuming emergency contact table exists
    $emergency_stmt->bindParam(':user_id', $user_id_from_request, PDO::PARAM_INT);
    $emergency_stmt->execute();
    $emergency_contact = $emergency_stmt->fetch(PDO::FETCH_ASSOC);

    // Assign emergency contact details
    if ($emergency_contact) {
        $emergency_name = htmlspecialchars($emergency_contact['behalf_name']);
        $emergency_phone = htmlspecialchars($emergency_contact['behalf_phone_number']);
    } else {
        $emergency_name = $emergency_phone = "Not Available";
    }
} else {
    $request = null;
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
  /* Prevent scrolling */
  body {
        overflow: hidden;
    }
    .notification-badge {
        position: absolute;
        top: -5px;
        right: 0px;
        background-color: red;
        color: white;
        border-radius: 50%;
        padding: 2px 5px;
        font-size: 0.80rem;
    }
    .id-picture-container img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
    }
    .left-content, .id-picture-container {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    /* Set the container height to fill the viewport without scrolling */
    .container {
        height: 100vh;
        overflow: hidden;
    }

     .modal { display: none; /* Hidden by default */ position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.5); }
  .modal-content { background-color: #fff; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 30%; border-radius: 8px; }
  .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
  .close:hover { color: #000; }
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



<!-- Main Content -->
<div class="container mt-1">
    <div class="row">
        <div class="col-md-8 left-content">
            <h5 class="text-center text-secondary">Pending Membership Requests</h5>
            <h6 class="text-center"><a href="#">Request from <?php echo htmlspecialchars($user_firstname . ' ' . $user_lastname); ?></a></h6>

            <h6>Basic Information</h6>
            <p><strong>First Name:</strong> <?php echo $user_firstname; ?><br>
            <strong>Last Name:</strong> <?php echo $user_lastname; ?><br>
            <strong>Phone:</strong> <?php echo $user_phone; ?></p>

            <h6>Financial Information</h6>
            <p><strong>Number of Place:</strong> <?php echo $financial_details['number_place']; ?><br>
            <strong>Amount:</strong> <?php echo $financial_details['amount']; ?><br>
            <strong>Payment Number:</strong> <?php echo $financial_details['payment_method']; ?></p>

            <h6>Identity Verification</h6>
            <p><strong>National Identification:</strong> <?php echo $user_details['idno']; ?></p>

            <h6>Emergency Contact</h6>
            <p><strong>Name:</strong> <?php echo $emergency_name; ?><br>
            <strong>Phone Number:</strong> <?php echo $emergency_phone; ?></p>
           
            <div class="d-inline-block">
  <button class="btn btn-success" onclick="openModal('Permitted')">Approve</button>
  <button class="btn btn-danger" onclick="openModal('Rejected')">Reject</button>
  <button class="btn btn-warning" onclick="openModal('Pending')">Set Pending</button>
</div>





        </div>

        <div class="col-md-4 d-flex align-items-center justify-content-center">
            <!-- Image container with flexible height matching left content -->
            <div class="id-picture-container text-center">
                <p><strong>National ID:</strong></p>
                <?php if ($idno_picture !== "Not Available"): ?>
                    <img src="<?php echo $idno_picture; ?>" alt="ID Picture" class="img-fluid" style="max-height: 100%; border-radius: 8px;">
                <?php else: ?>
                    Not Available
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- Modal -->
<div id="reasonModal" class="modal" style="display: none;">
  <div class="modal-content">
    <span onclick="closeModal()" class="close">&times;</span>
    <h3 id="modalTitle">Update Request Status</h3>
    <form id="statusForm" onsubmit="submitForm(event)">
      <input type="hidden" id="requestStatus" name="status">
      <input type="hidden" id="requestId" name="request_id">
      <label for="reason">Reason (optional):</label>
      <textarea id="reason" name="reason" class="form-control mb-3"></textarea>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Submit</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>


<script>

// Function to get the request ID from the URL
function getRequestIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

// Open Modal and set status and request ID
function openModal(status) {
    const requestId = getRequestIdFromUrl(); // Get request ID from URL
    if (requestId) {
        document.getElementById('requestStatus').value = status;
        document.getElementById('requestId').value = requestId;
        document.getElementById('reasonModal').style.display = "block"; // Show modal
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Request ID Not Found',
            text: 'The request ID is missing from the URL. Please check and try again.',
        });
    }
}

// Close Modal
function closeModal() {
    document.getElementById('reasonModal').style.display = "none"; // Hide modal
}

// Submit Form with AJAX
function submitForm(event) {
    event.preventDefault();
    const status = document.getElementById("requestStatus").value;
    const reason = document.getElementById("reason").value;
    const requestId = document.getElementById("requestId").value;

    console.log("Submitting form with:", { status, reason, requestId });

    fetch("update_request.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `status=${encodeURIComponent(status)}&reason=${encodeURIComponent(reason)}&request_id=${encodeURIComponent(requestId)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Request Updated',
                text: 'The request has been updated successfully!',
            }).then(() => {
                closeModal();
                location.reload(); // Refresh the page or update UI
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: data.message || 'An unknown error occurred.',
            });
        }
    })
    .catch(error => {
        console.error("Error:", error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while processing your request. Please try again later.',
        });
    });
}


    function confirmLogout() {
        Swal.fire({
            title: 'Are you sure?',
            text: "You will be logged out.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, log out!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }
</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
