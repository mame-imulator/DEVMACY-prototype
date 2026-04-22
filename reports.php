<?php
// reports.php
$page_title = 'Analytics & Reports';
include 'includes/header.php';

$view = $_GET['view'] ?? 'sales';
$xcrud_html = '';

// Custom callback to prevent xCRUD from truncating long strings
if (!function_exists('format_multi_line_items')) {
    function format_multi_line_items($value, $fieldname, $primary_key, $row, $xcrud) {
        return $value; 
    }
}

if (file_exists('xcrud/xcrud.php')) {
    require_once ('xcrud/xcrud.php');
    $xcrud = Xcrud::get_instance();

    if ($view === 'customer_returns') {
        $xcrud->table('Customer_Return');
        $xcrud->table_name('Customer Returned Items');
        
        // Link to Product Name via Sale_Item
        $xcrud->subselect('Medicine', 'SELECT p.product_name FROM Product p JOIN Sale_Item si ON si.product_id = p.product_id WHERE si.sale_item_id = {sale_item_id}');
        $xcrud->subselect('Packaging', 'SELECT us.size_description FROM Unit_Size us JOIN Sale_Item si ON si.unit_size_id = us.unit_size_id WHERE si.sale_item_id = {sale_item_id}');
        
        $xcrud->columns('Medicine, Packaging, quantity, refund_amount, reason, created_at');
        $xcrud->label('quantity', 'Qty');
        $xcrud->label('refund_amount', 'Refund ($)');
        $xcrud->label('created_at', 'Date Returned');
        
        $xcrud->sum('refund_amount', 'Total Refund: {value}');
        
        $xcrud->unset_add();
        $xcrud->unset_edit();
        $xcrud->unset_remove();

    } elseif ($view === 'supplier_returns') {
        $xcrud->table('Supplier_Return');
        $xcrud->table_name('Returns to Suppliers');
        
        // Link to Product Name via Stock
        $xcrud->subselect('Medicine', 'SELECT p.product_name FROM Product p JOIN Stock s ON s.product_id = p.product_id WHERE s.stock_id = {stock_id}');
        $xcrud->subselect('Packaging', 'SELECT us.size_description FROM Unit_Size us JOIN Stock s ON s.unit_size_id = us.unit_size_id WHERE s.stock_id = {stock_id}');
        
        $xcrud->columns('Medicine, Packaging, quantity, reason, created_at');
        $xcrud->label('quantity', 'Qty');
        $xcrud->label('created_at', 'Date Processed');
        
        $xcrud->sum('quantity', 'Total Items: {value}');
        
        $xcrud->unset_add();
        $xcrud->unset_edit();
        $xcrud->unset_remove();

    } else {
        // DEFAULT: Order History
        $xcrud->table('Sale');
        $xcrud->table_name('Order History');
        
        // Separate Subselects for better readability - Using <br> to separate items
        $xcrud->subselect('Items Sold', 'SELECT GROUP_CONCAT(CONCAT(p.product_name, " x ", si.units_sold, " @ $", si.unit_price) SEPARATOR "<br>") FROM Sale_Item si JOIN Product p ON si.product_id = p.product_id WHERE si.sale_id = {sale_id}');
        $xcrud->subselect('Total ($)', 'SELECT SUM(si.unit_price * si.units_sold) FROM Sale_Item si WHERE si.sale_id = {sale_id}');
        
        $xcrud->columns('sale_id, sale_date, Items Sold, Total ($), status');
        $xcrud->label('sale_id', 'Order #');
        
        // Use a callback to bypass xCRUD's automatic string trimming completely
        $xcrud->column_callback('Items Sold', 'format_multi_line_items');
        
        // Note: xCRUD sum() on subselects can be complex depending on version, 
        // if it doesn't show, we'd need a real column in the DB.
        
        $xcrud->change_type('sale_date', 'date', '', array('format' => 'd/m/Y'));
        
        // Highlight Returned orders
        $xcrud->highlight('status', '=', 'Returned', 'rgba(245, 158, 11, 0.1)');
        
        // Nested items view
        $items = $xcrud->nested_table('Order Details','sale_id','Sale_Item','sale_id');
        $items->columns('product_id, unit_size_id, units_sold, unit_price');
        $items->relation('product_id','Product','product_id','product_name');
        $items->relation('unit_size_id','Unit_Size','unit_size_id','size_description');
        
        if (($_SESSION['role_name'] ?? '') === 'Admin' || ($_SESSION['role_name'] ?? '') === 'Pharmacist') {
            $items->button('#', 'Refund Item', 'ph ph-arrow-u-up-left', '', array(
                'onclick' => 'refundItem({sale_item_id}, {units_sold})',
                'style' => 'color: var(--secondary-color);'
            ));
            
            $xcrud->button('#', 'Return Full Order', 'ph ph-arrow-counter-clockwise', '', array(
                'onclick' => 'returnFullOrder({sale_id})',
                'style' => 'color: var(--accent-color);'
            ));
        }
        
        $xcrud->unset_edit();
        $xcrud->unset_remove();
        $xcrud->unset_add();
    }

    $xcrud->unset_print();
    $xcrud->unset_csv();
    $xcrud_html = $xcrud->render();
}
?>

<style>
/* Standardization & Tabs */
.xcrud-wrapper .xcrud-main { display: flex !important; flex-direction: column !important; }
.xcrud-wrapper .xcrud-top-actions { order: 1 !important; margin-bottom: 16px; }
.xcrud-wrapper .xcrud-nav { order: 2 !important; margin-bottom: 24px; background: rgba(0,0,0,0.1); padding: 12px; border-radius: var(--radius-sm); }
.xcrud-wrapper .xcrud-list-container { order: 3 !important; }

.tabs { display: flex; gap: 12px; margin-bottom: 24px; }
.tab { padding: 12px 24px; border-radius: 12px; background: var(--surface-light); color: var(--text-muted); text-decoration: none; font-weight: 600; transition: all 0.2s; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 8px; }
.tab.active { background: var(--primary-color); color: white; border-color: var(--primary-color); box-shadow: var(--shadow-glow); }
</style>

<div class="page-container">
    
    <div class="glass-panel" style="padding: 24px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin:0;">Reporting & Returns</h3>
        </div>

        <div class="tabs">
            <a href="reports.php?view=sales" class="tab <?= ($view === 'sales') ? 'active' : '' ?>">
                <i class="ph ph-receipt"></i> Order Reports
            </a>
            <a href="reports.php?view=customer_returns" class="tab <?= ($view === 'customer_returns') ? 'active' : '' ?>">
                <i class="ph ph-arrow-u-up-left"></i> Customer Returns
            </a>
            <a href="reports.php?view=supplier_returns" class="tab <?= ($view === 'supplier_returns') ? 'active' : '' ?>">
                <i class="ph ph-truck"></i> Supplier Returns
            </a>
        </div>
        
        <?php if($xcrud_html): ?>
            <div class="xcrud-wrapper">
                <?= $xcrud_html ?>
            </div>
        <?php else: ?>
            <div style="padding: 40px; text-align: center; border: 2px dashed var(--accent-color); border-radius: var(--radius-md); background: rgba(244, 63, 94, 0.05);">
                <i class="ph ph-warning-circle" style="font-size: 48px; color: var(--accent-color); margin-bottom: 16px;"></i>
                <h4 style="color: white;">xCRUD Library Missing</h4>
            </div>
        <?php endif; ?>
    </div>

    <!-- Analytics Placeholders -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div class="glass-panel" style="padding: 32px; text-align: center;">
            <i class="ph ph-calendar-warning" style="font-size: 32px; color: var(--accent-color); margin-bottom: 12px;"></i>
            <h4 style="margin-bottom: 8px;">Expiry Analytics</h4>
            <p style="color: var(--text-muted); font-size: 13px;">Automated batch alerts in next update.</p>
        </div>
        <div class="glass-panel" style="padding: 32px; text-align: center;">
            <i class="ph ph-chart-line-up" style="font-size: 32px; color: var(--secondary-color); margin-bottom: 12px;"></i>
            <h4 style="margin-bottom: 8px;">Revenue Trends</h4>
            <p style="color: var(--text-muted); font-size: 13px;">Detailed monthly breakdowns coming soon.</p>
        </div>
    </div>
</div>

<script>
async function refundItem(itemId, maxQty) {
    const qty = prompt(`Enter number of units to refund (Max: ${maxQty}):`, maxQty);
    if (qty === null || qty === "" || isNaN(qty) || parseInt(qty) <= 0) return;
    
    if (parseInt(qty) > parseInt(maxQty)) {
        alert("Refund quantity cannot exceed items sold.");
        return;
    }

    const reason = prompt("Reason for refund:", "Customer Refund");
    if (reason === null) return;

    try {
        const response = await fetch('api/refund_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                sale_item_id: itemId,
                quantity: parseInt(qty),
                reason: reason
            })
        });
        
        const result = await response.json();
        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Failed to connect to refund API.');
    }
}

async function returnFullOrder(saleId) {
    if (!confirm(`Are you sure you want to RETURN Order #${saleId}? This will log all items as returns and restore stock.`)) {
        return;
    }

    const reason = prompt("Reason for returning this order:", "Full Order Return");
    if (reason === null) return;

    try {
        const response = await fetch('api/cancel_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sale_id: saleId, reason: reason })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Success: ' + result.message);
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Failed to connect to returns API.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
