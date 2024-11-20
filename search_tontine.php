<?php
require 'config.php';

if (isset($_GET['search_query'])) {
    $search_query = '%' . htmlspecialchars($_GET['search_query']) . '%';

    try {
        $stmt = $pdo->prepare("SELECT id, tontine_name FROM tontine WHERE tontine_name LIKE :query LIMIT 10");
        $stmt->bindParam(':query', $search_query, PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
