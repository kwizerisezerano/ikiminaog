<?php
// view_terms.php
require 'config.php'; // Database connection

// Retrieve tontine_id from the query parameter
$tontine_id = $_GET['id'] ?? null;

if ($tontine_id) {
    // Fetch file details from the database
    $query = "SELECT file_name, file_data FROM pdf_files WHERE tontine_id = :tontine_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':tontine_id', $tontine_id, PDO::PARAM_INT);
    $stmt->execute();
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        // Display PDF in an iframe with a Close button
        echo "<html><head><title>View Terms and Conditions</title>";
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "</head><body style='margin: 0;'>";

        echo "<iframe src='data:application/pdf;base64," . base64_encode($file['file_data']) . "' 
                style='width: 100%; height: 90vh; border: none;' 
                title='Terms and Conditions'></iframe>";
        
        // Add a close button
        echo "<div style='text-align: center; padding: 10px;'>
                <button onclick='closeDocument()' style='padding: 10px 20px; font-size: 16px;background-color:skyblue;border:none;border-radius: 5px;'>Close Document</button>
              </div>";
        
        // Close function with redirection to tontine_profile.php
        echo "<script>
                function closeDocument() {
                    Swal.fire({
                        title: 'Close Document',
                        text: 'Are you sure you want to close this document?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, close it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'tontine_profile_sector.php?id=$tontine_id';
                        }
                    });
                }
              </script>";

        echo "</body></html>";
    } else {
        // Error message if file not found
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            Swal.fire({
                title: 'Error!',
                text: 'Terms and conditions not found for this tontine ID.',
                icon: 'error'
            }).then(() => {
                window.location.href = 'tontine_profile.php';
            });
        </script>";
    }
} else {
    // Error message if invalid tontine ID
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        Swal.fire({
            title: 'Error!',
            text: 'Invalid tontine ID provided.',
            icon: 'error'
        }).then(() => {
            window.location.href = 'tontine_profile.php?id=$tontine_id';
        });
    </script>";
}
?>
