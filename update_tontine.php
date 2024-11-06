<?php
session_start();
require 'config.php';

// Get ID dynamically from query string
$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

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

// Fetch tontine details to pre-fill the form
$stmt = $pdo->prepare("SELECT tontine_name, logo, join_date, province, district, sector, total_contributions, occurrence, time, day, date,  purpose, rules FROM tontine WHERE id = :id");
$stmt->execute(['id' => $id]);
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tontine) {
    die("Tontine not found.");
}

// Enable error reporting for PDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Initialize variables for feedback messages
$updateSuccess = false;
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Collect form data
        $tontine_name = $_POST['tontine_name'];
        $purpose = $_POST['purpose'];
        $rules = $_POST['rules'];
        $total_contributions = (int)$_POST['total_contributions'];
        $sector = $_POST['sector'];

        // Check for duplicate Tontine name in the same sector
        $checkSql = "SELECT COUNT(*) FROM tontine WHERE tontine_name = :tontine_name AND sector = :sector AND id != :id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':tontine_name', $tontine_name);
        $checkStmt->bindParam(':sector', $sector);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();

        if ($checkStmt->fetchColumn() > 0) {
            $message = "A Tontine with this name already exists in the specified sector.";
            $updateSuccess = false;
        } else {
            // Validate Tontine Name
            if (!preg_match('/^[a-zA-Z\s.,\'-]+$/', $tontine_name)) {
                $message = "Tontine name must contain only letters, spaces, and certain punctuation: ., '-.";
                $updateSuccess = false;
            } else {
                // Prepare the update statement
                $stmt = $pdo->prepare("UPDATE tontine SET tontine_name = :tontine_name, total_contributions = :total_contributions, purpose = :purpose, rules = :rules, sector = :sector WHERE id = :id");
                $stmt->execute([
                    ':tontine_name' => $tontine_name,
                    ':total_contributions' => $total_contributions,
                    ':purpose' => $purpose,
                    ':rules' => $rules,
                    ':sector' => $sector,
                    ':id' => $id
                ]);

                // Set success message
                $message = "Tontine details updated successfully.";
                $updateSuccess = true;
            }
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $updateSuccess = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Tontine</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .notification-badge {
            position: absolute;
            top: -5px;
            right: 0;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 0.80rem;
        }
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .navbar {
            margin-bottom: 20px;
        }
        .container {
            max-width: 600px;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }
        .form-header {
            text-align: center;
            background-color: #007bff;
            color: white;
            font-weight: bold;
            font-size: 1rem;
            padding: 2px 5px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <!-- Navbar content -->
</nav>

<!-- Main Content -->
<div class="container mt-3">
    <h2 class="form-header">Update Tontine Details</h2>
    <form action="" method="post" onsubmit="return validateForm()">
        <div class="mb-3">
            <label for="tontine_name" class="form-label">Tontine Name</label>
            <input type="text" class="form-control" id="tontine_name" name="tontine_name" value="<?php echo htmlspecialchars($tontine['tontine_name']); ?>" required>
        </div>

        <div class="mb-3">
            <label for="purpose" class="form-label">Purpose</label>
            <textarea class="form-control" rows="1" id="purpose" name="purpose" required><?php echo htmlspecialchars($tontine['purpose']); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="rules" class="form-label">Rules</label>
            <textarea rows="1" class="form-control" id="rules" name="rules" required><?php echo htmlspecialchars($tontine['rules']); ?></textarea>
        </div>
        <div class="mb-3">
        <label for="total_contributions" class="form-label">Contribution per place</label>
            <input type="number" class="form-control" id="total_contributions" name="total_contributions" value="<?php echo htmlspecialchars($tontine['total_contributions']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="sector" class="form-label">Sector</label>
            <input type="text" class="form-control" id="sector" name="sector" value="<?php echo htmlspecialchars($tontine['sector']); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Tontine</button>
    </form>
</div>

<script>
    // Check if updateSuccess is true from PHP and show the SweetAlert accordingly
    <?php if ($updateSuccess): ?>
        Swal.fire({
            title: 'Success!',
            text: '<?php echo addslashes($message); ?>',
            icon: 'success',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'tontine_profile_details.php?id=<?php echo $id; ?>';
            }
        });
    <?php elseif ($message): ?>
        Swal.fire({
            title: 'Error!',
            text: '<?php echo addslashes($message); ?>',
            icon: 'error',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>

    function validateForm() {
        const tontineName = document.getElementById('tontine_name').value.trim();
        const totalContributions = document.getElementById('total_contributions').value.trim();
        const purpose = document.getElementById('purpose').value.trim();
        const rules = document.getElementById('rules').value.trim();
        const sector = document.getElementById('sector').value.trim();

        // Validate Tontine Name
        if (!/^[A-Za-z\s.,'-]+$/.test(tontineName)) {
            Swal.fire('Error', 'Tontine name can only contain letters, spaces, and certain punctuation.', 'error');
            return false;
        }

        // Validate Total Contributions
        if (isNaN(totalContributions) || totalContributions < 0) {
            Swal.fire('Error', 'Total contributions must be a positive number.', 'error');
            return false;
        }

        // Validate Purpose and Rules
        if (purpose === '' || rules === '' || sector === '') {
            Swal.fire('Error', 'Purpose, Rules, and Sector cannot be empty.', 'error');
            return false;
        }

        return true;
    }
</script>
</body>
</html>
