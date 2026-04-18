<?php
// api/get_metadata.php
header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$type = $_GET['type'] ?? '';

try {
    if ($type === 'unit_size') {
        $stmt = $pdo->query("SELECT unit_size_id, size_description FROM Unit_Size ORDER BY size_description ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($type === 'product_list') {
        // Simple list for generic dropdowns
        $stmt = $pdo->query("SELECT product_id, product_name FROM Product ORDER BY product_name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
