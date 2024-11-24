<?php
require'config.php';
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

   
       

        // Retrieve PDF file data
        $stmt = $pdo->prepare("SELECT file_name, file_data FROM pdf_files WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $pdf = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pdf) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $pdf['file_name'] . '"');
            echo $pdf['file_data'];
        } else {
            echo "PDF not found.";
        }
     
} else {
    echo "No PDF specified.";
}
?>
