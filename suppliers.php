<?php
// suppliers.php
$page_title = 'Supplier Directory';
include 'includes/header.php';

$xcrud_html = '';
if (file_exists('xcrud/xcrud.php')) {
    require ('xcrud/xcrud.php');
    $xcrud = Xcrud::get_instance();
    $xcrud->table('Supplier');
    
    // Dynamically pull in all products this supplier sells so it can be viewed and searched!
    $xcrud->subselect('products', 'SELECT GROUP_CONCAT(product_name SEPARATOR ", ") FROM Product WHERE supplier_id = {supplier_id}');
    
    // Set default order (A-Z by Company name)
    $xcrud->order_by('supplier_name', 'asc');
    
    // Check if there are any suppliers to decide on showing the Actions column
    $count = 0;
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM Supplier");
        $count = $stmt->fetchColumn();
    }

    // Define what columns are visible in the table structure
    $xcrud->columns('supplier_name, contact_name, phone_number, products');
    
    // Explicitly configure XCRUD's filtering function to allow combinations across these values
    $xcrud->search_columns('supplier_name, contact_name, phone_number, products');
    
    // Beautiful labels
    $xcrud->label('supplier_name', 'Company');
    $xcrud->label('contact_name', 'Contact Name');
    $xcrud->label('phone_number', 'Direct Line');
    $xcrud->label('products', 'Products Carried');

    // Requirement: Remove View button ALWAYS
    $xcrud->unset_view();

    // Requirement: Hide Actions column if no records exist
    if ($count == 0) {
        $xcrud->unset_edit();
        $xcrud->unset_remove();
    }

    // Remove Export & Print buttons
    $xcrud->unset_print();
    $xcrud->unset_csv();

    $xcrud_html = $xcrud->render();
}
?>

<style>
/* Force XCRUD to display its search/nav bar ABOVE the table */
.xcrud-wrapper .xcrud-main {
    display: flex !important;
    flex-direction: column !important;
}
.xcrud-wrapper .xcrud-top-actions { order: 1 !important; margin-bottom: 16px; }
.xcrud-wrapper .xcrud-nav { order: 2 !important; margin-bottom: 16px; background: rgba(0,0,0,0.1); padding: 12px; border-radius: var(--radius-sm); }
.xcrud-wrapper .xcrud-list-container { order: 3 !important; }
</style>

<div class="page-container">
    <div class="glass-panel" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div>
                <h3 style="margin-bottom: 4px;">Supplier Directory</h3>
                <p style="font-size: 13px; color: var(--text-muted); font-style: italic;">
                    <i class="ph ph-info"></i> Click on any column header to sort A-Z or Z-A.
                </p>
            </div>
        </div>
        
        <div class="xcrud-wrapper">
            <?php if($xcrud_html): ?>
                <?= $xcrud_html ?>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; border: 2px dashed var(--border-color); border-radius: var(--radius-md);">
                    <i class="ph ph-truck" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px;"></i>
                    <h4>XCRUD Supplier Grid will appear here</h4>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
