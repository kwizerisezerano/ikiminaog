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
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
$total_notifications = 5;

// Fetch all tontines created by the user
$tontineStmt = $pdo->prepare("SELECT id, tontine_name, logo, join_date, province, district, sector, total_contributions, occurrence, time, day, date FROM tontine WHERE id = :user_id");
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
        /* Styles here */
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <!-- Navbar contents as before -->
</nav>

<div class="container">
    <h1 class="text-center mt-4">Tontines Created by <?php echo $user_name; ?></h1>

    <?php foreach ($tontines as $tontine): ?>
        <?php 
            // Handle the occurrence type display
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

            // Handle logo path
            $logoFilePath = htmlspecialchars($tontine['logo']);
            if (empty($tontine['logo']) || !file_exists($logoFilePath)) {
                $logoFilePath = 'uploads/default_logo.png';
            }
        ?>

        <div class="profile-container my-4">
            <div class="row">
                <div class="col-md-4 text-center">
                    <img src="<?php echo $logoFilePath; ?>" alt="Logo" class="profile-logo">
                </div>
                <div class="col-md-8">
                    <div class="profile-header">
                        <?php echo htmlspecialchars($tontine['tontine_name']); ?>
                    </div>
                    <div class="profile-info">
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($tontine['province'] . ', ' . $tontine['district'] . ', ' . $tontine['sector']); ?></p>
                        <p><strong>Join Date:</strong> <?php echo htmlspecialchars($tontine['join_date']); ?></p>
                        <p><strong>Total Contributions:</strong> <?php echo number_format($tontine['total_contributions'], 2); ?> RWF</p>
                        <?php echo $occurrenceDisplay; ?>
                    </div>
                </div>
            </div>
            <div class="profile-buttons text-center mt-3">
                <button class="btn btn-secondary btn-custom" onclick="verifyTontine(<?php echo $tontine['id']; ?>)">Ask Verification</button>
                <button class="btn btn-info btn-custom" onclick="updateTontine(<?php echo $tontine['id']; ?>)">Update</button>
                <button class="btn btn-danger btn-custom" onclick="deleteTontine(<?php echo $tontine['id']; ?>)">Delete</button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
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

    function verifyTontine(id) {
        Swal.fire('Verification Sent!', 'The tontine has been sent for verification.', 'success');
        // Additional logic for verification
    }
</script>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>
