<?php
// api/adjust_stock.php
header('Content-Type: application/json');
require_once '../includes/db.php';
session_start();

// Strict Admin/Pharmacist Only
$role = $_SESSION['role_name'] ?? '';
if ($role !== 'Admin' && $role !== 'Pharmacist') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$stock_id = $data['stock_id'] ?? null;
$new_qty = $data['new_qty'] ?? null;
$reason = $data['reason'] ?? 'Manual Adjustment';

if ($stock_id === null || $new_qty === null) {
    echo json_encode(['success' => false, 'message' => 'Missing data.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Get current state
    $stmt = $pdo->prepare("SELECT quantity FROM Stock WHERE stock_id = ?");
    $stmt->execute([$stock_id]);
    $curr_qty = $stmt->fetchColumn();

    if ($curr_qty === false) {
        throw new Exception("Stock record not found.");
    }

    $change = $new_qty - $curr_qty;
    if ($change == 0) {
        throw new Exception("No change in quantity detected.");
    }

    // 2. Update Stock
    $stmt = $pdo->prepare("UPDATE Stock SET quantity = ? WHERE stock_id = ?");
    $stmt->execute([$new_qty, $stock_id]);

    // 3. Log Movement
    $stmt = $pdo->prepare("INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason) 
                           VALUES (?, ?, 'Adjustment', ?, ?, ?)");
    $stmt->execute([
        $stock_id,
        $_SESSION['user_id'],
        $change,
        $new_qty,
        $reason
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Stock adjusted and logged successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
