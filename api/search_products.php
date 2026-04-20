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
    // Now with Promotion support
    $stmt = $pdo->prepare("SELECT pup.barcode, p.product_name, us.size_description, pup.price_per_unit,
                                  pr.discount_type, pr.discount_value, pr.promo_name
                            FROM Product_Unit_Price pup
                            JOIN Product p ON pup.product_id = p.product_id
                            JOIN Unit_Size us ON pup.unit_size_id = us.unit_size_id
                            LEFT JOIN Promotion pr ON pup.barcode = pr.barcode 
                                 AND pr.is_active = 1 
                                 AND CURDATE() BETWEEN pr.start_date AND pr.end_date
                            WHERE pup.barcode LIKE ? 
                               OR p.product_name LIKE ? 
                               OR pup.product_id = ?
                            ORDER BY pr.discount_value DESC
                            LIMIT 10");
    $stmt->execute(["%$q%", "%$q%", $q]);
    $raw_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by barcode to handle multiple promos (picking the best one via ORDER BY)
    $results = [];
    $seen_barcodes = [];

    foreach ($raw_results as $row) {
        if (in_array($row['barcode'], $seen_barcodes)) continue;
        $seen_barcodes[] = $row['barcode'];

        $original_price = (float)$row['price_per_unit'];
        $promo_price = $original_price;
        $has_promo = false;

        if ($row['promo_name']) {
            $has_promo = true;
            if ($row['discount_type'] === 'Percentage') {
                $promo_price = $original_price * (1 - ($row['discount_value'] / 100));
            } else {
                $promo_price = max(0, $original_price - $row['discount_value']);
            }
        }

        $results[] = [
            'barcode' => $row['barcode'],
            'product_name' => $row['product_name'],
            'size_description' => $row['size_description'],
            'original_price' => $original_price,
            'price_per_unit' => round($promo_price, 2),
            'has_promo' => $has_promo,
            'promo_name' => $row['promo_name']
        ];
    }

    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
