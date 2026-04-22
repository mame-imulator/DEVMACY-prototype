<?php
// includes/db.php

$host = 'localhost';
$dbname = 'devmacy';
$username = 'root';
$password = ''; // XAMPP default

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // One-time migration for multi-inventory system
    if (!isset($_SESSION['inventory_migrated'])) {
        $check = $pdo->query("SHOW COLUMNS FROM Stock LIKE 'location'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE Stock ADD COLUMN location ENUM('Front', 'Back') NOT NULL DEFAULT 'Back'");
            $pdo->exec("UPDATE Stock SET location = 'Back'");
        }
        $_SESSION['inventory_migrated'] = true;
    }
} catch(PDOException $e) {
    // If connection fails, silently fail gracefully for now so the UI doesn't break
    // In production, you would handle this more aggressively.
    $pdo = null;
    $db_error = "Connection failed: " . $e->getMessage();
}
?>
