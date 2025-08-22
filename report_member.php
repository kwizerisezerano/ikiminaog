<?php
session_start();
require 'config.php';

// Check if user is logged in - redirect to login if not
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Additional security check for session validation
if (empty($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Fetch user ID from session
$user_id = $_SESSION['user_id'];

// Fetch user details with error handling
$stmt = $pdo->prepare("SELECT firstname, lastname, phone_number, image, idno, behalf_name, behalf_phone_number, idno_picture, otp_behalf_used FROM users WHERE id = :id ");
$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    // User not found or inactive - logout and redirect
    session_destroy();
    header("Location: login.php?error=invalid_user");
    exit();
}

$user_name = htmlspecialchars(trim($user['firstname'] . ' ' . $user['lastname']));
$user_phone = htmlspecialchars($user['phone_number']);
$user_image = !empty($user['image']) ? htmlspecialchars($user['image']) : 'default-avatar.png';

// Get tontine ID from the URL
$tontine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tontine_id <= 0) {
    die("Invalid tontine ID provided.");
}

// Fetch tontine details
$stmt = $pdo->prepare("SELECT * FROM tontine WHERE id = :id");
$stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if tontine exists
if (!$tontine) {
    die("Tontine not found.");
}

// Check if user has permission to view this tontine (either owner or member)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tontine WHERE id = :tontine_id AND user_id = :user_id
    UNION ALL
    SELECT COUNT(*) FROM tontine_join_requests WHERE tontine_id = :tontine_id AND user_id = :user_id AND status = 'Permitted'
");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_COLUMN);
$has_permission = (array_sum($results) > 0);

if (!$has_permission) {
    // Log unauthorized access attempt
    error_log("Unauthorized tontine access attempt - User ID: $user_id, Tontine ID: $tontine_id, IP: " . $_SERVER['REMOTE_ADDR']);
    
    // Redirect with error message instead of dying
    header("Location: dashboard.php?error=access_denied");
    exit();
}



// Total contributions (sum of approved contributions)
$stmt = $pdo->prepare("SELECT SUM(amount) AS total_contributions FROM contributions WHERE tontine_id = :tontine_id AND payment_status='Approved' AND user_id =:user_id");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$total_contributions = $stmt->fetchColumn() ?: 0;


// Total loan requests (approved)
$stmt = $pdo->prepare("SELECT SUM(loan_amount) AS total_loan_requests FROM loan_requests WHERE tontine_id = :tontine_id AND status = 'Approved' AND user_id =:user_id");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$total_loan_requests = $stmt->fetchColumn() ?: 0;

// Total loan payments (approved)
$stmt = $pdo->prepare("SELECT SUM(amount) AS total_loan_payments FROM loan_payments WHERE tontine_id = :tontine_id AND payment_status = 'Approved' AND user_id =:user_id");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$total_loan_payments = $stmt->fetchColumn() ?: 0;

// Get missed contributions statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'Unpaid' THEN 1 END) as unpaid_count,
        COALESCE(SUM(CASE WHEN status = 'Unpaid' THEN missed_amount ELSE 0 END), 0) as unpaid_amount,
        COUNT(CASE WHEN status = 'Paid' THEN 1 END) as paid_count,
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN missed_amount ELSE 0 END), 0) as paid_amount
    FROM missed_contributions 
    WHERE tontine_id = :tontine_id AND user_id =:user_id
");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$missed_contributions_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$unpaid_missed_contributions_count = $missed_contributions_stats['unpaid_count'];
$unpaid_missed_contributions_amount = $missed_contributions_stats['unpaid_amount'];
$paid_missed_contributions_count = $missed_contributions_stats['paid_count'];
$paid_missed_contributions_amount = $missed_contributions_stats['paid_amount'];

// Legacy variables for backward compatibility
$unpaid_missed_contributions = $unpaid_missed_contributions_count;
$paid_missed_contributions = $paid_missed_contributions_count;

// Get penalties statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'Unpaid' THEN 1 END) as unpaid_count,
        COALESCE(SUM(CASE WHEN status = 'Unpaid' THEN penalty_amount ELSE 0 END), 0) as unpaid_amount,
        COUNT(CASE WHEN status = 'Paid' THEN 1 END) as paid_count,
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN penalty_amount ELSE 0 END), 0) as paid_amount
    FROM penalties 
    WHERE tontine_id = :tontine_id AND user_id =:user_id
");
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$penalties_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$unpaid_penalties_count = $penalties_stats['unpaid_count'];
$unpaid_penalties_amount = $penalties_stats['unpaid_amount'];
$paid_penalties_count = $penalties_stats['paid_count'];
$paid_penalties_amount = $penalties_stats['paid_amount'];




?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tontine Dashboard - <?php echo htmlspecialchars($tontine['tontine_name']); ?></title>
      <!-- Font Awesome (only one version needed) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Bootstrap 4 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.5.2/css/bootstrap.min.css">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 4 Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<style> 
:root { 
    --primary-color: #2563eb; 
    --secondary-color: #1d4ed8; 
    --success-color: #10b981; 
    --warning-color: #f59e0b; 
    --danger-color: #ef4444; 
    --info-color: #3b82f6; 
    --dark-color: #1f2937; 
    --light-color: #f8fafc; 
    --gradient-primary: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); 
    --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%); 
    --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); 
    --gradient-danger: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); 
    --gradient-info: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); 
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.1); 
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1); 
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1); 
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.15); 
} 

* { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
} 

body { 
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
    line-height: 1.6; 
    color: var(--dark-color); 
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); 
    min-height: 100vh; 
} 

/* Navbar Styles */ 
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

/* Dashboard Container */ 
.dashboard-container { 
    padding: 2rem 0; 
    min-height: calc(100vh - 80px); 
} 

.dashboard-header { 
    background: white; 
    border-radius: 20px; 
    padding: 2rem; 
    margin-bottom: 2rem; 
    box-shadow: var(--shadow-lg); 
    position: relative; 
    overflow: hidden; 
} 

.dashboard-header::before { 
    content: ''; 
    position: absolute; 
    top: 0; 
    left: 0; 
    right: 0; 
    height: 4px; 
    background: var(--gradient-primary); 
} 

/* Profile Link Styling */
.profile-link {
    position: absolute;
    top: 2rem;
    right: 2rem;
    background: var(--gradient-primary);
    color: white;
    text-decoration: none;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-md);
    z-index: 10;
}

.profile-link:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    color: white;
    text-decoration: none;
}

.profile-link i {
    margin-right: 0.5rem;
}

.dashboard-title { 
    font-size: 2.5rem; 
    font-weight: 700; 
    background: var(--gradient-primary); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    background-clip: text; 
    margin-bottom: 0.5rem; 
    padding-right: 150px; /* Make space for the profile link */
} 

.dashboard-subtitle { 
    color: #6b7280; 
    font-size: 1.1rem; 
    font-weight: 500; 
} 

/* Stats Cards Grid - Updated to show 3 cards per row */
.stats-grid { 
    display: grid; 
    grid-template-columns: repeat(3, 1fr); 
    gap: 1.5rem; 
    margin-bottom: 2rem; 
} 

.stat-card { 
    background: white; 
    border-radius: 16px; 
    padding: 1.5rem; 
    box-shadow: var(--shadow-md); 
    transition: all 0.3s ease; 
    position: relative; 
    overflow: hidden; 
    border: 1px solid rgba(255, 255, 255, 0.1); 
    height: 100%; /* Ensure equal height */
} 

.stat-card::before { 
    content: ''; 
    position: absolute; 
    top: 0; 
    left: 0; 
    right: 0; 
    height: 3px; 
    background: var(--gradient-primary); 
} 

.stat-card:hover { 
    transform: translateY(-5px); 
    box-shadow: var(--shadow-xl); 
} 

.stat-card.success::before { 
    background: var(--gradient-success); 
} 

.stat-card.warning::before { 
    background: var(--gradient-warning); 
} 

.stat-card.danger::before { 
    background: var(--gradient-danger); 
} 

.stat-card.info::before { 
    background: var(--gradient-info); 
} 

.stat-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    margin-bottom: 1rem; 
} 

.stat-icon { 
    width: 50px; 
    height: 50px; 
    border-radius: 12px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-size: 1.25rem; 
    color: white; 
} 

.stat-icon.primary { 
    background: var(--gradient-primary); 
} 

.stat-icon.success { 
    background: var(--gradient-success); 
} 

.stat-icon.warning { 
    background: var(--gradient-warning); 
} 

.stat-icon.danger { 
    background: var(--gradient-danger); 
} 

.stat-icon.info { 
    background: var(--gradient-info); 
} 

.stat-title { 
    font-size: 0.875rem; 
    font-weight: 600; 
    color: #6b7280; 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
} 

.stat-value { 
    font-size: 2rem; 
    font-weight: 700; 
    color: var(--dark-color); 
    margin-bottom: 0.5rem; 
} 

.stat-description { 
    font-size: 0.875rem; 
    color: #9ca3af; 
} 

/* New styles for dual-metric cards */ 
.dual-metric { 
    display: flex; 
    justify-content: space-between; 
    align-items: flex-end; 
    gap: 1rem; 
    margin-top: 0.5rem; 
} 

.metric { 
    text-align: center; 
} 

.metric-label { 
    font-size: 0.75rem; 
    color: #9ca3af; 
    margin-bottom: 0.25rem; 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
} 

.metric-value { 
    font-size: 1.2rem; 
    font-weight: 700; 
    color: var(--dark-color); 
} 

.metric-value.count { 
    color: var(--primary-color); 
} 

.metric-value.amount { 
    color: var(--success-color); 
} 

/* Responsive Design */ 
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) { 
    .dashboard-title { 
        font-size: 2rem;
        padding-right: 0; /* Remove padding on mobile */
    }

    .profile-link, .logout-btn {
        position: static;
        display: inline-block;
        margin: 0.5rem 0.5rem 1rem 0;
    }

    .user-info {
        position: static;
        margin-bottom: 1rem;
        flex-direction: row;
    }
    
    .stats-grid { 
        grid-template-columns: 1fr; 
        gap: 1rem; 
    } 
    
    .stat-card { 
        padding: 1rem; 
    } 
    
    .dashboard-header { 
        padding: 1.5rem; 
    } 
    
    .dual-metric { 
        flex-direction: row; 
        justify-content: space-around; 
    } 
} 

/* Animation for loading */ 
@keyframes fadeInUp { 
    from { 
        opacity: 0; 
        transform: translateY(30px); 
    } 
    to { 
        opacity: 1; 
        transform: translateY(0); 
    } 
} 

.fade-in-up { 
    animation: fadeInUp 0.6s ease-out; 
} 

/* Custom scrollbar */ 
::-webkit-scrollbar { 
    width: 8px; 
} 

::-webkit-scrollbar-track { 
    background: #f1f1f1; 
    border-radius: 10px; 
} 

::-webkit-scrollbar-thumb { 
    background: var(--gradient-primary); 
    border-radius: 10px; 
} 

::-webkit-scrollbar-thumb:hover { 
    background: var(--gradient-secondary); 
} 

/* User info display */
.user-info {
    position: absolute;
    top: 2rem;
    left: 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    color: #6b7280;
    font-size: 0.9rem;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--primary-color);
}

.user-details i {
    margin-right: 0.5rem;
    color: var(--primary-color);
}

.user-name {
    font-weight: 600;
    color: var(--dark-color);
}

/* Logout button styling */
.logout-btn {
    position: absolute;
    top: 2rem;
    right: 200px; /* Position it to the left of profile link */
    background: var(--gradient-danger);
    color: white;
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 500;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-sm);
}

.logout-btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
    color: white;
    text-decoration: none;
}
</style>
</head>
<body>

<div class="container dashboard-container">
    
    <!-- Dashboard Header -->
    <div class="dashboard-header fade-in-up">
       
        <a href="tontine_profile_member.php?id=<?php echo $tontine_id; ?>" class="profile-link">
            <i class="fas fa-user-circle"></i>Tontine Profile
        </a>
        <h1 class="dashboard-title">
            <i class="fas fa-chart-line me-3"></i>Tontine Dashboard
        </h1>
        <p class="dashboard-subtitle">
            Detailed financial overview for <strong><?php echo htmlspecialchars($tontine['tontine_name']); ?></strong>
        </p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
       
        <!-- Total Contributions -->
        <div class="stat-card success fade-in-up">
            <div class="stat-header">
                <div class="stat-icon success">
                    <i class="fas fa-piggy-bank"></i>
                </div>
            </div>
            <div class="stat-title">Total Contributions</div>
            <div class="stat-value">RWF <?php echo number_format($total_contributions, 0); ?></div>
            <div class="stat-description">All approved contributions received</div>
        </div>

        
        <!-- Loan Requests -->
        <div class="stat-card info fade-in-up">
            <div class="stat-header">
                <div class="stat-icon info">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
            </div>
            <div class="stat-title">Loan Requests</div>
            <div class="stat-value">RWF <?php echo number_format($total_loan_requests, 0); ?></div>
            <div class="stat-description">Total approved loans</div>
        </div>

        <!-- Loan Payments -->
        <div class="stat-card primary fade-in-up">
            <div class="stat-header">
                <div class="stat-icon primary">
                    <i class="fas fa-money-check-alt"></i>
                </div>
            </div>
            <div class="stat-title">Loan Payments</div>
            <div class="stat-value">RWF <?php echo number_format($total_loan_payments, 0); ?></div>
            <div class="stat-description">Total loan repayments</div>
        </div>

        <!-- Missed Contributions -->
        <div class="stat-card danger fade-in-up">
            <div class="stat-header">
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="stat-title">Missed Contributions</div>
            <div class="dual-metric">
                <div class="metric">
                    <div class="metric-label">Count</div>
                    <div class="metric-value count"><?php echo $unpaid_missed_contributions_count; ?> / <?php echo $paid_missed_contributions_count; ?></div>
                </div>
                <div class="metric">
                    <div class="metric-label">Amount</div>
                    <div class="metric-value amount">RWF <?php echo number_format($unpaid_missed_contributions_amount + $paid_missed_contributions_amount, 0); ?></div>
                </div>
            </div>
            <div class="stat-description">Unpaid / Paid missed contributions</div>
        </div>

        <!-- Penalties -->
        <div class="stat-card warning fade-in-up">
            <div class="stat-header">
                <div class="stat-icon warning">
                    <i class="fas fa-gavel"></i>
                </div>
            </div>
            <div class="stat-title">Penalties</div>
            <div class="dual-metric">
                <div class="metric">
                    <div class="metric-label">Count</div>
                    <div class="metric-value count"><?php echo $unpaid_penalties_count; ?> / <?php echo $paid_penalties_count; ?></div>
                </div>
                <div class="metric">
                    <div class="metric-label">Amount</div>
                    <div class="metric-value amount">RWF <?php echo number_format($unpaid_penalties_amount + $paid_penalties_amount, 0); ?></div>
                </div>
            </div>
            <div class="stat-description">Unpaid / Paid penalties</div>
        </div>

       

        <!-- Outstanding Loans -->
        <?php
        $outstanding_loans = $total_loan_requests - $total_loan_payments;
        ?>
        <div class="stat-card info fade-in-up">
            <div class="stat-header">
                <div class="stat-icon info">
                    <i class="fas fa-balance-scale"></i>
                </div>
            </div>
            <div class="stat-title">Outstanding Loans</div>
            <div class="stat-value">RWF <?php echo number_format($outstanding_loans, 0); ?></div>
            <div class="stat-description">Loans yet to be repaid</div>
        </div>
    </div>
</div>

<script>
function confirmLogout() {
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out of your account!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, log out!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
}

// Add smooth loading animation
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.fade-in-up');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Add counter animation for numbers
function animateNumbers() {
    const numbers = document.querySelectorAll('.stat-value, .metric-value');
    numbers.forEach(number => {
        const text = number.textContent;
        // Skip if it contains slashes (count ratios)
        if (text.includes('/')) return;
        
        const finalValue = text.replace(/[^\d.-]/g, '');
        if (finalValue && !isNaN(finalValue)) {
            let current = 0;
            const increment = finalValue / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    current = finalValue;
                    clearInterval(timer);
                }
                if (text.includes('RWF')) {
                    number.textContent = 'RWF ' + Math.floor(current).toLocaleString();
                } else {
                    number.textContent = Math.floor(current);
                }
            }, 30);
        }
    });
}

// Start number animation when page loads
window.addEventListener('load', animateNumbers);
</script>

</body>
</html>