<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, image FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: index.php");
    exit();
}

$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);

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
            max-width: 1000%;
        }

        .job-card {
            width: 100%;
            max-width: 1000%;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin: 10px auto;
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
                <a class="nav-link dropdown-toggle font-weight-bold text-white" href="#" id="paymentsDropdown" data-toggle="dropdown">
                    Tontine
                </a>
                <div class="dropdown-menu">
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

<div class="container mt-1">
    <div class="form-group">
        <input type="text" id="search" class="form-control" placeholder="Search Tontines by name..." onkeyup="searchTontines()">
    </div>

    <!-- 🔍 No results message -->
    <div id="noResultsMessage" class="alert alert-warning text-center" style="display: none;">
        No tontine matches your search.
    </div>

    <?php
    $recentQuery = $pdo->query("SELECT * FROM tontine ORDER BY created_at DESC LIMIT 3");
    $recentTontines = $recentQuery->fetchAll(PDO::FETCH_ASSOC);

    if (count($recentTontines) > 0): ?>
        <h5>Recent Tontines</h5>
        <div class="row recent-tontines">
        <?php foreach ($recentTontines as $tontine):
            $logo = !empty($tontine['logo']) ? $tontine['logo'] : 'default-logo.png';
            $timeInfo = '';
            if ($tontine['occurrence'] == 'monthly') {
                $timeInfo = "<i class='fas fa-calendar-alt icon'></i> Date: {$tontine['created_at']}";
            } elseif ($tontine['occurrence'] == 'weekly') {
                $timeInfo = "<i class='fas fa-clock icon'></i> Day: {$tontine['day']}";
            } elseif ($tontine['occurrence'] == 'daily') {
                $timeInfo = "<i class='fas fa-clock icon'></i> Time: {$tontine['time']}";
            }
        ?>
            <div class='col-md-4'>
                <div class='job-card'>
                    <img src='<?php echo $logo; ?>' alt='Tontine Logo' class='job-logo'>
                    <div class='job-info'>
                        <span class='company-name'><?php echo htmlspecialchars($tontine['tontine_name']); ?></span>
                        <div class='job-title'>Contribution: <?php echo htmlspecialchars($tontine['total_contributions']); ?> RWF/Place [<?php echo htmlspecialchars($tontine['occurrence']); ?>]</div>
                        <div class='timer'><?php echo $timeInfo; ?></div>
                        <div class='details'>
                            <i class='fas fa-map-marker-alt icon'></i><?php echo "{$tontine['province']}, {$tontine['district']}, {$tontine['sector']}"; ?>
                        </div>
                        <button class='btn btn-outline-primary mt-2' onclick='confirmJoinTontine(<?php echo $tontine["id"]; ?>)'>View Details</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    $query = $pdo->query("SELECT * FROM tontine");
    $allTontines = $query->fetchAll(PDO::FETCH_ASSOC);

    if (count($allTontines) > 0): ?>
        <h5>All Tontines</h5>
        <div class="job-listings">
        <?php foreach ($allTontines as $tontine):
            $logo = !empty($tontine['logo']) ? $tontine['logo'] : 'default-logo.png';
            $timeInfo = '';
            if ($tontine['occurrence'] == 'monthly') {
                $timeInfo = "<i class='fas fa-calendar-alt icon'></i> Date: {$tontine['join_date']}";
            } elseif ($tontine['occurrence'] == 'weekly') {
                $timeInfo = "<i class='fas fa-clock icon'></i> Day: {$tontine['day']}";
            } elseif ($tontine['occurrence'] == 'daily') {
                $timeInfo = "<i class='fas fa-clock icon'></i> Time: {$tontine['time']}";
            }
        ?>
            <div class='job-card'>
                <img src='<?php echo $logo; ?>' alt='Tontine Logo' class='job-logo'>
                <div class='job-info'>
                    <span class='company-name'><?php echo htmlspecialchars($tontine['tontine_name']); ?></span>
                    <div class='job-title'><?php echo htmlspecialchars($tontine['total_contributions']); ?> [<?php echo htmlspecialchars($tontine['occurrence']); ?>]</div>
                    <div class='details'>
                        <i class='fas fa-map-marker-alt icon'></i><?php echo "{$tontine['province']}, {$tontine['district']}, {$tontine['sector']}"; ?>
                    </div>
                    <div class='timer mt-2'><?php echo $timeInfo; ?></div>
                    <button class='btn btn-outline-primary mt-2' onclick='confirmJoinTontine(<?php echo $tontine["id"]; ?>)'>View Details</button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function confirmJoinTontine(tontineId) {
    Swal.fire({
        title: 'Are you sure you want to view more details about this tontine?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, show more details',
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `tontine_profile_member.php?id=${tontineId}`;
        }
    });
}


function searchTontines() {
    const searchInput = document.getElementById('search').value.toLowerCase();
    const tontineCards = document.querySelectorAll('.job-card');
    const noResultsMessage = document.getElementById('noResultsMessage');
    const recentTontinesSection = document.querySelector('.recent-tontines');
    const allTontinesSection = document.querySelector('.job-listings');
    const recentHeading = document.querySelector('h5:nth-of-type(1)');
    const allHeading = document.querySelector('h5:nth-of-type(2)');

    let hasVisibleCard = false;

    tontineCards.forEach(card => {
        const tontineName = card.querySelector('.company-name').textContent.toLowerCase();
        if (tontineName.includes(searchInput)) {
            card.style.display = 'flex';
            hasVisibleCard = true;
        } else {
            card.style.display = 'none';
        }
    });

    noResultsMessage.style.display = hasVisibleCard ? 'none' : 'block';

    // Hide sections if no matching cards inside
    const visibleRecent = recentTontinesSection && [...recentTontinesSection.querySelectorAll('.job-card')].some(card => card.style.display !== 'none');
    const visibleAll = allTontinesSection && [...allTontinesSection.querySelectorAll('.job-card')].some(card => card.style.display !== 'none');

    if (recentTontinesSection) recentTontinesSection.style.display = visibleRecent ? 'flex' : 'none';
    if (allTontinesSection) allTontinesSection.style.display = visibleAll ? 'block' : 'none';
    if (recentHeading) recentHeading.style.display = visibleRecent ? 'block' : 'none';
    if (allHeading) allHeading.style.display = visibleAll ? 'block' : 'none';
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
            window.location.href = 'logout.php';
        }
    });
}
</script>
</body>
</html>
