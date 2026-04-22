<?php
// api/intake_stock.php
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
$product_id = $data['product_id'] ?? null;
$unit_size_id = $data['unit_size_id'] ?? null;
$new_qty = (int)($data['quantity'] ?? 0);
$expiry_date = $data['expiry_date'] ?? null; // Format: YYYY-MM-DD from HTML date input
$reason = $data['reason'] ?? 'New Stock Delivery';

if (!$product_id || !$unit_size_id || $new_qty <= 0 || !$expiry_date) {
    echo json_encode(['success' => false, 'message' => 'All fields are required. Quantity must be greater than zero.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 0. Hardware / Logic Validation
    // Prevent defining a batch using units that aren't registered for this specific drug
    $stmt = $pdo->prepare("SELECT 1 FROM Product_Unit_Price WHERE product_id = ? AND unit_size_id = ?");
    $stmt->execute([$product_id, $unit_size_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Invalid Unit Sizing: This drug is not configured to be sold in the selected unit. Please check the master catalog.");
    }

    // 1. Check if an identical batch exists (Product + Unit + Expiry)
    $stmt = $pdo->prepare("SELECT stock_id, quantity FROM Stock WHERE product_id = ? AND unit_size_id = ? AND expiry_date = ?");
    $stmt->execute([$product_id, $unit_size_id, $expiry_date]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // MERGE: Update existing batch
        $stock_id = $existing['stock_id'];
        $new_balance = $existing['quantity'] + $new_qty;
        
        $stmt = $pdo->prepare("UPDATE Stock SET quantity = ? WHERE stock_id = ?");
        $stmt->execute([$new_balance, $stock_id]);
    } else {
        // NEW BATCH
        $stmt = $pdo->prepare("INSERT INTO Stock (product_id, unit_size_id, quantity, expiry_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $unit_size_id, $new_qty, $expiry_date]);
        $stock_id = $pdo->lastInsertId();
        $new_balance = $new_qty;
    }

    // 2. Log Movement (The Audit Trail)
    $stmt = $pdo->prepare("INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason) 
                           VALUES (?, ?, 'Purchase', ?, ?, ?)");
    $stmt->execute([
        $stock_id,
        $_SESSION['user_id'],
        $new_qty,
        $new_balance,
        $reason
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Inventory updated and movement logged.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
