<?php
// api/migrate_multi_inventory.php
require_once '../includes/db.php';

if (!$pdo) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}

try {
    // 1. Add location column if it doesn't exist
    $pdo->exec("ALTER TABLE Stock ADD COLUMN IF NOT EXISTS location ENUM('Front', 'Back') NOT NULL DEFAULT 'Back'");
    
    // 2. Ensure all existing stock is set to 'Back' as per user request
    $pdo->exec("UPDATE Stock SET location = 'Back'");
    
    echo json_encode(['success' => true, 'message' => 'Inventory migration completed successfully. All stock moved to Back Storeroom.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()]);
}
?>
