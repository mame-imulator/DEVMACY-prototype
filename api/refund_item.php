<?php
// api/refund_item.php
header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

// Admin/Pharmacist only for refunds
$role = $_SESSION['role_name'] ?? '';
if ($role !== 'Admin' && $role !== 'Pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$sale_item_id = $data['sale_item_id'] ?? null;
$refund_qty = (int)($data['quantity'] ?? 0);
$reason = $data['reason'] ?? 'Customer Refund';

if (!$sale_item_id || $refund_qty <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item or quantity.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Get original sale item details
    $stmt = $pdo->prepare("SELECT units_sold, unit_price FROM Sale_Item WHERE sale_item_id = ?");
    $stmt->execute([$sale_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Sale item not found.");
    }

    // Check if already refunded (total returned so far)
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM Customer_Return WHERE sale_item_id = ?");
    $stmt->execute([$sale_item_id]);
    $already_refunded = $stmt->fetchColumn() ?: 0;

    if (($already_refunded + $refund_qty) > $item['units_sold']) {
        throw new Exception("Refund quantity exceeds original purchase amount.");
    }

    // 2. Identify the batch(es) to return to
    // We'll return stock proportionately to the batches it was taken from
    $stmt = $pdo->prepare("SELECT stock_id, quantity_taken FROM Sale_Item_Batch WHERE sale_item_id = ?");
    $stmt->execute([$sale_item_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $remaining_to_return = $refund_qty;
    foreach ($batches as $batch) {
        if ($remaining_to_return <= 0) break;

        $return_to_this_batch = min($remaining_to_return, $batch['quantity_taken']);

        // Fetch current quantity for audit math
        $stmt = $pdo->prepare("SELECT quantity FROM Stock WHERE stock_id = ?");
        $stmt->execute([$batch['stock_id']]);
        $curr_qty = $stmt->fetchColumn();

        $new_balance = $curr_qty + $return_to_this_batch;

        // Update Stock
        $stmt = $pdo->prepare("UPDATE Stock SET quantity = ? WHERE stock_id = ?");
        $stmt->execute([$new_balance, $batch['stock_id']]);

        // Log Movement (Stock Card)
        $stmt = $pdo->prepare("INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason) 
                               VALUES (?, ?, 'Refund', ?, ?, ?)");
        $stmt->execute([
            $batch['stock_id'], 
            $_SESSION['user_id'], 
            $return_to_this_batch, 
            $new_balance, 
            "Customer Refund (Order Item #$sale_item_id)"
        ]);

        $remaining_to_return -= $return_to_this_batch;
    }

    // 3. Record the Refund
    $refund_total = $refund_qty * $item['unit_price'];
    $stmt = $pdo->prepare("INSERT INTO Customer_Return (sale_item_id, quantity, refund_amount, reason) VALUES (?, ?, ?, ?)");
    $stmt->execute([$sale_item_id, $refund_qty, $refund_total, $reason]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Refund processed successfully. Total: $" . number_format($refund_total, 2)]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
