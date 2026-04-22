<?php
// api/transfer_stock.php
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
$source_stock_id = $data['stock_id'] ?? null;
$qty_to_move = (int)($data['quantity'] ?? 0);
$target_location = $data['target_location'] ?? 'Front'; // Usually 'Front'

if (!$source_stock_id || $qty_to_move <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid source batch or quantity.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Get source batch details
    $stmt = $pdo->prepare("SELECT product_id, unit_size_id, quantity, expiry_date, location FROM Stock WHERE stock_id = ?");
    $stmt->execute([$source_stock_id]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$source) throw new Exception("Source stock batch not found.");
    if ($source['quantity'] < $qty_to_move) throw new Exception("Insufficient quantity in source batch.");
    if ($source['location'] === $target_location) throw new Exception("Batch is already in the $target_location location.");

    // 2. Find or Create target batch
    // We try to merge into an existing batch with same product, size, and expiry at the target location
    $stmt = $pdo->prepare("SELECT stock_id, quantity FROM Stock 
                           WHERE product_id = ? AND unit_size_id = ? AND expiry_date = ? AND location = ?
                           LIMIT 1");
    $stmt->execute([$source['product_id'], $source['unit_size_id'], $source['expiry_date'], $target_location]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($target) {
        $target_stock_id = $target['stock_id'];
        // Update existing target
        $stmt = $pdo->prepare("UPDATE Stock SET quantity = quantity + ? WHERE stock_id = ?");
        $stmt->execute([$qty_to_move, $target_stock_id]);
    } else {
        // Create new target batch
        $stmt = $pdo->prepare("INSERT INTO Stock (product_id, unit_size_id, quantity, expiry_date, location) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$source['product_id'], $source['unit_size_id'], $qty_to_move, $source['expiry_date'], $target_location]);
        $target_stock_id = $pdo->lastInsertId();
    }

    // 3. Deduct from source batch
    $stmt = $pdo->prepare("UPDATE Stock SET quantity = quantity - ? WHERE stock_id = ?");
    $stmt->execute([$qty_to_move, $source_stock_id]);

    // 4. Log Movement (Two entries: Out from Source, In to Target)
    $stmt = $pdo->prepare("INSERT INTO Stock_Movement (stock_id, user_id, type, quantity_change, balance_after, reason) 
                           VALUES (?, ?, 'Adjustment', ?, ?, ?)");
    
    // Out from Back
    $stmt->execute([
        $source_stock_id, 
        $_SESSION['user_id'], 
        -$qty_to_move, 
        $source['quantity'] - $qty_to_move, 
        "Transferred to $target_location"
    ]);

    // In to Front
    $stmt->execute([
        $target_stock_id, 
        $_SESSION['user_id'], 
        $qty_to_move, 
        ($target['quantity'] ?? 0) + $qty_to_move, 
        "Restocked from {$source['location']}"
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Successfully moved $qty_to_move units to $target_location."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
