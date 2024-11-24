<?php
// upload_terms.php
require 'config.php'; // Database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['terms_file'])) {
    $tontine_id = $_POST['tontine_id'];
    $fileName = $_FILES['terms_file']['name'];
    
    // Check if the file upload was successful
    if (is_uploaded_file($_FILES['terms_file']['tmp_name'])) {
        $fileData = file_get_contents($_FILES['terms_file']['tmp_name']);
        
        // Check if a record with the given tontine_id already exists
        $checkQuery = "SELECT COUNT(*) FROM pdf_files WHERE tontine_id = :tontine_id";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $recordExists = $checkStmt->fetchColumn() > 0;

        if ($recordExists) {
            // Update the existing record
            $query = "UPDATE pdf_files SET file_name = :file_name, file_data = :file_data WHERE tontine_id = :tontine_id";
        } else {
            // Insert a new record if none exists
            $query = "INSERT INTO pdf_files (file_name, file_data, tontine_id) VALUES (:file_name, :file_data, :tontine_id)";
        }

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':file_name', $fileName);
        $stmt->bindParam(':file_data', $fileData, PDO::PARAM_LOB);
        $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Success message using SweetAlert
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Terms and conditions uploaded successfully!',
                        icon: 'success'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'view_terms.php?id=$tontine_id';
                        }
                    });
                });
            </script>";
        } else {
            // Error message using SweetAlert
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'There was an error uploading the terms and conditions.',
                        icon: 'error'
                    });
                });
            </script>";
        }
    } else {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'No file was uploaded. Please try again.',
                    icon: 'error'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'tontine_profile.php?id=$tontine_id';
                    }
                });
            });
        </script>";
        
    }
    exit(); // Ensures no further processing occurs after the response
}
?>
