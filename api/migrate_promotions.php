<?php
// api/migrate_promotions.php
require_once '../includes/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS Promotion (
    promo_id INT PRIMARY KEY AUTO_INCREMENT,
    promo_name VARCHAR(100) NOT NULL,
    barcode VARCHAR(50) NOT NULL,
    discount_type ENUM('Percentage', 'Fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barcode) REFERENCES Product_Unit_Price(barcode) ON DELETE CASCADE
);
";

try {
    $pdo->exec($sql);
    echo "Promotion table created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
