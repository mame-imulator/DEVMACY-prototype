<?php
// api/cancel_order.php (Renamed logically to Full Return)
header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

$role = $_SESSION['role_name'] ?? '';
if ($role !== 'Admin' && $role !== 'Pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$sale_id = $data['sale_id'] ?? null;
$reason = $data['reason'] ?? 'Full Order Return';

if (!$sale_id) {
    echo json_encode(['success' => false, 'message' => 'No Sale ID provided.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Verify sale existence and check if already returned
    $stmt = $pdo->prepare("SELECT status FROM Sale WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) throw new Exception("Order not found.");
    if ($sale['status'] === 'Returned') throw new Exception("This order has already been returned.");

    // 2. Extract all items for Return Logging
    $stmt = $pdo->prepare("SELECT sale_item_id, units_sold, unit_price FROM Sale_Item WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        // Record as Customer Return
        $refund_amt = $item['units_sold'] * $item['unit_price'];
        $stmt = $pdo->prepare("INSERT INTO Customer_Return (sale_item_id, quantity, refund_amount, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$item['sale_item_id'], $item['units_sold'], $refund_amt, $reason]);
    }

    // 3. Extract and Restore Stock (from batches)
    $stmt = $pdo->prepare("SELECT sib.stock_id, sib.quantity_taken, si.sale_item_id 
                            FROM Sale_Item_Batch sib 
                            JOIN Sale_Item si ON sib.sale_item_id = si.sale_item_id 
                            WHERE si.sale_id = ?");
    $stmt->execute([$sale_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($batches as $batch) {
        // Update Stock count
        $stmt = $pdo->prepare("UPDATE Stock SET quantity = quantity + ? WHERE stock_id = ?");
        $stmt->execute([$batch['quantity_taken'], $batch['stock_id']]);

        // Record on Stock Card
        $stmt = $pdo->prepare("INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason) 
                               SELECT ?, ?, 'Refund', ?, quantity, ? FROM Stock WHERE stock_id = ?");
        $stmt->execute([
            $batch['stock_id'], 
            $_SESSION['user_id'], 
            $batch['quantity_taken'], 
            "Full Order Return #$sale_id",
            $batch['stock_id']
        ]);
    }

    // 4. Finalize status
    $stmt = $pdo->prepare("UPDATE Sale SET status = 'Returned' WHERE sale_id = ?");
    $stmt->execute([$sale_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Full order return processed and inventory updated."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
