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

if ($view === 'yearly_sales') {
    $years = [];
    if (isset($pdo) && $pdo) {
        try {
            $stmt = $pdo->query("SELECT DISTINCT YEAR(sale_date) as year FROM Sale ORDER BY year DESC");
            $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}
    }
    if (empty($years)) {
        $years = [date('Y')];
    }
    $selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)$years[0];
    
    // Summary metrics variables
    $yearly_sales = 0.00;
    $yearly_txns = 0;
    $yearly_units = 0;
    $yearly_refunds = 0.00;
    
    // Previous Year metrics for YoY
    $prev_year = $selected_year - 1;
    $prev_sales = 0.00;
    $prev_txns = 0;
    
    $monthly_sales = array_fill(1, 12, 0.0);
    $monthly_txns = array_fill(1, 12, 0);
    $top_rev_products = [];
    $top_qty_products = [];
    $top_symptoms = [];
    
    if (isset($pdo) && $pdo) {
        try {
            // Selected Year metrics
            $stmt = $pdo->prepare("SELECT SUM(si.unit_price * si.units_sold) as total FROM Sale_Item si JOIN Sale s ON si.sale_id = s.sale_id WHERE YEAR(s.sale_date) = :year");
            $stmt->execute(['year' => $selected_year]);
            $yearly_sales = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00);
            
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.sale_id) as cnt FROM Sale s WHERE YEAR(s.sale_date) = :year");
            $stmt->execute(['year' => $selected_year]);
            $yearly_txns = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            
            $stmt = $pdo->prepare("SELECT SUM(si.units_sold) as cnt FROM Sale_Item si JOIN Sale s ON si.sale_id = s.sale_id WHERE YEAR(s.sale_date) = :year");
            $stmt->execute(['year' => $selected_year]);
            $yearly_units = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            
            $stmt = $pdo->prepare("SELECT SUM(refund_amount) as total FROM Customer_Return WHERE YEAR(created_at) = :year");
            $stmt->execute(['year' => $selected_year]);
            $yearly_refunds = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00);
            
            // Previous Year metrics for YoY
            $stmt = $pdo->prepare("SELECT SUM(si.unit_price * si.units_sold) as total FROM Sale_Item si JOIN Sale s ON si.sale_id = s.sale_id WHERE YEAR(s.sale_date) = :year");
            $stmt->execute(['year' => $prev_year]);
            $prev_sales = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00);
            
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.sale_id) as cnt FROM Sale s WHERE YEAR(s.sale_date) = :year");
            $stmt->execute(['year' => $prev_year]);
            $prev_txns = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
            
            // Monthly Trend for chart
            $stmt = $pdo->prepare("
                SELECT MONTH(s.sale_date) as month, SUM(si.unit_price * si.units_sold) as total, COUNT(DISTINCT s.sale_id) as txns
                FROM Sale s
                LEFT JOIN Sale_Item si ON s.sale_id = si.sale_id
                WHERE YEAR(s.sale_date) = :year
                GROUP BY MONTH(s.sale_date)
                ORDER BY month ASC
            ");
            $stmt->execute(['year' => $selected_year]);
            $trend_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($trend_rows as $row) {
                $m = (int)$row['month'];
                $monthly_sales[$m] = (float)$row['total'];
                $monthly_txns[$m] = (int)$row['txns'];
            }
            
            // Top 5 Products by Revenue
            $stmt = $pdo->prepare("
                SELECT p.product_name, SUM(si.unit_price * si.units_sold) as revenue, SUM(si.units_sold) as units
                FROM Sale_Item si
                JOIN Product p ON si.product_id = p.product_id
                JOIN Sale s ON si.sale_id = s.sale_id
                WHERE YEAR(s.sale_date) = :year
                GROUP BY p.product_id
                ORDER BY revenue DESC
                LIMIT 5
            ");
            $stmt->execute(['year' => $selected_year]);
            $top_rev_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Top 5 Products by Velocity (units sold)
            $stmt = $pdo->prepare("
                SELECT p.product_name, SUM(si.units_sold) as units, SUM(si.unit_price * si.units_sold) as revenue
                FROM Sale_Item si
                JOIN Product p ON si.product_id = p.product_id
                JOIN Sale s ON si.sale_id = s.sale_id
                WHERE YEAR(s.sale_date) = :year
                GROUP BY p.product_id
                ORDER BY units DESC
                LIMIT 5
            ");
            $stmt->execute(['year' => $selected_year]);
            $top_qty_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Top 5 Symptoms Treated
            $stmt = $pdo->prepare("
                SELECT sym.symptom_name, SUM(si.units_sold) as units
                FROM Sale_Item si
                JOIN Product_Symptom ps ON si.product_id = ps.product_id
                JOIN Symptom sym ON ps.symptom_id = sym.symptom_id
                JOIN Sale s ON si.sale_id = s.sale_id
                WHERE YEAR(s.sale_date) = :year
                GROUP BY sym.symptom_id
                ORDER BY units DESC
                LIMIT 5
            ");
            $stmt->execute(['year' => $selected_year]);
            $top_symptoms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $db_error = $e->getMessage();
        }
    }
} else {
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

/* Highlight the Sum Row (Σ) */
.xcrud-wrapper table tr.xcrud-sum-row td,
.xcrud-wrapper table tfoot tr td {
    background-color: rgba(99, 102, 241, 0.15) !important;
    color: white !important;
    font-weight: 800 !important;
    border-top: 2px solid white !important;
}
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
            <a href="reports.php?view=yearly_sales" class="tab <?= ($view === 'yearly_sales') ? 'active' : '' ?>">
                <i class="ph ph-chart-line"></i> Yearly Sales
            </a>
            <a href="reports.php?view=customer_returns" class="tab <?= ($view === 'customer_returns') ? 'active' : '' ?>">
                <i class="ph ph-arrow-u-up-left"></i> Customer Returns
            </a>
            <a href="reports.php?view=supplier_returns" class="tab <?= ($view === 'supplier_returns') ? 'active' : '' ?>">
                <i class="ph ph-truck"></i> Supplier Returns
            </a>
        </div>
        
        <?php if ($view === 'yearly_sales'): ?>
            <!-- Dynamic Year Selector Dropdown -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; margin-bottom: 8px; flex-wrap: wrap; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 24px;">
                <h4 style="margin: 0; font-size: 16px; color: white;">Yearly Sales Overview for <?= htmlspecialchars($selected_year) ?></h4>
                <form method="GET" action="reports.php" style="display: flex; align-items: center; gap: 8px;">
                    <input type="hidden" name="view" value="yearly_sales">
                    <label for="year-select" style="color: var(--text-muted); font-size: 13px; font-weight: 500;">Select Year:</label>
                    <select id="year-select" name="year" onchange="this.form.submit()" style="background: var(--surface-light); color: white; border: 1px solid var(--border-color); padding: 8px 16px; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; outline: none; transition: all 0.2s;">
                        <?php foreach($years as $yr): ?>
                            <option value="<?= $yr ?>" <?= ($yr == $selected_year) ? 'selected' : '' ?>><?= $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($view === 'yearly_sales'): ?>
            <!-- Metrics Cards Grid inside glass panel -->
            <?php
                $aov = $yearly_txns > 0 ? $yearly_sales / $yearly_txns : 0.00;
                $sales_growth = 0.0;
                $txns_growth = 0.0;
                if ($prev_sales > 0) {
                    $sales_growth = (($yearly_sales - $prev_sales) / $prev_sales) * 100;
                }
                if ($prev_txns > 0) {
                    $txns_growth = (($yearly_txns - $prev_txns) / $prev_txns) * 100;
                }
            ?>
            <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 20px;">
                <!-- Total Sales -->
                <div class="metric-card" style="padding: 16px; background: rgba(0,0,0,0.15); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div class="metric-icon" style="color: #10B981; background: rgba(16, 185, 129, 0.1); width: 44px; height: 44px; font-size: 20px; border-radius: 8px;"><i class="ph ph-currency-dollar"></i></div>
                    <div>
                        <p style="color: var(--text-muted); font-size: 11px; margin-bottom: 2px;">Total Revenue</p>
                        <h3 style="font-size: 18px; font-weight: 800; color: white;">$<?= number_format($yearly_sales, 2) ?></h3>
                        <?php if ($prev_sales > 0): ?>
                            <span style="font-size: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 2px; padding: 2px 6px; border-radius: 4px; margin-top: 4px; background: <?= $sales_growth >= 0 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(244, 63, 94, 0.15)' ?>; color: <?= $sales_growth >= 0 ? '#10B981' : '#F43F5E' ?>;">
                                <i class="ph ph-arrow-<?= $sales_growth >= 0 ? 'up' : 'down' ?>"></i>
                                <?= number_format(abs($sales_growth), 1) ?>% vs <?= $prev_year ?>
                            </span>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 10px; display: block; margin-top: 4px;">No baseline (<?= $prev_year ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Transactions -->
                <div class="metric-card" style="padding: 16px; background: rgba(0,0,0,0.15); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div class="metric-icon" style="color: #6366f1; background: rgba(99, 102, 241, 0.1); width: 44px; height: 44px; font-size: 20px; border-radius: 8px;"><i class="ph ph-receipt"></i></div>
                    <div>
                        <p style="color: var(--text-muted); font-size: 11px; margin-bottom: 2px;">Transactions</p>
                        <h3 style="font-size: 18px; font-weight: 800; color: white;"><?= number_format($yearly_txns) ?></h3>
                        <?php if ($prev_txns > 0): ?>
                            <span style="font-size: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 2px; padding: 2px 6px; border-radius: 4px; margin-top: 4px; background: <?= $txns_growth >= 0 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(244, 63, 94, 0.15)' ?>; color: <?= $txns_growth >= 0 ? '#10B981' : '#F43F5E' ?>;">
                                <i class="ph ph-arrow-<?= $txns_growth >= 0 ? 'up' : 'down' ?>"></i>
                                <?= number_format(abs($txns_growth), 1) ?>% vs <?= $prev_year ?>
                            </span>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 10px; display: block; margin-top: 4px;">No baseline (<?= $prev_year ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Avg Order Value -->
                <div class="metric-card" style="padding: 16px; background: rgba(0,0,0,0.15); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div class="metric-icon" style="color: #8B5CF6; background: rgba(139, 92, 246, 0.1); width: 44px; height: 44px; font-size: 20px; border-radius: 8px;"><i class="ph ph-shopping-bag"></i></div>
                    <div>
                        <p style="color: var(--text-muted); font-size: 11px; margin-bottom: 2px;">Avg Order Value</p>
                        <h3 style="font-size: 18px; font-weight: 800; color: white;">$<?= number_format($aov, 2) ?></h3>
                        <span style="color: var(--text-muted); font-size: 10px; display: block; margin-top: 4px;">Per transaction</span>
                    </div>
                </div>

                <!-- Units Sold & Returns -->
                <div class="metric-card" style="padding: 16px; background: rgba(0,0,0,0.15); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div class="metric-icon" style="color: #F59E0B; background: rgba(245, 158, 11, 0.1); width: 44px; height: 44px; font-size: 20px; border-radius: 8px;"><i class="ph ph-pill"></i></div>
                    <div>
                        <p style="color: var(--text-muted); font-size: 11px; margin-bottom: 2px;">Volume & Returns</p>
                        <h3 style="font-size: 18px; font-weight: 800; color: white;"><?= number_format($yearly_units) ?> units</h3>
                        <span style="color: #F43F5E; font-size: 10px; display: inline-flex; align-items: center; gap: 2px; padding: 2px 6px; border-radius: 4px; margin-top: 4px; background: rgba(244, 63, 94, 0.1);">
                            <i class="ph ph-arrow-counter-clockwise"></i> -$<?= number_format($yearly_refunds, 2) ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php else: ?>
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
        <?php endif; ?>
    </div>

    <?php if ($view === 'yearly_sales'): ?>
        <!-- Interactive Chart Card -->
        <div class="glass-panel" style="padding: 24px; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                <h4 style="margin: 0; font-size: 16px; color: white; display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-chart-bar" style="color: var(--primary-color);"></i> Monthly Revenue & Transaction Trends
                </h4>
                <div style="display: flex; gap: 16px; align-items: center; font-size: 12px; color: var(--text-muted);">
                    <span style="display: inline-flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 12px; border-radius: 3px; background: rgba(99, 102, 241, 0.7); display: inline-block;"></span> Revenue ($)</span>
                    <span style="display: inline-flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 3px; background: #10B981; display: inline-block;"></span> Transactions</span>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="yearlySalesChart"></canvas>
            </div>
        </div>

        <!-- Product & Clinical Insights Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 24px;">
            <!-- Top Revenue -->
            <div class="glass-panel" style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h4 style="margin:0; font-size: 14px; color: white; display: flex; align-items: center; gap: 8px;">
                        <i class="ph ph-trend-up" style="color: #10B981;"></i> Top Revenue Generators
                    </h4>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (empty($top_rev_products)): ?>
                        <p style="color: var(--text-muted); font-size: 13px; text-align: center; padding: 20px; margin: 0;">No sales recorded in <?= $selected_year ?>.</p>
                    <?php else: ?>
                        <?php foreach($top_rev_products as $idx => $p): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); width: 16px;"><?= $idx + 1 ?></span>
                                    <span style="color: white; font-weight: 500;"><?= htmlspecialchars($p['product_name']) ?></span>
                                </div>
                                <div style="text-align: right;">
                                    <span style="font-weight: 800; color: #10B981;">$<?= number_format($p['revenue'], 2) ?></span>
                                    <span style="display: block; font-size: 10px; color: var(--text-muted);"><?= $p['units'] ?> units</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Velocity -->
            <div class="glass-panel" style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h4 style="margin:0; font-size: 14px; color: white; display: flex; align-items: center; gap: 8px;">
                        <i class="ph ph-package" style="color: #6366f1;"></i> Highest Volume Medicines
                    </h4>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (empty($top_qty_products)): ?>
                        <p style="color: var(--text-muted); font-size: 13px; text-align: center; padding: 20px; margin: 0;">No sales recorded in <?= $selected_year ?>.</p>
                    <?php else: ?>
                        <?php foreach($top_qty_products as $idx => $p): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); width: 16px;"><?= $idx + 1 ?></span>
                                    <span style="color: white; font-weight: 500;"><?= htmlspecialchars($p['product_name']) ?></span>
                                </div>
                                <div style="text-align: right;">
                                    <span style="font-weight: 800; color: #6366f1;"><?= number_format($p['units']) ?> sold</span>
                                    <span style="display: block; font-size: 10px; color: var(--text-muted);">$<?= number_format($p['revenue'], 2) ?> total</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Clinical Symptoms -->
            <div class="glass-panel" style="padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h4 style="margin:0; font-size: 14px; color: white; display: flex; align-items: center; gap: 8px;">
                        <i class="ph ph-heartbeat" style="color: #F43F5E;"></i> Clinical Needs Treated
                    </h4>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (empty($top_symptoms)): ?>
                        <p style="color: var(--text-muted); font-size: 13px; text-align: center; padding: 20px; margin: 0;">No symptoms cataloged.</p>
                    <?php else: ?>
                        <?php foreach($top_symptoms as $idx => $s): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); width: 16px;"><?= $idx + 1 ?></span>
                                    <span style="color: white; font-weight: 500;"><?= htmlspecialchars($s['symptom_name']) ?></span>
                                </div>
                                <span style="font-weight: 800; color: #F43F5E;"><?= number_format($s['units']) ?> units</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Analytics Placeholders -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div class="glass-panel" style="padding: 32px; text-align: center;">
            <i class="ph ph-calendar-warning" style="font-size: 32px; color: var(--accent-color); margin-bottom: 12px;"></i>
            <h4 style="margin-bottom: 8px;">Expiry Analytics</h4>
            <p style="color: var(--text-muted); font-size: 13px;">Automated batch alerts in next update.</p>
        </div>
        <a href="reports.php?view=yearly_sales" class="glass-panel" style="padding: 32px; text-align: center; text-decoration: none; color: inherit; border: 1px solid rgba(16, 185, 129, 0.2); transition: all 0.2s;">
            <i class="ph ph-chart-line-up" style="font-size: 32px; color: var(--secondary-color); margin-bottom: 12px;"></i>
            <h4 style="margin-bottom: 8px; color: white;">Revenue Trends</h4>
            <p style="color: var(--text-muted); font-size: 13px;">Interactive monthly charts and YoY insights.</p>
        </a>
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

<?php if ($view === 'yearly_sales'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('yearlySalesChart').getContext('2d');
    
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const salesData = <?= json_encode(array_values($monthly_sales)) ?>;
    const txnsData = <?= json_encode(array_values($monthly_txns)) ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Revenue ($)',
                    data: salesData,
                    backgroundColor: 'rgba(99, 102, 241, 0.4)',
                    borderColor: '#6366f1',
                    borderWidth: 2,
                    borderRadius: 4,
                    yAxisID: 'y'
                },
                {
                    label: 'Transactions',
                    data: txnsData,
                    type: 'line',
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#10B981',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    padding: 12
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#94a3b8' }
                },
                y: {
                    position: 'left',
                    grid: { color: 'rgba(148, 163, 184, 0.1)' },
                    ticks: {
                        color: '#94a3b8',
                        callback: (value) => '$' + value
                    }
                },
                y1: {
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: '#94a3b8' }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
