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
        $stmt = $pdo->query("SELECT product_id, product_name FROM Product ORDER BY product_name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($type === 'product_units') {
        // Dynamic relational query to only get valid sizes for a specific product
        $pid = $_GET['product_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT us.unit_size_id, us.size_description 
            FROM Unit_Size us
            JOIN Product_Unit_Price pup ON us.unit_size_id = pup.unit_size_id
            WHERE pup.product_id = ?
            ORDER BY us.size_description ASC
        ");
        $stmt->execute([$pid]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
