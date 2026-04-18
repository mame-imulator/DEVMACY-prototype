<?php
// api/process_sale.php
header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Get JSON Input
$data = json_decode(file_get_contents('php://input'), true);
$cart = $data['cart'] ?? [];

if (empty($cart)) {
    echo json_encode(['success' => false, 'message' => 'The cart is empty.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Create Sale Entry
    $stmt = $pdo->prepare("INSERT INTO Sale (sale_date) VALUES (CURDATE())");
    $stmt->execute();
    $sale_id = $pdo->lastInsertId();

    foreach ($cart as $item) {
        $barcode = $item['barcode'];
        $qty_to_sell = (int)$item['quantity'];

        // 2. Fetch Product & Unit Details from Barcode
        $stmt = $pdo->prepare("SELECT product_id, unit_size_id, price_per_unit FROM Product_Unit_Price WHERE barcode = ?");
        $stmt->execute([$barcode]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$details) {
            throw new Exception("Product with barcode $barcode not found.");
        }

        $product_id = $details['product_id'];
        $unit_size_id = $details['unit_size_id'];
        $unit_price = $details['price_per_unit'];

        // 3. FIFO Stock Deduction (Closest to Expiry First)
        // Fetch all stock batches for this product/unit, ordered by expiry date
        $stmt = $pdo->prepare("SELECT stock_id, quantity FROM Stock 
                               WHERE product_id = ? AND unit_size_id = ? AND quantity > 0 
                               ORDER BY expiry_date ASC");
        $stmt->execute([$product_id, $unit_size_id]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_available = array_sum(array_column($batches, 'quantity'));
        if ($total_available < $qty_to_sell) {
            throw new Exception("Insufficient stock for product barcode: $barcode");
        }

        // 4. Create Sale_Item Entry
        $stmt = $pdo->prepare("INSERT INTO Sale_Item (sale_id, product_id, unit_size_id, units_sold, unit_price) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sale_id, $product_id, $unit_size_id, $qty_to_sell, $unit_price]);
        $sale_item_id = $pdo->lastInsertId();

        // 5. Deduct from Batches & Log Usage
        $remaining_to_deduct = $qty_to_sell;
        foreach ($batches as $batch) {
            if ($remaining_to_deduct <= 0) break;

            $deduct = min($batch['quantity'], $remaining_to_deduct);
            
            // Deduct from Stock
            $stmt = $pdo->prepare("UPDATE Stock SET quantity = quantity - ? WHERE stock_id = ?");
            $stmt->execute([$deduct, $batch['stock_id']]);

            // Track which batch was used for this Sale Item
            $stmt = $pdo->prepare("INSERT INTO Sale_Item_Batch (sale_item_id, stock_id, quantity_taken) VALUES (?, ?, ?)");
            $stmt->execute([$sale_item_id, $batch['stock_id'], $deduct]);

            // Record Movement on the Stock Card
            $balance_after = $batch['quantity'] - $deduct;
            $stmt = $pdo->prepare("INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason) 
                                   VALUES (?, ?, 'Sale', ?, ?, ?)");
            $stmt->execute([
                $batch['stock_id'], 
                $_SESSION['user_id'], 
                -$deduct, 
                $balance_after, 
                "POS Sale Order #$sale_id"
            ]);

            $remaining_to_deduct -= $deduct;
        }
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Sale processed successfully!',
        'sale_id' => $sale_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
