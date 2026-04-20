<?php
// promotions.php
$page_title = 'Promotion Management';
include 'includes/header.php';

// Restricted to Admins
if (($_SESSION['role_name'] ?? '') !== 'Admin') {
    echo "<div class='page-container'><div class='glass-panel' style='padding:40px; text-align:center;'><h3>Access Denied</h3><p>Only Administrators can manage store promotions.</p></div></div>";
    include 'includes/footer.php';
    exit();
}

// AUTO-MIGRATION: Ensure Promotions table exists
try {
    $pdo->query("SELECT 1 FROM Promotion LIMIT 1");
} catch (Exception $e) {
    $create_sql = "
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
    );";
    $pdo->exec($create_sql);
}

$xcrud_html = '';
if (file_exists('xcrud/xcrud.php')) {
    require_once ('xcrud/xcrud.php');
    $xcrud = Xcrud::get_instance();
    $xcrud->table('Promotion');
    $xcrud->table_name('Store Promotions & Discounts');
    
    // Link Barcode to Product Name + Size
    $xcrud->relation('barcode', 'Product_Unit_Price', 'barcode', array('barcode', 'product_id', 'unit_size_id'));
    // We want a more readable label in the relation
    $xcrud->subselect('Medicine', 'SELECT CONCAT(p.product_name, " (", us.size_description, ")") FROM Product p JOIN Product_Unit_Price pup ON p.product_id = pup.product_id JOIN Unit_Size us ON us.unit_size_id = pup.unit_size_id WHERE pup.barcode = {barcode}');
    
    $xcrud->columns('promo_name, Medicine, discount_type, discount_value, start_date, end_date, is_active');
    
    $xcrud->label('promo_name', 'Promotion Name');
    $xcrud->label('discount_value', 'Value');
    $xcrud->label('is_active', 'Active');
    
    $xcrud->fields('promo_name, barcode, discount_type, discount_value, start_date, end_date, is_active');
    
    $xcrud->validation_required('promo_name');
    $xcrud->validation_required('barcode');
    $xcrud->validation_required('discount_value');
    
    // Styling
    $xcrud->change_type('discount_type', 'select', '', array('Percentage' => 'Percentage (%)', 'Fixed' => 'Fixed Amount ($)'));
    $xcrud->highlight('is_active', '=', '0', '#fee2e2');

    $xcrud_html = $xcrud->render();
}
?>

<div class="page-container">
    <div class="glass-panel" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin:0;">Targeted Promotions</h3>
        </div>

        <?php if($xcrud_html): ?>
            <div class="xcrud-wrapper">
                <?= $xcrud_html ?>
            </div>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; border: 2px dashed var(--accent-color); border-radius: var(--radius-md);">
                <i class="ph ph-warning-circle" style="font-size: 48px; color: var(--accent-color); margin-bottom: 16px;"></i>
                <h4 style="color: white;">xCRUD Library Missing</h4>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
