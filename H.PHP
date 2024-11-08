<?php
// config.php includes database connection setup
include('config.php');

// Get the tontine ID dynamically, e.g., from URL parameters
$tontine_id = isset($_GET['id']) ? $_GET['id'] : 49;

// Fetch the tontine details from the database
$sql = "SELECT tontine_name, occurrence, time, day, date FROM tontine WHERE id = :tontine_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
$stmt->execute();
$tontine = $stmt->fetch(PDO::FETCH_ASSOC);

// Get the values from the database
$time = $tontine['time'];
$day = $tontine['day'];
$date = $tontine['date'];

// Extract only the day part from the date (in case it's stored as 'YYYY-MM-DD')
$day_of_month = date("d", strtotime($date));  // This will extract just the day (e.g., '30')
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Countdown Timer for Tontine</title>
    <style>
        .timer-box {
            border: 2px solid skyblue;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
            width: 80px;
            margin: 5px;
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
    </script>
</head>
<body>
    <h1 style="text-align: center;">Tontine Countdown</h1>

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

</body>
</html>
