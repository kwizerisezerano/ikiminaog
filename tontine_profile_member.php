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

    // Display the name of the creator
    // echo "<p class='mb-1'><strong>Created by:</strong> " . $creator_name . "</p>";
} else {
    // If no creator found for the given tontine ID
    // echo "<p class='mb-1'><strong>Created by:</strong> Unknown</p>";
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>

.left-section a{
    text-decoration: none;
}


* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

/* Body styling */
body {
  font-family: Arial, sans-serif;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
  margin: 0;
  background-color: #f0f0f0;
}

        .container {
            display: flex;
            max-width: 1200px;
            width: 100%;
            gap: 20px;
            /* padding: 1px 20px; */
            /* margin-top: -20px; */
        }
        .left-section {
  flex: 1; /* Takes up available width */
  padding: 20px;
  background-color: #f9f9f9;
  overflow: auto; /* Allows scrolling within if content overflows */
  height: auto; /* Adjust height based on content */
  min-height: 0; /* Ensures the section does not grow larger than its container */
  border-radius: 5px;

}
.right-section {
    flex: 1; /* Takes up available width */
  padding: 20px;
  background-color: #f9f9f9;
  /* Adjust height based on content */
  min-height: 0; /* Ensures the section does not grow larger than its container */

}


        .cover-photo {
            width: 100%;
            height: 150px;
            background-image: url('BACKROUNDS/T3.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }
        .tontine-info {
            position: relative;
            padding-top: 40px;
            margin-left: 110px;
        }
        .tontine-logo {
            width: 100px;
            height: 100px;
            background-color: #002f6c;
            border-radius: 10px;
            position: absolute;
            top: -30px;
            margin-left: -80px;
            z-index: 1;
            background-image: url('<?php echo $logoFilePath; ?>');
            background-size: cover;
            background-position: center;
        }
        .tontine-details {
            padding-left: 50px;
            margin-top: -30px;
        }
        .tontine-details h2 {
            font-size: 20px;
            font-weight: 600;
        }
         .button-container{
          display: flex;
          margin: 5px 80px;
          gap: 10px; 
         }
        .button-container .btn-action {
    width: 120px; /* Set desired width */
    height: 50px; /* Set desired height */
    display: flex;
    align-items: center;
    justify-content: center;
}

.button-container .btn-action a,
.button-container .btn-action i,
.button-container .btn-action p {
    margin: 0;
    padding: 0;
    text-decoration: none;
}


.button-container .btn i {
  font-size: 16px; /* Adjust icon size */
}

      
       
        .custom-button:hover {
            opacity: 0.9;
            text-decoration: none;
        }
      
        .right-section {
            flex: 1;
            background-color: #fff;
            border-radius: 10px;
            padding: 5px 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            /* margin: 10px; */
        }
        .section-title {
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .section-title i {
            color: #007bff;
        }
        .info-section p {
            margin: 5px 0;
        }
      
        .edit-icon {
           margin-left: 10px; /* Adjust the gap between icon and content */ /* position: absolute; */
            
            cursor: pointer;
            position: absolute;
        }
         .edit-field,.edit-field1{
           
            border: none;
            /* display: flex; */
    /* align-items: center; */
    outline: none;
            
         }
         .right-section h6{
            font-weight: bold;
         }
         .right-section p{
            font-size: 1rem;
         }

         .timer-box {
            border: 1px solid skyblue;
            border-radius: 5px;
            padding: 5px 10px;
            text-align: center;
            width: 80px;
            margin: 5px;
            margin-top: -10px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .timer-box span {
            display: block;
            font-size: 20px;
        }
        .timer-label {
            font-size: 14px;
            color: #007bff;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Left Section -->
    <div class="left-section">
        <!-- Cover Photo and Tontine Info -->
        <div class="cover-photo"></div>
        <div class="tontine-info">
            <div class="tontine-logo"></div>
            <div class="tontine-details">
                <h2><?php echo htmlspecialchars($tontine['tontine_name']); ?></h2>
                <p><strong>Province: </strong><?php echo htmlspecialchars($tontine['province']); ?> ,<strong> District:</strong> <?php echo htmlspecialchars($tontine['district']); ?> ,<strong> Sector: </strong><?php echo htmlspecialchars($tontine['sector']); ?></p>
                <p><strong>Total Contributions:</strong> <?php echo number_format($tontine['total_contributions']); ?> Rwf</p>
                    <p><strong>Occurence:</strong> <?php echo htmlspecialchars($tontine['occurrence']); ?> </p>
                <?php echo $occurrenceDisplay; ?>
                  <p><strong>Time:</strong> <?php echo htmlspecialchars($tontine['time']); ?> </p>
                   <p><strong>Status:</strong> <?php echo htmlspecialchars($tontine['status']); ?> </p>
                  
            </div>
        </div>
        <div class="container d-flex justify-content-center align-items-center mt-1">
       <!-- Countdown Timer HTML -->
<div class="d-flex">
    <div class="timer-box">
        <span id="days">00</span>
        <div class="timer-label">Days</div>
    </div>
    <div class="timer-box">
        <span id="hours">00</span>
        <div class="timer-label">Hours</div>
    </div>
    <div class="timer-box">
        <span id="minutes">00</span>
        <div class="timer-label">Mins</div>
    </div>
    <div class="timer-box">
        <span id="seconds">00</span>
        <div class="timer-label">Seconds</div>
    </div>
</div>

    </div>

        <!-- Buttons for Actions -->
        <div class="button-container d-flex justify-content-start">
    <button type="button" class="btn btn-outline-info btn-verification btn-action">
        <a href="user_profile.php" class="text-primary">Home</a>
    </button>
    
<!-- Join Button -->
<button type="button" class="btn btn-outline-info btn-action btn-join" onclick="confirmJoinTontine()">
    <i class="fas fa-user-plus text-primary"></i> <p class="text-primary">Join Now</p>
</button>

<!-- Join Button -->
<button type="button" class="btn btn-outline-info btn-action btn-join" onclick="confirmContribute()">
    <i class="fas fa-user-plus text-primary"></i> <p class="text-primary">Contribute</p>
</button>
<!-- Join Button -->
<button type="button" class="btn btn-outline-info btn-action btn-join" onclick="confirmLoan()">
    <i class="fas fa-user-plus text-primary"></i> <p class="text-primary">Apply Loan</p>
</button>


</button>
<!-- <button type="button" class="btn btn-outline-info btn-verification btn-action ">
        <a href="user_profile.php" class="text-blue"><i class="fas fa-bell "></i></a>
    </button> -->

</div>


    </div>

    <!-- Right Section -->
<div class="right-section p-3">
    <!-- Contact Section -->
    <div class="info-section border-bottom mb-1 pb-1">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="section-title text-info">Contact Information</h6>
           
        </div>
        <p class="mb-1"><strong>Created by:</strong> <?php echo htmlspecialchars($creator_name); ?></p>
        <p><strong>Contact:</strong> <?php echo htmlspecialchars($creator_contact); ?></p>
    </div>

    <!-- Contributions, Loans, Penalties Section -->
    <div class="info-section border-bottom mb-1 pb-1">
        <div class="section-item mb-1">
            <h6 class="text-info">Contributions</h6>
            <p><strong> Contribution per place:</strong> <?php echo number_format($tontine['total_contributions']); ?> Rwf</p>
        </div>

        <div class="section-item mb-1">
            <h6 class="text-info">Loans</h6>
            <p><strong>Interest Rate:</strong> <?php echo intval($tontine['interest']).'%'; ?></p>
            <p><strong>Payment Frequency:</strong> 
    <?php 
        // Display payment frequency
        echo $tontine['payment_frequency'];

        // Check if payment frequency is weekly or monthly and display additional details
        if ($tontine['payment_frequency'] === 'Weekly') {
            // For weekly payments, display the frequent_payment_day if set
            if (!empty($tontine['frequent_payment_day'])) {
                echo ' [Day: ' . $tontine['frequent_payment_day'] . ']';
            } else {
                echo ' [No specific day set for weekly payments]';
            }
        } elseif ($tontine['payment_frequency'] === 'Monthly') {
            // For monthly payments, display the frequent_payment_date if set
            if (!empty($tontine['frequent_payment_date'])) {
                echo ' [Date: ' . date('F j, Y', strtotime($tontine['frequent_payment_date'])) . ']';
            } else {
                echo ' [No specific date set for monthly payments]';
            }
        } else {
            // If payment frequency is neither weekly nor monthly, show a default message
            echo ' [Invalid payment frequency]';
        }
    ?>
</p>


          
        </div>

        <div class="section-item mb-1 pb-1">
            <h6 class="text-info">Penalties</h6>
            <p><strong> Contribution Late Amount:</strong> <?php echo $tontine['late_contribution_penalty']; ?> RWF per 1 place</p>

            <p><strong> Late Loan Repayment Amount:</strong> <?php echo $tontine['late_loan_repayment_amount']; ?> RWF per 1 place</p>
        </div
    </div>
<!-- Purpose & Rules Section -->
<div class="about-section border-bottom mb-1 pb-1">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <h6 class="section-title text-info">Purpose</h6>
    </div>

    <?php
    // Check if the purpose is empty, then set a default value
    $purpose = isset($purpose) && !empty($purpose) ? $purpose : 'Describe your purpose';
    ?>
    <input type="text" class="edit-field mb-1 space-between" id="purpose-field" value="<?php echo htmlspecialchars($purpose); ?>">

  

    <h6 class="section-title text-info">Rules</h6>

    <?php
    // Check if the rules are empty, then set a default value
    $rules = isset($rules) && !empty($rules) ? $rules : 'Describe your rules';
    ?>
    <input type="text" class="edit-field mb-1 space-between" id="rules-field" value="<?php echo htmlspecialchars($rules); ?>">

 
</div>
 <button type="button" class="btn btn-info btn-sm rounded">
        <a class="text-white"style="text-decoration:none;" href="view_terms_member.php?id=<?php echo $id; ?>">Read Terms and Conditions</a>
    </button>
    <button type="button" class="btn btn-info btn-sm rounded">
        <a class="text-white"style="text-decoration:none;" href="contribution_success.php?id=<?php echo $id; ?>">Contributions History</a>
    </button>
     <button type="button" class="btn btn-info btn-sm rounded mt-1">
        <a class="text-white "style="text-decoration:none;" href="contribution_dates_admin.php?id=<?php echo $id; ?>">Contribution dates  </a>
    </button>
      <button type="button" class="btn btn-info btn-sm rounded mt-1">
        <a class="text-white "style="text-decoration:none;" href="missed_contribution.php?id=<?php echo $id; ?>">Missed contributions</a>
    </button>
     <button type="button" class="btn btn-info btn-sm rounded mt-1">
        <a class="text-white "style="text-decoration:none;" href="penalties_contribution.php?id=<?php echo $id; ?>">Penalties for contributions</a>
    </button>
      <button type="button" class="btn btn-info btn-sm rounded mt-1">
        <a class="text-white "style="text-decoration:none;" href="missed_penalties.php?id=<?php echo $id; ?>">Missed Penalties</a>
    </button>
    <button type="button" class="btn btn-info btn-sm rounded mt-1">
        <a class="text-white "style="text-decoration:none;" href="loan_success.php?id=<?php echo $id; ?>">view you loans</a>
    </button>
     <button type="button" class="btn btn-info btn-sm rounded mt-1">
        <a class="text-white "style="text-decoration:none;" href="paid_loan_list.php?id=<?php echo $id; ?>">paid loans</a>
    </button>
    <button type="button" class="btn btn-info btn-sm rounded mt-1">
        <a class="text-white "style="text-decoration:none;" href="report_member.php?id=<?php echo $id; ?>">Member Report</a>
    </button>
     <button type="button" class="btn btn-info btn-sm rounded mt-1">
        <a class="text-white "style="text-decoration:none;" href="contribution_dates_admin.php?id=<?php echo $id; ?>">Contribution dates  </a>
    </button>
<!-- Include SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


</div>




   

<script>
 document.addEventListener('DOMContentLoaded', function() {
            // Retrieve PHP variables and pass them into the JavaScript object
            const tontine = {
                occurrence: "<?php echo $tontine['occurrence']; ?>", // Dynamic occurrence (daily, weekly, monthly)
                time: "<?php echo $time; ?>",
                day: "<?php echo $day; ?>", // For weekly occurrences
                date: "<?php echo $day_of_month; ?>" // For monthly occurrences
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
                        targetDate.setDate(targetDate.getDate() + 1); // If today has passed, set to tomorrow
                    }
                }

                if (occurrence === "Weekly") {
                    const weekdays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
                    let targetDay = weekdays.indexOf(day);
                    targetDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), ...time.split(":"));

                    // Adjust to the correct day of the week
                    if (now.getDay() <= targetDay) {
                        targetDate.setDate(now.getDate() + (targetDay - now.getDay())); // Next occurrence
                    } else {
                        targetDate.setDate(now.getDate() + (7 - (now.getDay() - targetDay))); // Next week's occurrence
                    }
                }

                if (occurrence === "Monthly") {
                    targetDate = new Date(now.getFullYear(), now.getMonth(), date, ...time.split(":"));
                    if (now > targetDate) {
                        targetDate.setMonth(targetDate.getMonth() + 1); // Set for the next month if the date has passed
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

                    // Display the countdown in the respective HTML elements
                    document.getElementById("days").innerText = days.toString().padStart(2, '0');
                    document.getElementById("hours").innerText = hours.toString().padStart(2, '0');
                    document.getElementById("minutes").innerText = minutes.toString().padStart(2, '0');
                    document.getElementById("seconds").innerText = seconds.toString().padStart(2, '0');
                } else {
                    clearInterval(interval);
                    alert("Tontine has started!");
                    document.getElementById("days").innerText = "00";
                    document.getElementById("hours").innerText = "00";
                    document.getElementById("minutes").innerText = "00";
                    document.getElementById("seconds").innerText = "00";
                }
            }

            // Start the countdown immediately and set an interval to update every second
            updateCountdown();
            const interval = setInterval(updateCountdown, 1000);
        }

      function confirmLoan() {
    // Open your modal or form for editing
    Swal.fire({
        title: 'Are you sure?',
        text: "You want to Apply loan in   this  tontine",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, I want to Apply!',
        cancelButtonText: 'No, ',
    }).then((result) => {
        if (result.isConfirmed) {
            // Perform the deletion
            window.location.href = 'loan.php?id=' + <?php echo $id; ?>;
        }
    });
   
}





        function confirmContribute() {
    // Open your modal or form for editing
    Swal.fire({
        title: 'Are you sure?',
        text: "You want to Contribute to  this  tontine",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, I want to Contribute!',
        cancelButtonText: 'No, ',
    }).then((result) => {
        if (result.isConfirmed) {
            // Perform the deletion
            window.location.href = 'contribution.php?id=' + <?php echo $id; ?>;
        }
    });
   
}
// Function to show the update modal
function confirmJoinTontine() {
    // Open your modal or form for editing
    Swal.fire({
        title: 'Are you sure?',
        text: "You want to join this  tontine",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, I want to Join it!',
        cancelButtonText: 'No, ',
    }).then((result) => {
        if (result.isConfirmed) {
            // Perform the deletion
            window.location.href = 'join_tontine.php?id=' + <?php echo $id; ?>;
        }
    });
   
}
// Function to trigger SweetAlert popup for editing either Purpose or Rules
function editField(field) {
    let fieldValue = document.getElementById(field + '-field').value;

    // Open SweetAlert popup
    Swal.fire({
        title: 'Edit ' + field.charAt(0).toUpperCase() + field.slice(1),
        input: 'text',
        inputValue: fieldValue,
        showCancelButton: true,
        confirmButtonText: 'Save',
        cancelButtonText: 'Cancel',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to write something!';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            let newValue = result.value;

            // Send the updated value to the server to save it
            updateFieldInDatabase(field, newValue);
        }
    });
}

// Function to send AJAX request to update field in the database
function updateFieldInDatabase(field, newValue) {
    // Assuming you have the tontine ID available in JavaScript (e.g., from a global variable or URL)
    let tontineId = <?php echo $id; ?>;  // Dynamically retrieve the Tontine ID from PHP

    let data = new FormData();
    data.append('field', field);
    data.append('value', newValue);
    data.append('id', tontineId); // Append the tontine ID to the data

    // Use the fetch API to send the request to the server
    fetch('update_field.php', {
        method: 'POST',
        body: data
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the value in the input field
            document.getElementById(field + '-field').value = newValue;
            Swal.fire('Success', 'Your ' + field + ' has been updated!', 'success');
        } else {
            Swal.fire('Error', 'Failed to update the ' + field, 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'An error occurred while updating', 'error');
    });
}
</script>
</body>
</html>