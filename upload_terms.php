<?php
// upload_terms.php
require 'config.php'; // Database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['terms_file'])) {
    $tontine_id = $_POST['tontine_id'];
    $fileName = $_FILES['terms_file']['name'];

    if (is_uploaded_file($_FILES['terms_file']['tmp_name'])) {
        $fileData = file_get_contents($_FILES['terms_file']['tmp_name']);

        // Check if a record with the given tontine_id already exists
        $checkQuery = "SELECT COUNT(*) FROM pdf_files WHERE tontine_id = :tontine_id";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $recordExists = $checkStmt->fetchColumn() > 0;

        if ($recordExists) {
            // Update existing record
            $query = "UPDATE pdf_files SET file_name = :file_name, file_data = :file_data WHERE tontine_id = :tontine_id";
        } else {
            // Insert new record
            $query = "INSERT INTO pdf_files (file_name, file_data, tontine_id) VALUES (:file_name, :file_data, :tontine_id)";
        }

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':file_name', $fileName);
        $stmt->bindParam(':file_data', $fileData, PDO::PARAM_LOB);
        $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);

        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";

        if ($stmt->execute()) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Terms and conditions uploaded successfully!',
                        icon: 'success',
                        iconColor: '#0f73adff',
                        showConfirmButton: false,
                        timer: 500,
                        timerProgressBar: true
                    });
                    setTimeout(function() {
                        window.location.href = 'view_terms.php?id=$tontine_id';
                    }, 2000);
                });
            </script>";
        } else {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'There was an error uploading the terms and conditions.',
                        icon: 'error',
                        iconColor: '#0f73adff',
                        showConfirmButton: false,
                        timer: 500,
                        timerProgressBar: true
                    });
                    setTimeout(function() {
                        window.location.href = 'tontine_profile.php?id=$tontine_id';
                    }, 2000);
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
                    icon: 'error',
                    iconColor: '#0f73adff',
                    showConfirmButton: false,
                    timer: 500,
                    timerProgressBar: true
                });
                setTimeout(function() {
                    window.location.href = 'tontine_profile.php?id=$tontine_id';
                }, 500);
            });
        </script>";
    }
    exit(); // Stop script execution after sending response
}
?>
