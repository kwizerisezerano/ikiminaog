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
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Sanitize user data
$user_name = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
$user_contact = htmlspecialchars($user['phone_number']); // User contact fetched from database
$total_notifications = 5;

// Get ID dynamically from query string or default to 1
$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Prepare and execute query to check if the sector exists
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tontine WHERE id = :id AND sector IS NOT NULL AND sector != ''");
$stmt->execute(['id' => $id]);
$sectorExists = $stmt->fetchColumn();

// If the sector does not exist, display SweetAlert message and exit script
if (!$sectorExists) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Sector Not Found',
                text: 'The specified sector does not exist for this tontine.',
                confirmButtonText: 'Go Back'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.history.back(); // Go back to the previous page
                }
            });
        });
    </script>";
    exit();
}

// Fetch tontine details including the logo
$stmt = $pdo->prepare("SELECT tontine_name, logo, join_date, province, district, sector, total_contributions, occurrence, time, day, date, user_id,rules,purpose,status,interest, payment_frequency ,frequent_payment_date,frequent_payment_day,late_contribution_penalty,late_loan_repayment_amount  FROM tontine WHERE id = :id");
$stmt->execute(['id' => $id]);
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

$rules = $tontine['rules']; // Tontine rules fetched from database 
$purpose=$tontine['purpose']; // Tontine purpose fetched from database from                         

// Get the values from the database
$time = $tontine['time'];
$day = $tontine['day'];
$date = $tontine['date'];
// Extract only the day part from the date (in case it's stored as 'YYYY-MM-DD')
$day_of_month = date("d", strtotime($date));  // This will extract just the day (e.g., '30')

// Check if tontine details were found
if (!$tontine) {
    die("Tontine details not found.");
}

// Fetch contact of the user who created the tontine (Admin role)
$creator_id = $tontine['user_id'];
$stmt = $pdo->prepare("SELECT phone_number FROM users WHERE id = :id");
$stmt->bindParam(':id', $creator_id);
$stmt->execute();
$creator = $stmt->fetch(PDO::FETCH_ASSOC);
$creator_contact = htmlspecialchars($creator['phone_number']);

// Build the path for the logo image
$logoFilePath = htmlspecialchars($tontine['logo']);
if (empty($tontine['logo']) || !file_exists($logoFilePath)) {
    $logoFilePath = 'uploads/default_logo.png';
}

// Determine what to display based on the occurrence type
$occurrenceDisplay = '';
switch (strtolower($tontine['occurrence'])) {
    case 'daily':
        // Only display time for daily occurrence
        // $occurrenceDisplay = '<p><strong>Time:</strong> ' . htmlspecialchars($tontine['time']) . '</p>';
        break;
    case 'weekly':
        $occurrenceDisplay = '<p><strong>Day:</strong> ' . htmlspecialchars($tontine['day']) . '</p>';
        break;
    case 'monthly':
        $occurrenceDisplay = '<p><strong>Date:</strong> ' . htmlspecialchars($tontine['join_date']) . '</p>';
        break;
    default:
        $occurrenceDisplay = '<p><strong>Occurrence:</strong> ' . htmlspecialchars($tontine['occurrence']) . '</p>';
        break;
}

// Calculate the target date and time for the countdown timer 
// Get the tontine ID dynamically from the query string or default to 1
$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Prepare and execute the query to fetch the creator's name using a JOIN
$stmt = $pdo->prepare("
    SELECT users.firstname, users.lastname,users.phone_number
    FROM tontine
    JOIN users ON tontine.user_id = users.id
    WHERE tontine.id = :id
");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

// Fetch the creator's details
$creator = $stmt->fetch(PDO::FETCH_ASSOC);
$creator_contact=$creator['phone_number'];

// Check if the creator data is found
if ($creator) {
    $creator_name = htmlspecialchars($creator['firstname'] . ' ' . $creator['lastname']); // Name of the creator
} else {
    // If no creator found for the given tontine ID
    $creator_name = "Unknown";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tontine Member Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0f73adff;
            --primary-dark: #0a5b8a;
            --primary-light: #e6f3ff;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --white: #ffffff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--light-bg) 0%, #e2e8f0 100%);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .dashboard-container {
            min-height: 100vh;
            padding: 1rem 1rem;
        }

        .main-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        /* Left Section - Main Content */
        .main-content {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .hero-section {
            position: relative;
            height: 200px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            padding: 2rem;
            color: white;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: flex-end;
            gap: 1.5rem;
            height: 100%;
        }

        .tontine-logo {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: var(--white);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            background-image: url('<?php echo $logoFilePath; ?>');
            background-size: cover;
            background-position: center;
            border: 3px solid var(--white);
        }

        .tontine-info h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .tontine-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .tontine-stats {
            display: flex;
            gap: 2rem;
            font-size: 0.9rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Status Badge */
        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Countdown Timer */
        .countdown-section {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }

        .countdown-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .timer-box {
            background: var(--primary-light);
            border: 2px solid var(--primary-color);
            border-radius: 12px;
            padding: 1rem;
            min-width: 70px;
            text-align: center;
        }

        .timer-box .number {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            line-height: 1;
        }

        .timer-box .label {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Details Section */
        .details-section {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1rem;
        }

        .detail-group {
            background: var(--light-bg);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .detail-group h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Editable Fields */
        .editable-field {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .editable-field:last-child {
            border-bottom: none;
        }

        .edit-input {
            flex: 1;
            border: none;
            outline: none;
            background: transparent;
            font-weight: 500;
            padding: 0.25rem 0;
        }

        .edit-btn {
            color: var(--primary-color);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .edit-btn:hover {
            background: var(--primary-light);
        }

        /* Action Buttons */
        .actions-section {
            padding: 1rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.5rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.1rem;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            background: var(--white);
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.3s ease;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
        }

        .action-btn:hover {
            border-color: var(--primary-color);
            background: var(--primary-light);
            color: var(--primary-color);
            text-decoration: none;
            transform: translateY(-2px);
        }

        .action-btn.primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .action-btn.primary:hover {
            background: var(--primary-dark);
            color: white;
        }

        .action-btn i {
            font-size: 1rem;
            color: var(--primary-color);
        }

        .action-btn.primary i {
            color: white;
        }

        /* Right Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            width: 400px;
        }

        .info-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            width: 100%;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Management Tools */
        .management-section {
            padding: 1.5rem;
        }

        .management-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .mgmt-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-dark);
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
        }

        .mgmt-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary-color);
            color: var(--primary-color);
            text-decoration: none;
        }

        .mgmt-btn i {
            width: 16px;
            text-align: center;
            flex-shrink: 0;
            color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-wrapper {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .sidebar {
                order: -1;
                width: 100%;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem 0.5rem;
            }
            
            .hero-section {
                height: 160px;
                padding: 1.5rem;
            }
            
            .tontine-info h1 {
                font-size: 1.5rem;
            }
            
            .tontine-logo {
                width: 60px;
                height: 60px;
            }
            
            .countdown-timer {
                gap: 0.5rem;
            }
            
            .timer-box {
                min-width: 60px;
                padding: 0.75rem 0.5rem;
            }
            
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="main-wrapper">
            <!-- Main Content -->
            <div class="main-content">
                <!-- Hero Section -->
                <div class="hero-section">
                    <div class="status-badge">
                        <i class="fas fa-circle-info"></i>
                        <?php echo htmlspecialchars($tontine['status']); ?>
                    </div>
                    
                    <div class="hero-content">
                        <div class="tontine-logo"></div>
                        <div class="tontine-info">
                            <h1><?php echo htmlspecialchars($tontine['tontine_name']); ?></h1>
                            <div class="tontine-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($tontine['province']); ?> / <?php echo htmlspecialchars($tontine['district']); ?> / <?php echo htmlspecialchars($tontine['sector']); ?></span>
                            </div>
                            <div class="tontine-stats">
                                <div class="stat-item">
                                    <i class="fas fa-coins"></i>
                                    <span><?php echo number_format($tontine['total_contributions']); ?> RWF</span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?php echo htmlspecialchars($tontine['occurrence']); ?></span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo htmlspecialchars($tontine['time']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Countdown Section -->
                <div class="countdown-section">
                    <div class="countdown-title">Next Contribution In</div>
                    <div class="countdown-timer">
                        <div class="timer-box">
                            <span class="number" id="days">00</span>
                            <div class="label">Days</div>
                        </div>
                        <div class="timer-box">
                            <span class="number" id="hours">00</span>
                            <div class="label">Hours</div>
                        </div>
                        <div class="timer-box">
                            <span class="number" id="minutes">00</span>
                            <div class="label">Minutes</div>
                        </div>
                        <div class="timer-box">
                            <span class="number" id="seconds">00</span>
                            <div class="label">Seconds</div>
                        </div>
                    </div>
                </div>

                <!-- Details Section -->
                <div class="details-section">
                    <div class="details-grid">
                        <!-- Contact Information -->
                        <div class="detail-group">
                            <h3>
                                <i class="fas fa-user"></i>
                                Contact Information
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Created by</span>
                                <span class="detail-value"><?php echo htmlspecialchars($creator_name); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Contact</span>
                                <span class="detail-value"><?php echo htmlspecialchars($creator_contact); ?></span>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <div class="detail-group">
                            <h3>
                                <i class="fas fa-chart-line"></i>
                                Financial Details
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Contribution per place</span>
                                <span class="detail-value"><?php echo number_format($tontine['total_contributions']); ?> RWF</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Loan Interest Rate</span>
                                <span class="detail-value"><?php echo intval($tontine['interest']).'%'; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Payment Frequency</span>
                                <span class="detail-value">
                                    <?php 
                                        echo $tontine['payment_frequency'];
                                        if ($tontine['payment_frequency'] === 'Weekly' && !empty($tontine['frequent_payment_day'])) {
                                            echo ' [Day: ' . $tontine['frequent_payment_day'] . ']';
                                        } elseif ($tontine['payment_frequency'] === 'Monthly' && !empty($tontine['frequent_payment_date'])) {
                                            echo ' [Date: ' . date('F j, Y', strtotime($tontine['frequent_payment_date'])) . ']';
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- Penalties -->
                        <div class="detail-group">
                            <h3>
                                <i class="fas fa-exclamation-triangle"></i>
                                Penalties
                            </h3>
                            <div class="detail-row">
                                <span class="detail-label">Contribution Late Amount</span>
                                <span class="detail-value"><?php echo $tontine['late_contribution_penalty']; ?> RWF per 1 place</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Late Loan Repayment Amount</span>
                                <span class="detail-value"><?php echo $tontine['late_loan_repayment_amount']; ?> RWF per 1 place</span>
                            </div>
                        </div>

                        <!-- Purpose & Rules -->
                        <div class="detail-group">
                            <h3>
                                <i class="fas fa-info-circle"></i>
                                Purpose & Rules
                            </h3>
                            <div class="editable-field">
                                <strong>Purpose:</strong>
                                <input type="text" class="edit-input" id="purpose-field" value="<?php echo htmlspecialchars(isset($purpose) && !empty($purpose) ? $purpose : 'Describe your purpose'); ?>">
                                <i class="fas fa-pencil-alt edit-btn" onclick="editField('purpose')"></i>
                            </div>
                            <div class="editable-field">
                                <strong>Rules:</strong>
                                <input type="text" class="edit-input" id="rules-field" value="<?php echo htmlspecialchars(isset($rules) && !empty($rules) ? $rules : 'Describe your rules'); ?>">
                                <i class="fas fa-pencil-alt edit-btn" onclick="editField('rules')"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions Section -->
                <div class="actions-section">
                    <div class="actions-grid">
                        <a href="user_profile.php" class="action-btn">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                        </a>
                        
                        <button type="button" class="action-btn primary" onclick="confirmJoinTontine()">
                            <i class="fas fa-user-plus"></i>
                            <span>Join Now</span>
                        </button>
                        
                        <button type="button" class="action-btn" onclick="confirmContribute()">
                            <i class="fas fa-hand-holding-dollar"></i>
                            <span>Contribute</span>
                        </button>
                        
                        <button type="button" class="action-btn" onclick="confirmLoan()">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Apply Loan</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Member Management Tools -->
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-user-cog"></i>
                        Member Tools
                    </div>
                    <div class="management-section">
                        <div class="management-grid">
                            <a href="view_terms_member.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-file-text"></i>
                                Read Terms & Conditions
                            </a>
                            
                            <a href="contribution_success.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-history"></i>
                                Contributions History
                            </a>
                            
                            <a href="contribution_dates_admin.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-calendar-check"></i>
                                Contribution Dates
                            </a>
                            
                            <a href="missed_contribution.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-times-circle"></i>
                                Missed Contributions
                            </a>
                            <a href="paid_missed_contribution.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-times-circle"></i>
                               Paid Missed Contributions
                            </a>
                            
                            <!-- <a href="penalties_contribution.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-exclamation-triangle"></i>
                                Penalties for Contributions
                            </a> -->
                            
                            <a href="missed_penalties.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-warning"></i>
                                Missed Penalties
                            </a>
                              <a href="paid_missed_penalties.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-times-circle"></i>
                                Paid Missed Penalties
                            </a>
                            
                            
                            
                            <a href="loan_success.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-money-bill-wave"></i>
                                View Your Loans
                            </a>
                            
                            <a href="paid_loan_list.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-money-check-alt"></i>
                                Paid Loans
                            </a>
                            
                            <a href="report_member.php?id=<?php echo $id; ?>" class="mgmt-btn">
                                <i class="fas fa-chart-bar"></i>
                                Member Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function confirmLoan() {
            Swal.fire({
                title: 'Apply for Loan',
                text: "Do you want to apply for a loan in this tontine?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Apply!',
                cancelButtonText: 'Cancel',
                confirmButtonColor: 'var(--primary-color)',
                cancelButtonColor: 'var(--secondary-color)'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'loan.php?id=' + <?php echo $id; ?>;
                }
            });
        }

        function confirmContribute() {
            Swal.fire({
                title: 'Make Contribution',
                text: "Do you want to contribute to this tontine?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Contribute!',
                cancelButtonText: 'Cancel',
                confirmButtonColor: 'var(--primary-color)',
                cancelButtonColor: 'var(--secondary-color)'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'contribution.php?id=' + <?php echo $id; ?>;
                }
            });
        }

        function confirmJoinTontine() {
            Swal.fire({
                title: 'Join Tontine',
                text: "Do you want to join this tontine?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Join!',
                cancelButtonText: 'Cancel',
                confirmButtonColor: 'var(--primary-color)',
                cancelButtonColor: 'var(--secondary-color)'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'join_tontine.php?id=' + <?php echo $id; ?>;
                }
            });
        }

        function editField(field) {
            let fieldValue = document.getElementById(field + '-field').value;

            Swal.fire({
                title: 'Edit ' + field.charAt(0).toUpperCase() + field.slice(1),
                input: 'text',
                inputValue: fieldValue,
                showCancelButton: true,
                confirmButtonText: 'Save',
                cancelButtonText: 'Cancel',
                confirmButtonColor: 'var(--primary-color)',
                cancelButtonColor: 'var(--secondary-color)',
                inputValidator: (value) => {
                    if (!value) {
                        return 'You need to write something!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    let newValue = result.value;
                    updateFieldInDatabase(field, newValue);
                }
            });
        }

        function updateFieldInDatabase(field, newValue) {
            let tontineId = <?php echo $id; ?>;

            let data = new FormData();
            data.append('field', field);
            data.append('value', newValue);
            data.append('id', tontineId);

            fetch('update_field.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(field + '-field').value = newValue;
                    Swal.fire({
                        title: 'Success!',
                        text: 'Your ' + field + ' has been updated!',
                        icon: 'success',
                        confirmButtonColor: 'var(--primary-color)'
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to update the ' + field,
                        icon: 'error',
                        confirmButtonColor: 'var(--primary-color)'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while updating',
                    icon: 'error',
                    confirmButtonColor: 'var(--primary-color)'
                });
            });
        }

        // Countdown Timer
        document.addEventListener('DOMContentLoaded', function() {
            const tontine = {
                occurrence: "<?php echo $tontine['occurrence']; ?>",
                time: "<?php echo $time; ?>",
                day: "<?php echo $day; ?>",
                date: "<?php echo $day_of_month; ?>"
            };

            startCountdown(tontine);
        });

        function startCountdown(tontine) {
            const { occurrence, time, day, date } = tontine;

            function getNextOccurrence() {
                const now = new Date();
                let targetDate;

                if (occurrence === "Daily") {
                    targetDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), ...time.split(":"));
                    if (now > targetDate) {
                        targetDate.setDate(targetDate.getDate() + 1);
                    }
                }

                if (occurrence === "Weekly") {
                    const weekdays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
                    let targetDay = weekdays.indexOf(day);
                    targetDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), ...time.split(":"));

                    if (now.getDay() <= targetDay) {
                        targetDate.setDate(now.getDate() + (targetDay - now.getDay()));
                    } else {
                        targetDate.setDate(now.getDate() + (7 - (now.getDay() - targetDay)));
                    }
                }

                if (occurrence === "Monthly") {
                    targetDate = new Date(now.getFullYear(), now.getMonth(), date, ...time.split(":"));
                    if (now > targetDate) {
                        targetDate.setMonth(targetDate.getMonth() + 1);
                    }
                }

                return targetDate;
            }

            function updateCountdown() {
                const targetDate = getNextOccurrence();
                const now = new Date();
                const distance = targetDate - now;

                if (distance > 0) {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    document.getElementById("days").innerText = days.toString().padStart(2, '0');
                    document.getElementById("hours").innerText = hours.toString().padStart(2, '0');
                    document.getElementById("minutes").innerText = minutes.toString().padStart(2, '0');
                    document.getElementById("seconds").innerText = seconds.toString().padStart(2, '0');
                } else {
                    clearInterval(interval);
                    Swal.fire({
                        title: 'Time\'s Up!',
                        text: 'Tontine contribution time has arrived!',
                        icon: 'info',
                        confirmButtonColor: 'var(--primary-color)'
                    });
                    document.getElementById("days").innerText = "00";
                    document.getElementById("hours").innerText = "00";
                    document.getElementById("minutes").innerText = "00";
                    document.getElementById("seconds").innerText = "00";
                }
            }

            updateCountdown();
            const interval = setInterval(updateCountdown, 1000);
        }
    </script>
</body>
</html>