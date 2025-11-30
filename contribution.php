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
$stmt = $pdo->prepare("SELECT tontine_name, total_contributions, status as tontine_status FROM tontine WHERE id = :id");
$stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if tontine exists
if (!$tontine) {
    die("Tontine not found.");
}

// ENHANCED VALIDATION: Check all required conditions
$stmt = $pdo->prepare("
    SELECT tjr.amount, tjr.status as join_status, tjr.payment_status 
    FROM tontine_join_requests tjr 
    WHERE tjr.tontine_id = :tontine_id AND tjr.user_id = :user_id
");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has joined the tontine
if (!$result || empty($result['amount'])) {
    header("Location: join_tontine.php?id=" . $tontine_id);
    exit();
}

// Store validation errors for SweetAlert display
$validation_error = '';
$redirect_url = 'joined_tontine.php';

// Check if join status is 'Permitted'
if ($result['join_status'] !== 'Permitted') {
    $validation_error = 'Your joining payment has not been approved yet. Because your join payment status failed ,First check your M-Money SMS you will see it as failed trransaction ,then contact tontine Admin';
}
// Check if payment status is 'Approved'
elseif ($result['payment_status'] !== 'Approved') {
    $validation_error = 'Your joining payment has not been approved yet. Because your join payment status failed ,First check your M-Money SMS you will see it as failed trransaction ,then contact tontine Admin ';
}
// Check if tontine status is 'Justified'
elseif ($tontine['tontine_status'] !== 'Justified') {
    $validation_error = 'This tontine is not registered by the sector. Contributions are not allowed at this time.';
}

$total_notifications = 5;
// Get the total contributions for calculation
$total_contributions = $tontine['total_contributions'];

// If all validations pass, continue processing...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contribute-<?php echo htmlspecialchars($tontine['tontine_name']); ?> - Ikimina MIS</title>
    <!-- Font Awesome (only one version needed) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery (for Bootstrap 4/5) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 4 Bundle (you can remove this if you only want to use Bootstrap 5) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
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
        /* Custom CSS */
        body {
            background-color: #d6dce5;
            font-family: Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
        }
        
        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 56px); /* Subtract navbar height */
            padding: 20px;
        }
        
        .form-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .form-title {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-section {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .btn-submit {
            width: 100%;
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
            border: none;
        }
        .btn-submit:hover {
            background-color: #0056b3;
        }
        .form-check-label {
            font-size: 0.9rem;
        }
        .status-indicator {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
        .status-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
               
               
            </ul>

            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link font-weight-bold text-white d-flex align-items-center" href="#" style="gap: 8px;">
                        <div style="background-color: #ffffff; color: #007bff; font-weight: bold; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 1rem; text-transform: uppercase;">
                            <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                        </div>
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

    <div class="main-content">
        <div class="form-container">
            <h5 class="form-title">Welcome to Contribute in <?php echo htmlspecialchars($tontine['tontine_name']); ?></h5>
            
            <!-- Status Indicators -->
            <?php if (empty($validation_error)): ?>
            <div class="status-indicator status-success">
                <i class="fas fa-check-circle"></i> All requirements met - You can now contribute!
            </div>
            <?php endif; ?>
            
            <form id="joinForm" method="POST">
                <input type="hidden" name="tontine_id" value="<?php echo $tontine_id; ?>">
                <input type="hidden" id="total_contributions" value="<?php echo $total_contributions; ?>">

                <div class="mb-3">
                    <label for="amount" class="form-label">Amount</label>
                    <input type="text" class="form-control" id="amount" name="amount" readonly value="<?php echo $result['amount'];?>">
                </div>

                <div class="mb-3">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <input type="text" class="form-control" id="payment_method" name="payment_method" value="<?php echo $user['phone_number']; ?>" readonly>
                </div>

                <button type="submit" class="btn btn-submit" id="submitBtn">Send Contribution</button>
            </form>
        </div>
    </div>

    <script>
        // Check for validation errors and display SweetAlert
        <?php if (!empty($validation_error)): ?>
        $(document).ready(function() {
            Swal.fire({
                title: 'Access Denied',
                text: '<?php echo addslashes($validation_error); ?>',
                icon: 'warning',
                confirmButtonText: 'OK',
                confirmButtonColor: '#007bff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?php echo $redirect_url; ?>';
                }
            });
        });
        <?php endif; ?>

        $('#joinForm').on('submit', function(e) {
            e.preventDefault();

            // Check if there are validation errors
            <?php if (!empty($validation_error)): ?>
            Swal.fire({
                title: 'Access Denied',
                text: '<?php echo addslashes($validation_error); ?>',
                icon: 'warning',
                confirmButtonText: 'OK',
                confirmButtonColor: '#007bff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?php echo $redirect_url; ?>';
                }
            });
            return;
            <?php endif; ?>

            $.ajax({
                url: 'submit_contribution.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    console.log(response);  // Log the response to see what is returned
                    try {
                        const res = JSON.parse(response);

                        Swal.fire({
                            title: res.title,
                            text: res.message,
                            icon: res.status === 'success' ? 'success' : 'error',
                            confirmButtonColor: '#007bff'
                        }).then(() => {
                            if (res.status === 'success' && res.redirect_url) {
                                window.location.href = res.redirect_url;
                            }
                        });
                    } catch (e) {
                        Swal.fire({
                            title: 'Parsing Error',
                            text: 'Unexpected response from server.',
                            icon: 'error',
                            confirmButtonColor: '#007bff'
                        });
                    }
                }
            });
        });

        function confirmLogout() {
            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you want to log out?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>
</body>
</html>