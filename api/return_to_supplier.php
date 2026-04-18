<?php
// api/return_to_supplier.php
header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

// Admin only for supplier returns
$role = $_SESSION['role_name'] ?? '';
if ($role !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin access required.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$stock_id = $data['stock_id'] ?? null;
$return_qty = (int)($data['quantity'] ?? 0);
$reason = $data['reason'] ?? 'Return to Supplier';

if (!$stock_id || $return_qty <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch or quantity.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Check current stock level
    $stmt = $pdo->prepare("SELECT quantity FROM Stock WHERE stock_id = ?");
    $stmt->execute([$stock_id]);
    $curr_qty = $stmt->fetchColumn();

    if ($curr_qty === false) {
        throw new Exception("Stock batch not found.");
    }

    if ($return_qty > $curr_qty) {
        throw new Exception("Return quantity exceeds current stock level.");
    }

    $new_balance = $curr_qty - $return_qty;

    // 2. Deduct from Stock (Wait, should we delete the row if 0? No, keep it as an audited zero.)
    $stmt = $pdo->prepare("UPDATE Stock SET quantity = ? WHERE stock_id = ?");
    $stmt->execute([$new_balance, $stock_id]);

    // 3. Log Movement (Stock Card)
    $stmt = $pdo->prepare("INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason) 
                           VALUES (?, ?, 'SupplierReturn', ?, ?, ?)");
    $stmt->execute([
        $stock_id,
        $_SESSION['user_id'],
        -$return_qty,
        $new_balance,
        "Supplier Return: $reason"
    ]);

    // 4. Record the Return
    $stmt = $pdo->prepare("INSERT INTO Supplier_Return (stock_id, quantity, reason) VALUES (?, ?, ?)");
    $stmt->execute([$stock_id, $return_qty, $reason]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Successfully returned $return_qty units to supplier."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
