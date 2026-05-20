<?php
$page_title = 'Item Management';
include 'includes/header.php';
require_once 'includes/db.php';

// Admin or Pharmacist Access
$curr_role = $_SESSION['role_name'] ?? '';
if ($curr_role !== 'Admin' && $curr_role !== 'Pharmacist') {
    header('Location: inventory.php');
    exit();
}

// 1. Calculate Item Catalog Metrics for HUD
$total_items = 0;

if (isset($pdo)) {
    // Total Unique Medicines
    $total_items = $pdo->query("SELECT COUNT(*) FROM Product")->fetchColumn();
}


$xcrud_html = '';
$view = $_GET['view'] ?? 'medicines';

if (file_exists('xcrud/xcrud.php')) {
    require_once ('xcrud/xcrud.php');

    if ($view === 'units') {
        $x_units = Xcrud::get_instance();
        $x_units->table('Unit_Size');
        $x_units->table_name('Allowed Units Definition');
        $x_units->label('size_description', 'Unit Name / Packaging Type');
        $x_units->unset_print();
        $x_units->unset_csv();
        $xcrud_html = $x_units->render();
    } elseif ($view === 'pricing') {
        $x_pup = Xcrud::get_instance();
        $x_pup->table('Product_Unit_Price');
        $x_pup->table_name('Medicine Unit Mappings & Pricing');
        $x_pup->relation('product_id', 'Product', 'product_id', 'product_name');
        $x_pup->relation('unit_size_id', 'Unit_Size', 'unit_size_id', 'size_description');
        $x_pup->label('product_id', 'Medicine');
        $x_pup->label('unit_size_id', 'Allowed Unit');
        $x_pup->label('price_per_unit', 'Price ($)');
        $x_pup->label('barcode', 'Barcode Identifier');
        $x_pup->unset_print();
        $x_pup->unset_csv();
        $xcrud_html = $x_pup->render();
    } else {
        $x_prod = Xcrud::get_instance();
        $x_prod->table('Product');
        $x_prod->table_name('Medicine Catalog (Master Items)');
        
        // Base Relations
        $x_prod->relation('supplier_id', 'Supplier', 'supplier_id', 'supplier_name');
        $x_prod->label('product_name', 'Medicine Name');
        $x_prod->label('supplier_id', 'Main Supplier');
        
        // Nested Grid 1: Unit Pricing & Barcodes
        $pricing = $x_prod->nested_table('Pricing & Units', 'product_id', 'Product_Unit_Price', 'product_id');
        $pricing->relation('unit_size_id', 'Unit_Size', 'unit_size_id', 'size_description');
        $pricing->label('unit_size_id', 'Item Packaging');
        $pricing->label('price_per_unit', 'Price ($)');
        $pricing->unset_print();
        $pricing->unset_csv();
        
        // Nested Grid 2: Clinical Symptoms (Dictionary Helper)
        $s_link = $x_prod->nested_table('Clinical Indicators', 'product_id', 'Product_Symptom', 'product_id');
        $s_link->relation('symptom_id', 'Symptom', 'symptom_id', 'symptom_name');
        $s_link->label('symptom_id', 'Treats Symptom');
        $s_link->unset_print();
        $s_link->unset_csv();

        $x_prod->unset_print();
        $x_prod->unset_csv();
        
        $xcrud_html = $x_prod->render();
    }
}
?>

<style>
/* Tab Styling */
.tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}
.tab {
    padding: 12px 24px;
    border-radius: 12px;
    background: var(--surface-light);
    color: var(--text-muted);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
    border: 1px solid var(--border-color);
}
.tab.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    box-shadow: var(--shadow-glow);
}

/* Standardization: Search Above Layout */
.xcrud-wrapper .xcrud-main {
    display: flex !important;
    flex-direction: column !important;
}
.xcrud-wrapper .xcrud-top-actions { order: 1 !important; margin-bottom: 16px; }
.xcrud-wrapper .xcrud-nav { order: 2 !important; margin-bottom: 24px; background: rgba(0,0,0,0.1); padding: 12px; border-radius: var(--radius-sm); }
.xcrud-wrapper .xcrud-list-container { order: 3 !important; }
</style>

<div class="page-container">
    
    <!-- Item Health HUD -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 320px)); gap: 24px; margin-bottom: 32px;">
        <div class="glass-panel" style="padding: 20px; display: flex; align-items: center; gap: 16px; border-left: 4px solid var(--primary-color);">
            <div style="background: rgba(99, 102, 241, 0.1); padding: 12px; border-radius: 12px; color: var(--primary-color);">
                <i class="ph ph-pill" style="font-size: 24px;"></i>
            </div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Total Items</p>
                <h3 style="font-size: 20px; font-weight: 800;"><?= $total_items ?> Medicines</h3>
            </div>
        </div>
    </div>

    <div class="glass-panel" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div>
                    <h3 style="margin:0;">Unified Item Management</h3>
                    <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">Manage drug definitions, pricing tiers, clinical attributes, and unit sizes.</p>
                </div>
            </div>
        </div>

        <div class="tabs">
            <a href="products.php?view=medicines" class="tab <?= ($view === 'medicines') ? 'active' : '' ?>">
                <i class="ph ph-pill"></i> Medicine Catalog
            </a>
            <a href="products.php?view=pricing" class="tab <?= ($view === 'pricing') ? 'active' : '' ?>">
                <i class="ph ph-tag"></i> Unit Mappings & Pricing
            </a>
            <a href="products.php?view=units" class="tab <?= ($view === 'units') ? 'active' : '' ?>">
                <i class="ph ph-package"></i> Allowed Units
            </a>
        </div>

        <div class="xcrud-wrapper">
            <?php if($xcrud_html): ?>
                <?= $xcrud_html ?>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; border: 2px dashed var(--accent-color); border-radius: var(--radius-md); background: rgba(244, 63, 94, 0.05);">
                    <i class="ph ph-warning-circle" style="font-size: 48px; color: var(--accent-color); margin-bottom: 16px;"></i>
                    <h4 style="color: white;">xCRUD Library Missing</h4>
                    <p style="color: var(--text-muted); margin-top: 8px;">Please upload the 'xcrud' folder to enable item management.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
