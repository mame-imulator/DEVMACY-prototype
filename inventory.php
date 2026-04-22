<?php
// inventory.php
$page_title = 'Inventory Management';
include 'includes/header.php';

$valuation = 0.00;
$critical_expiry = 0;

if (isset($pdo) && $pdo) {
    try {
        // Stock Valuation (Product total value based on current quantity and price)
        $stmt = $pdo->query("
            SELECT SUM(s.quantity * COALESCE(pup.price_per_unit, 0)) as total 
            FROM Stock s 
            LEFT JOIN Product_Unit_Price pup ON s.product_id = pup.product_id AND s.unit_size_id = pup.unit_size_id
        ");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res && $res['total']) $valuation = $res['total'];

        // Critical Expiry (Next 60 days)
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM Stock WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND quantity > 0");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res) $critical_expiry = $res['cnt'];
    } catch(PDOException $e) {
        $db_error = $e->getMessage();
    }
}
if (file_exists('xcrud/xcrud.php')) {
    require ('xcrud/xcrud.php');
    $xcrud = Xcrud::get_instance();
    $xcrud->table('Stock');
    $xcrud->table_name('Current Inventory Batches');
    $xcrud->relation('product_id','Product','product_id','product_name');
    $xcrud->relation('unit_size_id','Unit_Size','unit_size_id','size_description');
    
    $xcrud->columns('product_id, unit_size_id, quantity, location, expiry_date');
    $xcrud->label('product_id', 'Medicine');
    $xcrud->label('unit_size_id', 'Unit');
    $xcrud->label('quantity', 'Current Stock');
    $xcrud->label('location', 'Location');
    
    // Formatting: Use slashes for date
    $xcrud->change_type('expiry_date', 'date', '', array('format' => 'd/m/Y'));
    
    // Nested Stock Card (Movement History / Update Trail)
    $ledger = $xcrud->nested_table('View Update Trail','stock_id','Stock_Movement','stock_id');
    $ledger->columns('created_at, type, quantity_change, balance_after, reason, user_id');
    $ledger->relation('user_id', 'Users', 'user_id', 'full_name');
    $ledger->label('created_at', 'Update Time');
    $ledger->label('quantity_change', 'Total (+/-)');
    $ledger->label('balance_after', 'Running Balance');
    $ledger->label('user_id', 'Action By');
    
    $ledger->change_type('created_at', 'datetime', '', array('format' => 'd/m/Y H:i'));
    
    $xcrud->unset_add();
    $xcrud->unset_edit();
    $xcrud->unset_remove();
    $xcrud->unset_print();
    $xcrud->unset_csv();
    
    // Separate Add/Remove buttons for Admin
    if (($_SESSION['role_name'] ?? '') === 'Admin') {
        $xcrud->button('#', 'Add Stock (+)', 'ph ph-plus-circle', '', array(
            'onclick' => 'addStockUnits({stock_id}, {quantity})',
            'style' => 'color: var(--secondary-color);'
        ));
        $xcrud->button('#', 'Remove (-)', 'ph ph-minus-circle', '', array(
            'onclick' => 'removeStockUnits({stock_id}, {quantity})',
            'style' => 'color: var(--accent-color);'
        ));
        $xcrud->button('#', 'Return to Supplier', 'ph ph-arrow-square-out', '', array(
            'onclick' => 'returnToSupplier({stock_id}, {quantity})',
            'style' => 'color: #94A3B8;'
        ));
        
        // Transfer Buttons
        $xcrud->button('#', 'Move to Front', 'ph ph-arrow-fat-line-right', '', array(
            'onclick' => 'moveStock({stock_id}, {quantity}, "Back", "Front")',
            'style' => 'color: var(--primary-color);',
            'data-show-where' => '{location} == "Back"' // xCRUD pseudo-logic if supported, else we handle in JS
        ));
        $xcrud->button('#', 'Move to Back', 'ph ph-arrow-fat-line-left', '', array(
            'onclick' => 'moveStock({stock_id}, {quantity}, "Front", "Back")',
            'style' => 'color: #64748B;',
            'data-show-where' => '{location} == "Front"'
        ));
    }

    $xcrud_html = $xcrud->render();
}
?>

<div class="page-container" style="padding-bottom: 40px;">

    <!-- Inventory Overview HUD -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px;">
        <div class="glass-panel" style="padding: 20px; display: flex; align-items: center; gap: 16px; border-left: 4px solid var(--secondary-color);">
            <div style="background: rgba(16, 185, 129, 0.1); padding: 12px; border-radius: 12px; color: var(--secondary-color);">
                <i class="ph ph-money" style="font-size: 24px;"></i>
            </div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Net Stock Value</p>
                <h3 style="font-size: 20px; font-weight: 800;">$<?= number_format($valuation, 2) ?></h3>
            </div>
        </div>

        <div class="glass-panel" style="padding: 20px; display: flex; align-items: center; gap: 16px; border-left: 4px solid var(--accent-color);">
            <div style="background: rgba(244, 63, 94, 0.1); padding: 12px; border-radius: 12px; color: var(--accent-color);">
                <i class="ph ph-calendar-x" style="font-size: 24px;"></i>
            </div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Near Expiry Batches</p>
                <h3 style="font-size: 20px; font-weight: 800;"><?= $critical_expiry ?> Alerts</h3>
            </div>
        </div>

        <div class="glass-panel" style="padding: 20px; display: flex; align-items: center; gap: 16px; border-left: 4px solid var(--primary-color);">
            <div style="background: rgba(99, 102, 241, 0.1); padding: 12px; border-radius: 12px; color: var(--primary-color);">
                <i class="ph ph-package" style="font-size: 24px;"></i>
            </div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Active Batches</p>
                <h3 style="font-size: 20px; font-weight: 800;">Monitoring...</h3>
            </div>
        </div>
    </div>

    <div class="glass-panel" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <div>
                <h3 style="margin:0;">Inventory Stock Cards</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">Auditable ledger of all medicine movements (In/Out).</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="reports.php?view=supplier_returns" class="btn" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; background: rgba(148, 163, 184, 0.1); color: var(--text-muted); text-decoration: none; border-radius: var(--radius-sm); font-weight: 600; font-size: 14px; transition: all 0.2s; border: 1px solid var(--border-color);">
                    <i class="ph ph-list-numbers"></i> View Return Logs
                </a>
                <button onclick="openIntakeModal()" class="btn-primary" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px;">
                    <i class="ph ph-plus"></i> New Delivery
                </button>
            </div>
        </div>
        
        <div class="xcrud-wrapper">
            <?php if($xcrud_html): ?>
                <?= $xcrud_html ?>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; border: 2px dashed var(--accent-color); border-radius: var(--radius-md); background: rgba(244, 63, 94, 0.05);">
                    <i class="ph ph-warning-circle" style="font-size: 48px; color: var(--accent-color); margin-bottom: 16px;"></i>
                    <h4 style="color: white;">xCRUD Library Missing</h4>
                    <p style="color: var(--text-muted); margin-top: 8px;">Please upload the 'xcrud' folder to enable inventory management.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Force XCRUD to display its search/nav bar ABOVE the table */
    .xcrud-wrapper .xcrud-main {
        display: flex !important;
        flex-direction: column !important;
    }
    .xcrud-wrapper .xcrud-top-actions { order: 1 !important; margin-bottom: 16px; }
    .xcrud-wrapper .xcrud-nav { order: 2 !important; margin-bottom: 16px; background: rgba(0,0,0,0.1); padding: 12px; border-radius: var(--radius-sm); }
    .xcrud-wrapper .xcrud-list-container { order: 3 !important; }

    /* Grid Highlighting Styles (Injected via CSS targeting xCRUD cells) */
    .xcrud-list td:nth-child(3):contains("-") { color: #f43f5e; font-weight: bold; } /* If negative balance */
</style>

<div id="intakeModal" class="glass-panel" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); z-index:1000; width:500px; padding:32px; border: 1px solid var(--primary-color);">
    <h3 style="margin-bottom: 24px;">Record New Delivery</h3>
    <form id="intakeForm">
        <div style="margin-bottom: 16px;">
            <label style="display:block; margin-bottom:8px;">Medicine</label>
            <select name="product_id" id="intake_product" required style="width:100%; padding:12px; background:var(--bg-color); color:white; border:1px solid var(--border-color); border-radius:8px;">
                <!-- Populated via JS -->
            </select>
        </div>
        <div style="margin-bottom: 16px;">
            <label style="display:block; margin-bottom:8px;">Unit Size</label>
            <select name="unit_size_id" id="intake_unit" required style="width:100%; padding:12px; background:var(--bg-color); color:white; border:1px solid var(--border-color); border-radius:8px;">
                <!-- Populated via JS -->
            </select>
        </div>
        <div style="display:flex; gap:16px; margin-bottom:16px;">
            <div style="flex:1;">
                <label style="display:block; margin-bottom:8px;">Quantity Change</label>
                <input type="number" name="quantity" required style="width:100%; padding:12px; background:var(--bg-color); color:white; border:1px solid var(--border-color); border-radius:8px;">
            </div>
            <div style="flex:1;">
                <label style="display:block; margin-bottom:8px;">Expiry Date</label>
                <input type="date" name="expiry_date" required style="width:100%; padding:12px; background:var(--bg-color); color:white; border:1px solid var(--border-color); border-radius:8px;">
            </div>
        </div>
        <div style="margin-bottom: 24px;">
            <label style="display:block; margin-bottom:8px;">Notes (e.g. GRN Number)</label>
            <input type="text" name="reason" placeholder="Opening Stock / Monthly Restock" style="width:100%; padding:12px; background:var(--bg-color); color:white; border:1px solid var(--border-color); border-radius:8px;">
        </div>
        
        <div style="display:flex; justify-content:flex-end; gap:12px;">
            <button type="button" onclick="closeIntakeModal()" style="padding:10px 20px; background:transparent; border:1px solid var(--border-color); color:white; border-radius:8px; cursor:pointer;">Cancel</button>
            <button type="submit" class="btn-primary" style="padding:10px 20px;">Save Delivery</button>
        </div>
    </form>
</div>
<div id="modalOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:999;" onclick="closeIntakeModal()"></div>

<script>
async function openIntakeModal() {
    // Fetch Products & Units for dropdowns
    try {
        const [prodRes, unitRes] = await Promise.all([
            fetch('api/get_metadata.php?type=product_list'), 
            fetch('api/get_metadata.php?type=unit_size')
        ]);
        
        const products = await prodRes.json();
        const units = await unitRes.json();
        
        const pSelect = document.getElementById('intake_product');
        pSelect.innerHTML = products.map(p => `<option value="${p.product_id}">${p.product_name}</option>`).join('');
        
        const uSelect = document.getElementById('intake_unit');
        uSelect.innerHTML = units.map(u => `<option value="${u.unit_size_id}">${u.size_description}</option>`).join('');
        
        document.getElementById('intakeModal').style.display = 'block';
        document.getElementById('modalOverlay').style.display = 'block';
    } catch (e) {
        alert('Error loading medicines list.');
    }
}

function closeIntakeModal() {
    document.getElementById('intakeModal').style.display = 'none';
    document.getElementById('modalOverlay').style.display = 'none';
}

document.getElementById('intakeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const response = await fetch('api/intake_stock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Failed to save intake.');
    }
});

async function addStockUnits(stockId, currentQty) {
    const addAmount = prompt("Enter number of units to ADD to this batch:", "0");
    if (addAmount === null || addAmount === "" || isNaN(addAmount) || parseInt(addAmount) <= 0) return;
    
    const reason = prompt("Reason for adding stock (e.g. Corrected count, Return):", "Manual Addition");
    if (reason === null) return;

    const newQty = parseInt(currentQty) + parseInt(addAmount);
    performAdjustment(stockId, newQty, reason);
}

async function removeStockUnits(stockId, currentQty) {
    const removeAmount = prompt("Enter number of units to REMOVE from this batch:", "0");
    if (removeAmount === null || removeAmount === "" || isNaN(removeAmount) || parseInt(removeAmount) <= 0) return;
    
    if (parseInt(removeAmount) > parseInt(currentQty)) {
        alert("Cannot remove more than current stock level.");
        return;
    }

    const reason = prompt("Reason for removal (e.g. Breakage, Expired, Lost):", "Wastage/Disposal");
    if (reason === null) return;

    const newQty = parseInt(currentQty) - parseInt(removeAmount);
    performAdjustment(stockId, newQty, reason);
}

async function returnToSupplier(stockId, currentQty) {
    const qty = prompt(`Enter number of units to return to supplier (Current Stock: ${currentQty}):`, "0");
    if (qty === null || qty === "" || isNaN(qty) || parseInt(qty) <= 0) return;
    
    if (parseInt(qty) > parseInt(currentQty)) {
        alert("Cannot return more than what is currently in stock.");
        return;
    }

    const reason = prompt("Reason for return (e.g. Defective, Near Expiry):", "Stock Return");
    if (reason === null) return;

    try {
        const response = await fetch('api/return_to_supplier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                stock_id: stockId,
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
        alert('Failed to connect to return API.');
    }
}

async function performAdjustment(stockId, newQty, reason) {
    try {
        const response = await fetch('api/adjust_stock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                stock_id: stockId,
                new_qty: newQty,
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
        alert('Failed to connect to adjustment API.');
    }
}

async function moveStock(stockId, currentQty, fromMode, toMode) {
    const qty = prompt(`Enter number of units to move from ${fromMode} to ${toMode} (Available: ${currentQty}):`, currentQty);
    if (qty === null || qty === "" || isNaN(qty) || parseInt(qty) <= 0) return;
    
    if (parseInt(qty) > parseInt(currentQty)) {
        alert(`Cannot move more than what is available in ${fromMode}.`);
        return;
    }

    try {
        const response = await fetch('api/transfer_stock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                stock_id: stockId,
                quantity: parseInt(qty),
                target_location: toMode
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
        alert('Failed to connect to transfer API.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
