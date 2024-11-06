<?php
// Start session for SweetAlert messaging
session_start();

// Database configuration
require 'config.php';
$loggedUserId = $_SESSION['user_id']; // Ensure 'user_id' is stored in session during login

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tontineName = $_POST['tontineName'];
    $joinDate = $_POST['join_date'];
    $province = $_POST['province'];
    $district = $_POST['district'];
    $sector = $_POST['sector'];
    $contributions = $_POST['contributions'];
    $occurrence = $_POST['occurrence'];
    $time = $_POST['time'] ?? null;
    $day = $_POST['day'] ?? null;
    $date = $_POST['date'] ?? null;
 // Validate Tontine Name: Only letters, spaces, and certain punctuation allowed
if (!preg_match('/^[a-zA-Z\s.,\'-]+$/', $tontineName)) {
    echo json_encode(['success' => false, 'message' => "Tontine name must contain only letters, spaces, and the following punctuation: ., '-."]);
    exit;
}

    // Check for duplicate Tontine name in the same sector
    $checkSql = "SELECT COUNT(*) FROM tontine WHERE tontine_name = :tontine_name AND sector = :sector";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':tontine_name', $tontineName);
    $checkStmt->bindParam(':sector', $sector);
    $checkStmt->execute();

    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => "A Tontine with this name already exists in the specified sector."]);
        exit;
    }

    // Handle file upload
    if (isset($_FILES['logo'])) {
        $logo = $_FILES['logo'];
        $fileName = $logo['name'];
        $fileSize = $logo['size'];
        $fileTmp = $logo['tmp_name'];
        $fileType = pathinfo($fileName, PATHINFO_EXTENSION);

        if ($logo['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => "Error during file upload: " . $logo['error']]);
            exit;
        }

        // Allowed file types
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array(strtolower($fileType), $allowedExtensions)) {
            echo json_encode(['success' => false, 'message' => "Invalid file type. Please upload a JPG, JPEG, PNG, or GIF image."]);
            exit;
        }

        if ($fileSize > 2097152) {
            echo json_encode(['success' => false, 'message' => "File size must not exceed 2 MB."]);
            exit;
        }

        // Define the upload path and generate a unique filename
        $logoPath = "uploads/" . uniqid() . '.' . strtolower($fileType);

        if (!move_uploaded_file($fileTmp, $logoPath)) {
            echo json_encode(['success' => false, 'message' => "Failed to upload logo."]);
            exit;
        }
    } else {
        $logoPath = null;
    }

    // Prepare SQL query with 'role' field
    $sql = "INSERT INTO tontine (tontine_name, logo, join_date, province, district, sector, total_contributions, occurrence, time, day, date, user_id, role) 
            VALUES (:tontine_name, :logo, :join_date, :province, :district, :sector, :total_contributions, :occurrence, :time, :day, :date, :user_id, :role)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':tontine_name', $tontineName);
    $stmt->bindParam(':join_date', $joinDate);
    $stmt->bindParam(':province', $province);
    $stmt->bindParam(':district', $district);
    $stmt->bindParam(':sector', $sector);
    $stmt->bindParam(':total_contributions', $contributions);
    $stmt->bindParam(':occurrence', $occurrence);
    $stmt->bindParam(':time', $time);
    $stmt->bindParam(':day', $day);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':logo', $logoPath);
    $stmt->bindParam(':user_id', $loggedUserId);

    // Bind 'role' with the value 'Admin'
    $role = 'Admin';
    $stmt->bindParam(':role', $role);

    if ($stmt->execute()) {
        $insertedId = $pdo->lastInsertId(); // Get the ID of the newly inserted row
        echo json_encode(['success' => true, 'message' => 'Tontine registered successfully!', 'redirectUrl' => 'tontine_profile.php?id=' . $insertedId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed! ' . implode(", ", $stmt->errorInfo())]);
    }
    exit;
}    
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IZU MIS - Tontine Registration</title>
    <!-- Include SweetAlert CSS and JS from CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.js"></script>

    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>

       

        .preview {
            display: none; /* Hidden by default */
            margin-top: 10px;
            width: 100px;
            height: 100px;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .close-preview {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #f00;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 14px;
            cursor: pointer;
        }

        input[type="file"] {
            margin-top: 5px;
            display: block;
            margin-left: 1px;
        }

        body {
            background-color: #f7f9fc;
            font-family: Arial, sans-serif;
        }

        .container {
        margin-top: 0px;
        padding-top: 0px;
        }

        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
         
        }

        .card-header {
            background-color: #00a3e0;
            color: white;
            font-weight: bold;
            font-size: 1rem;
            padding: 2PX 5px;
            text-align: center;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: -30px;
        }

        .input-group-text {
            background-color: #00a3e0;
            color: white;
            border: none;
            padding: 4px 8px;
        }

        .btn-primary {
            background-color: #00a3e0;
            border: none;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            padding: 1px;
            font-weight: bold;
        }

        .btn-primary:hover {
            background-color: #008bb8;
            box-shadow: 0 3px 8px rgba(0, 163, 224, 0.3);
        }

        .form-control, #file-input {
            border-radius: 6px;
            padding: 0px 10px;
            border-color: #ced4da;
            /* margin-top: -5px; */
        }

        .form-control:focus {
            border-color: #00a3e0;
            box-shadow: 0 0 4px rgba(0, 163, 224, 0.3);
        }
        .form-group {
    margin-bottom: 0px; /* Reduces space between form groups */
}

.form-row {
    margin-bottom: 10px; /* Reduces space between rows */
}
 .image-upload-container {
            text-align: center;
            margin-top: 20px;
        }

    </style>
</head>
<body>
<div class="container">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"style="float:left;">Tontine Identification</div>
                    <div class="card-body">
                    <form id="registrationForm" method="POST" autocomplete="off" enctype="multipart/form-data">

                            <div class="form-group">
                                <label for="tontineName">Tontine Name *</label>
                                <input type="text" class="form-control" id="tontineName" name="tontineName" required>
                            </div>
                            <div class="form-group" >
                                <label>Upload Tontine Logo</label>
                                <input type="file" id="file-input" name="logo" accept="image/*">
                                <div class="preview" id="image-preview">
                                    <button type="button" class="close-preview" onclick="removeImagePreview()">x</button>
                                    <img src="#" alt="Image Preview">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="join_date">Join Date *</label>
                                <input type="date" class="form-control" id="join_date" name="join_date" readonly>
                            </div>
                            <div class="form-group">
                                <label for="province">Province *</label>
                                <select id="province" name="province" class="form-control" required>
                                    <option value="">Select Province</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="district">District *</label>
                                <select id="district" name="district" class="form-control" required>
                                    <option value="">Select District</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="sector">Sector *</label>
                                <select id="sector" name="sector" class="form-control" required>
                                    <option value="">Select Sector</option>
                                </select>
                            </div>
                
                            <div class="form-group">
                                <label for="contributions">Contributions per place *</label>
                                <input type="number" class="form-control" id="contributions" name="contributions" required>
                            </div>
                            <div class="form-group">
    <label for="occurrence">Contribution Occurrence *</label>
    <select id="occurrence" name="occurrence" class="form-control" required>
        <option value="">Select Occurrence</option>
        <option value="Daily">Daily</option>
        <option value="Weekly">Weekly</option>
        <option value="Monthly">Monthly</option>
    </select>
</div>
<div class="form-group" id="timeInput" style="display: none;">
    <label for="time">Time (HH:MM) *</label>
    <input type="time" class="form-control" id="time" name="time">
</div>
<div class="form-group" id="dayInput" style="display: none;">
    <label for="day">Select Day *</label>
    <select id="day" name="day" class="form-control">
        <option value="">Select Day</option>
        <option value="Monday">Monday</option>
        <option value="Tuesday">Tuesday</option>
        <option value="Wednesday">Wednesday</option>
        <option value="Thursday">Thursday</option>
        <option value="Friday">Friday</option>
        <option value="Saturday">Saturday</option>
        <option value="Sunday">Sunday</option>
    </select>
</div>
<div class="form-group" id="dateInput" style="display: none;">
    <label for="date">Select Date *</label>
    <input type="date" class="form-control" id="date" name="date">
</div>

                            <button type="submit" class="btn btn-primary btn-block mt-2"><i class="fas fa-save"></i> Register</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card page-preview">
                    <div class="card-header">Tontine Page Preview</div>
                    <div class="card-body">
                        <div class="company-logo">
                            <img src="https://via.placeholder.com/100" alt="Tontine Logo" id="logoPreview" width="100" height="100" style="border-radius:5px;"> 
                        </div>
                        <h5 id="previewName">Tontine Name</h5>
                        <p id="previewSector">District-Sector: Kigali - Rwanda</p>
                        <p id="previewPurpose">Total Contribution: A community fund for social support.</p>
                        <a href="#" class="btn btn-primary" style="padding: 5px;">Visit Tontine Page</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
          <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="rwanda.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
document.getElementById('registrationForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent default form submission

    const formData = new FormData(this);

    fetch('', { // The current page is handling the form submission
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show SweetAlert success message
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 3000,
                showConfirmButton: true
            }).then(() => {
                // Redirect to tontine profile page after the alert
                window.location.href = data.redirectUrl;
            });
        } else {
            // Show SweetAlert error message
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message,
                showConfirmButton: true
            });
        }
    })
    .catch(error => console.error('Error:', error));
});

        document.getElementById("occurrence").addEventListener("change", function() {
    const occurrence = this.value;

    // Hide all additional input fields initially
    document.getElementById("timeInput").style.display = 'none';
    document.getElementById("dayInput").style.display = 'none';
    document.getElementById("dateInput").style.display = 'none';

    // Show the corresponding input field based on the selected occurrence
    if (occurrence === "Daily") {
        document.getElementById("timeInput").style.display = 'block';
    } else if (occurrence === "Weekly") {
        document.getElementById("dayInput").style.display = 'block';
    } else if (occurrence === "Monthly") {
        document.getElementById("dateInput").style.display = 'block';
    }
});

         document.addEventListener('DOMContentLoaded', function() {
            var today = new Date().toISOString().split('T')[0];
            document.getElementById('join_date').value = today;
            document.getElementById('join_date').readOnly = true;
        });






        const fileInput = document.getElementById('file-input');
        const imagePreview = document.getElementById('image-preview');

        // Set initial state of preview to hidden
        imagePreview.style.display = 'none';

        fileInput.addEventListener('change', function() {
            const file = fileInput.files[0];

            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // Set the image src to the selected file's data
                    imagePreview.innerHTML = `<button type="button" class="close-preview" onclick="removeImagePreview()">x</button><img src="${event.target.result}" alt="Image Preview">`;
                    imagePreview.style.display = 'flex'; // Show the preview
                    fileInput.style.display = 'none'; // Hide the file input
                };
                reader.readAsDataURL(file);
            }
        });

        function removeImagePreview() {
            imagePreview.style.display = 'none'; // Hide the preview
            fileInput.style.display = 'block';   // Show the file input again
            fileInput.value = '';                // Clear the file input value
        }


         const tontineNameInput = document.getElementById("tontineName");
        const districtSelect = document.getElementById("district");
        const sectorSelect = document.getElementById("sector");
        const contributionsInput = document.getElementById("contributions");

        tontineNameInput.addEventListener("input", function() {
            document.getElementById("previewName").textContent = tontineNameInput.value || "Tontine Name";
        });

        districtSelect.addEventListener("change", updatePreviewSector);
        sectorSelect.addEventListener("change", updatePreviewSector);

        function updatePreviewSector() {
            const district = districtSelect.options[districtSelect.selectedIndex].text;
            const sector = sectorSelect.options[sectorSelect.selectedIndex].text;
            document.getElementById("previewSector").textContent = `District-Sector: ${district} - ${sector}`;
        }

        contributionsInput.addEventListener("input", function() {
            const amount = contributionsInput.value;
            document.getElementById("previewPurpose").textContent = amount ? 
                `Purpose: Total contributions of ${amount} RWF for community support.` : 
                "Purpose: A community fund for social support.";
        });

        document.getElementById("file-input").addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById("logoPreview").src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
// Show SweetAlert message if session message is set
        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                text: "<?php echo $_SESSION['message']; ?>",
                icon: "<?php echo strpos($_SESSION['message'], 'success') !== false ? 'success' : 'error'; ?>"
            });
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
    </script>
</body>
</html>
