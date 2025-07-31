<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Validate ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Invalid Request',
        'message' => 'No tontine ID was provided for deletion.'
    ];
    header("Location: own_tontine.php");
    exit();
}

$tontine_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Check if the tontine exists and belongs to the user
    $check_stmt = $pdo->prepare("SELECT id, tontine_name, user_id FROM tontine WHERE id = :id");
    $check_stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $check_stmt->execute();
    $tontine = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tontine) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Tontine Not Found',
            'message' => 'The requested tontine does not exist or has already been deleted.'
        ];
        $pdo->rollBack();
        header("Location: own_tontine.php");
        exit();
    }

    if ($tontine['user_id'] != $user_id) {
        $_SESSION['alert'] = [
            'type' => 'error',
            'title' => 'Access Denied',
            'message' => 'You do not have permission to delete this tontine.'
        ];
        $pdo->rollBack();
        header("Location: own_tontine.php");
        exit();
    }

    // Check related activity
    $activity_check = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM tontine_join_requests WHERE tontine_id = :id AND status = 'Approved') as active_members,
            (SELECT COUNT(*) FROM contributions WHERE tontine_id = :id) as total_contributions,
            (SELECT COUNT(*) FROM loan_requests WHERE tontine_id = :id AND status IN ('Approved', 'Disbursed')) as active_loans
    ");
    $activity_check->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $activity_check->execute();
    $activity = $activity_check->fetch(PDO::FETCH_ASSOC);

    if ($activity['active_members'] > 0 || $activity['total_contributions'] > 0 || $activity['active_loans'] > 0) {
        $_SESSION['alert'] = [
            'type' => 'warning',
            'title' => 'Cannot Delete Tontine',
            'message' => 'This tontine has active members, contributions, or outstanding loans. Please resolve them before deletion.'
        ];
        $pdo->rollBack();
        header("Location: own_tontine.php");
        exit();
    }

    // Delete related data
    $delete_requests = $pdo->prepare("DELETE FROM tontine_join_requests WHERE tontine_id = :id");
    $delete_requests->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $delete_requests->execute();

    $delete_pdfs = $pdo->prepare("DELETE FROM pdf_files WHERE tontine_id = :id");
    $delete_pdfs->bindParam(':id', $tontine_id, PDO::PARAM_INT);
    $delete_pdfs->execute();

    // Delete tontine
    $delete_stmt = $pdo->prepare("DELETE FROM tontine WHERE id = :id");
    $delete_stmt->bindParam(':id', $tontine_id, PDO::PARAM_INT);

    if ($delete_stmt->execute()) {
        $pdo->commit();
        $_SESSION['alert'] = [
            'type' => 'success',
            'title' => 'Tontine Deleted',
            'message' => 'The tontine "' . htmlspecialchars($tontine['tontine_name']) . '" has been successfully deleted.'
        ];
        header("Location: own_tontine.php");
        exit();
    } else {
        throw new Exception("Failed to delete tontine.");
    }

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Tontine deletion error: " . $e->getMessage());

    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Deletion Failed',
        'message' => 'An error occurred while deleting the tontine. Please try again later.'
    ];
    header("Location: own_tontine.php");
    exit();
}
?>
