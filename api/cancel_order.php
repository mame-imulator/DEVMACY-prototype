<?php
// api/cancel_order.php
header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

// Ensure user is an Admin
if (($_SESSION['role_name'] ?? '') !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Only Admins can cancel orders.']);
    exit();
}

// Get JSON Input
$data = json_decode(file_get_contents('php://input'), true);
$sale_id = $data['sale_id'] ?? null;

if (!$sale_id) {
    echo json_encode(['success' => false, 'message' => 'No Sale ID provided.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Verify sale existence and current status
    $stmt = $pdo->prepare("SELECT status FROM Sale WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception("Sale order not found.");
    }

    if ($sale['status'] === 'Cancelled') {
        throw new Exception("This order is already cancelled.");
    }

    // 2. Fetch all items for return logging and stock restoration
    $stmt = $pdo->prepare("SELECT sale_item_id, units_sold, unit_price FROM Sale_Item WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Record each item in the Customer_Return table
    foreach ($sale_items as $si) {
        $stmt = $pdo->prepare("INSERT INTO Customer_Return (sale_item_id, quantity, refund_amount, reason) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $si['sale_item_id'],
            $si['units_sold'],
            ($si['units_sold'] * $si['unit_price']),
            "Order Cancellation (Order #$sale_id)"
        ]);
    }

    // 4. Fetch and restore stock for each batch used in this sale
    $stmt = $pdo->prepare("SELECT sib.stock_id, sib.quantity_taken 
                            FROM Sale_Item_Batch sib
                            JOIN Sale_Item si ON sib.sale_item_id = si.sale_item_id
                            WHERE si.sale_id = ?");
    $stmt->execute([$sale_id]);
    $batches_to_restore = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($batches_to_restore as $batch) {
        $stmt = $pdo->prepare("SELECT quantity FROM Stock WHERE stock_id = ?");
        $stmt->execute([$batch['stock_id']]);
        $curr_stock = $stmt->fetchColumn();
        
        $new_balance = $curr_stock + $batch['quantity_taken'];

        $stmt = $pdo->prepare("UPDATE Stock SET quantity = ? WHERE stock_id = ?");
        $stmt->execute([$new_balance, $batch['stock_id']]);

        $stmt = $pdo->prepare("INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason) 
                               VALUES (?, ?, 'Cancellation', ?, ?, ?)");
        $stmt->execute([
            $batch['stock_id'], 
            $_SESSION['user_id'], 
            $batch['quantity_taken'], 
            $new_balance, 
            "Cancellation of Order #$sale_id"
        ]);
    }

    // 5. Update Sale status to Cancelled
    $stmt = $pdo->prepare("UPDATE Sale SET status = 'Cancelled' WHERE sale_id = ?");
    $stmt->execute([$sale_id]);

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Order cancelled and stock restored successfully.'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
