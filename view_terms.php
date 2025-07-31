<?php
// view_terms.php
require 'config.php'; // Database connection

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

        // Close button and confirmation dialog
        echo "<div style='text-align: center; padding: 10px;'>
                <button onclick='closeDocument()' style='padding: 10px 20px; font-size: 16px; background-color:#0f73adff; border:none; border-radius: 5px; color:white;'>Close Document</button>
              </div>";

        echo "<script>
            function closeDocument() {
                Swal.fire({
                    title: 'Close Document',
                    text: 'Are you sure you want to close this document?',
                    icon: 'warning',
                    iconColor: '#0f73adff',
                    showCancelButton: true,
                    confirmButtonColor: '#0f73adff',
                    cancelButtonColor: '#0f73adff',
                    confirmButtonText: 'Yes, close it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'tontine_profile.php?id=$tontine_id';
                    }
                });
            }
        </script>";

        echo "</body></html>";
    } else {
        // File not found
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            Swal.fire({
                title: 'Error!',
                text: 'Terms and conditions not found for this tontine ID.',
                icon: 'error',
                iconColor: '#0f73adff',
                confirmButtonColor: '#0f73adff'
            }).then(() => {
                window.location.href = 'tontine_profile.php';
            });
        </script>";
    }
} else {
    // Invalid or missing ID
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        Swal.fire({
            title: 'Error!',
            text: 'Invalid tontine ID provided.',
            icon: 'error',
            iconColor: '#0f73adff',
            confirmButtonColor: '#0f73adff'
        }).then(() => {
            window.location.href = 'tontine_profile.php';
        });
    </script>";
}
?>
