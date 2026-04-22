<?php
// api/migrate_multi_inventory.php
require_once '../includes/db.php';

if (!$pdo) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}

try {
    // 1. Manually check if location column exists
    $check = $pdo->query("SHOW COLUMNS FROM Stock LIKE 'location'")->fetch();
    
    if (!$check) {
        $pdo->exec("ALTER TABLE Stock ADD COLUMN location ENUM('Front', 'Back') NOT NULL DEFAULT 'Back'");
        // Only update to 'Back' if we just added the column
        $pdo->exec("UPDATE Stock SET location = 'Back'");
    }
    
    echo json_encode(['success' => true, 'message' => 'Inventory migration check completed.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()]);
}
?>
