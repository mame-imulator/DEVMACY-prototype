<?php
// api/get_recommendations.php
header('Content-Type: application/json');
require_once '../includes/db.php';

$symptom_ids = $_GET['symptoms'] ?? '';
if (empty($symptom_ids)) {
    echo json_encode([]);
    exit();
}

$id_array = explode(',', $symptom_ids);
$id_count = count($id_array);
$placeholders = str_repeat('?,', $id_count - 1) . '?';

try {
    // Find products matching ALL selected symptoms
    // Join with Stock to see live availability
    // Join with Pricing to see price
    $query = "
        SELECT 
            p.product_id, 
            p.product_name,
            s.size_description,
            pup.price_per_unit,
            pup.barcode,
            SUM(st.quantity) as total_stock,
            MIN(st.expiry_date) as earliest_expiry
        FROM Product p
        JOIN Unit_Size s ON 1=1 -- We want all available sizes for this drug
        JOIN Product_Unit_Price pup ON p.product_id = pup.product_id AND s.unit_size_id = pup.unit_size_id
        LEFT JOIN Stock st ON p.product_id = st.product_id AND s.unit_size_id = st.unit_size_id
        WHERE p.product_id IN (
            SELECT ps.product_id
            FROM Product_Symptom ps
            WHERE ps.symptom_id IN ($placeholders)
            GROUP BY ps.product_id
            HAVING COUNT(DISTINCT ps.symptom_id) = ?
        )
        GROUP BY p.product_id, s.unit_size_id
        ORDER BY total_stock DESC, p.product_name ASC
    ";

    $params = array_merge($id_array, [$id_count]);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
