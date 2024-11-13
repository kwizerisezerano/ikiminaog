<?php
require 'config.php';
if (isset($_POST['upload'])) {
    // Check if a file is selected
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $fileName = $_FILES['file']['name'];
        $fileData = file_get_contents($_FILES['file']['tmp_name']);

      

            // Insert file into the database
            $stmt = $pdo->prepare("INSERT INTO pdf_files (file_name, file_data) VALUES (:file_name, :file_data)");
            $stmt->bindParam(':file_name', $fileName);
            $stmt->bindParam(':file_data', $fileData, PDO::PARAM_LOB);

            if ($stmt->execute()) {
                echo "PDF uploaded successfully!";
            } else {
                echo "Failed to upload PDF.";
            }
        } 
    } else {
        echo "No file selected or there was an error uploading the file.";
    }

?>
