<?php
// api/search_products.php
header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$q = $_GET['q'] ?? '';

if (strlen($q) < 1) {
    echo json_encode([]);
    exit();
}

try {
    // Search by barcode, product name, OR product_id
    $stmt = $pdo->prepare("SELECT pup.barcode, p.product_name, us.size_description, pup.price_per_unit 
                            FROM Product_Unit_Price pup
                            JOIN Product p ON pup.product_id = p.product_id
                            JOIN Unit_Size us ON pup.unit_size_id = us.unit_size_id
                            WHERE pup.barcode LIKE ? 
                               OR p.product_name LIKE ? 
                               OR pup.product_id = ?
                            LIMIT 10");
    $stmt->execute(["%$q%", "%$q%", $q]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
