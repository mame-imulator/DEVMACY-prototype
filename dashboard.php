<?php
// dashboard.php
$page_title = 'Dashboard';
include 'includes/header.php';

$today_sales = 0.00;
$today_txns = 0;
$low_stock = 0;
$week_sales = 0.00;
$year_sales = 0.00;
$items_sold_today = 0;

if (isset($pdo) && $pdo) {
if (isset($pdo) && $pdo) {
    try {
        // Today's Sales
        $stmt = $pdo->query("SELECT SUM(si.unit_price * si.units_sold) as total FROM Sale_Item si JOIN Sale s ON si.sale_id = s.sale_id WHERE s.sale_date = CURDATE()");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res && $res['total']) $today_sales = $res['total'];

        // This Week's Sales
        $stmt = $pdo->query("SELECT SUM(si.unit_price * si.units_sold) as total FROM Sale_Item si JOIN Sale s ON si.sale_id = s.sale_id WHERE YEARWEEK(s.sale_date, 1) = YEARWEEK(CURDATE(), 1)");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res && $res['total']) $week_sales = $res['total'];

        // This Year's Sales
        $stmt = $pdo->query("SELECT SUM(si.unit_price * si.units_sold) as total FROM Sale_Item si JOIN Sale s ON si.sale_id = s.sale_id WHERE YEAR(s.sale_date) = YEAR(CURDATE())");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res && $res['total']) $year_sales = $res['total'];

        // Items Sold Today
        $stmt = $pdo->query("SELECT SUM(si.units_sold) as cnt FROM Sale_Item si JOIN Sale s ON si.sale_id = s.sale_id WHERE s.sale_date = CURDATE()");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res && $res['cnt']) $items_sold_today = $res['cnt'];

        // Transactions count
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM Sale WHERE sale_date = CURDATE()");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res) $today_txns = $res['cnt'];

        // Low Stock Detailed (Criteria: < 10)
        $low_stock_stmt = $pdo->query("
            SELECT p.product_name, s.quantity, us.size_description 
            FROM Stock s 
            JOIN Product p ON s.product_id = p.product_id 
            JOIN Unit_Size us ON s.unit_size_id = us.unit_size_id 
            WHERE s.quantity < 10 
            ORDER BY s.quantity ASC 
            LIMIT 5
        ");
        $low_stock_items = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);
        $low_stock = count($low_stock_items); // or get total count for the badge

        // Recent Activity (Returns with Order IDs)
        $returns_stmt = $pdo->query("
            SELECT cr.created_at, p.product_name, s.sale_id, cr.quantity
            FROM Customer_Return cr
            JOIN Sale_Item si ON cr.sale_item_id = si.sale_item_id
            JOIN Sale s ON si.sale_id = s.sale_id
            JOIN Product p ON si.product_id = p.product_id
            ORDER BY cr.created_at DESC
            LIMIT 5
        ");
        $recent_returns_detailed = $returns_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transactions count
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM Sale WHERE sale_date = CURDATE()");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res) $today_txns = $res['cnt'];

        // 7-Day Sales Trend
        $trend_stmt = $pdo->query("
            SELECT DATE_FORMAT(sale_date, '%d %b') as day, SUM(si.unit_price * si.units_sold) as total 
            FROM Sale s 
            LEFT JOIN Sale_Item si ON s.sale_id = si.sale_id 
            WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY s.sale_date
            ORDER BY s.sale_date ASC
        ");
        $sales_trend_data = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top 5 Products
        $top_stmt = $pdo->query("
            SELECT p.product_name, SUM(si.units_sold) as sold 
            FROM Sale_Item si 
            JOIN Product p ON si.product_id = p.product_id 
            GROUP BY p.product_id 
            ORDER BY sold DESC 
            LIMIT 5
        ");
        $top_products = $top_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Transactions
        $recent_stmt = $pdo->query("
            SELECT s.sale_id, s.sale_date, SUM(si.unit_price * si.units_sold) as total 
            FROM Sale s 
            LEFT JOIN Sale_Item si ON s.sale_id = si.sale_id 
            GROUP BY s.sale_id 
            ORDER BY s.sale_id DESC 
            LIMIT 5
        ");
        $recent_sales = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top Revenue Products (This Week)
        $rev_stmt = $pdo->query("
            SELECT p.product_name, SUM(si.unit_price * si.units_sold) as revenue 
            FROM Sale_Item si 
            JOIN Product p ON si.product_id = p.product_id 
            JOIN Sale s ON si.sale_id = s.sale_id
            WHERE YEARWEEK(s.sale_date, 1) = YEARWEEK(CURDATE(), 1)
            GROUP BY p.product_id 
            ORDER BY revenue DESC 
            LIMIT 5
        ");
        $top_revenue_products = $rev_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top Symptoms Treated (This Week)
        $sym_stmt = $pdo->query("
            SELECT sym.symptom_name, SUM(si.units_sold) as units 
            FROM Sale_Item si
            JOIN Product_Symptom ps ON si.product_id = ps.product_id
            JOIN Symptom sym ON ps.symptom_id = sym.symptom_id
            JOIN Sale s ON si.sale_id = s.sale_id
            WHERE YEARWEEK(s.sale_date, 1) = YEARWEEK(CURDATE(), 1)
            GROUP BY sym.symptom_id
            ORDER BY units DESC
            LIMIT 5
        ");
        $top_symptoms = $sym_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Promotion Performance
        $promo_performance = [];
        try {
            $promo_stmt = $pdo->query("
                SELECT pr.promo_name, SUM(si.units_sold) as promo_units, SUM(si.unit_price * si.units_sold) as promo_revenue,
                       DATEDIFF(pr.end_date, pr.start_date) as duration_days
                FROM Promotion pr
                JOIN Product_Unit_Price pup ON pr.barcode = pup.barcode
                JOIN Sale_Item si ON pup.product_id = si.product_id AND pup.unit_size_id = si.unit_size_id
                JOIN Sale s ON si.sale_id = s.sale_id
                WHERE s.sale_date BETWEEN pr.start_date AND pr.end_date
                GROUP BY pr.promo_id
                ORDER BY promo_revenue DESC
                LIMIT 5
            ");
            $promo_performance = $promo_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {}

    } catch(PDOException $e) {
        $db_error = $e->getMessage();
    }
}
}
?>

<div class="page-container" style="padding-bottom: 40px;">

    <!-- Quick Actions Bar -->
    <div style="display: flex; gap: 16px; margin-bottom: 32px; overflow-x: auto; padding-bottom: 8px;">
        <a href="pos.php" class="glass-panel" style="padding: 12px 20px; text-decoration: none; color: white; display: flex; align-items: center; gap: 10px; border: 1px solid var(--secondary-color);">
            <i class="ph ph-cash-register" style="font-size: 20px; color: var(--secondary-color);"></i>
            <span style="font-weight: 600;">Process New Sale</span>
        </a>
        <a href="inventory.php" class="glass-panel" style="padding: 12px 20px; text-decoration: none; color: white; display: flex; align-items: center; gap: 10px;">
            <i class="ph ph-plus-circle" style="font-size: 20px; color: var(--primary-color);"></i>
            <span style="font-weight: 600;">Update Inventory</span>
        </a>
        <a href="recommendations.php" class="glass-panel" style="padding: 12px 20px; text-decoration: none; color: white; display: flex; align-items: center; gap: 10px;">
            <i class="ph ph-stethoscope" style="font-size: 20px; color: var(--accent-color);"></i>
            <span style="font-weight: 600;">Symptom Check</span>
        </a>
        <a href="reports.php?view=customer_returns" class="glass-panel" style="padding: 12px 20px; text-decoration: none; color: white; display: flex; align-items: center; gap: 10px;">
            <i class="ph ph-arrow-u-up-left" style="font-size: 20px; color: var(--secondary-color);"></i>
            <span style="font-weight: 600;">View Returns</span>
        </a>
        <a href="reports.php" class="glass-panel" style="padding: 12px 20px; text-decoration: none; color: white; display: flex; align-items: center; gap: 10px;">
            <i class="ph ph-file-text" style="font-size: 20px; color: #94A3B8;"></i>
            <span style="font-weight: 600;">Full Reports</span>
        </a>
    </div>

    <?php
        $ret_count = 0;
        if(isset($pdo)) {
            $ret_count = $pdo->query("SELECT COUNT(*) FROM Customer_Return WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        }
    ?>

    <?php $aov = $today_txns > 0 ? $today_sales / $today_txns : 0; ?>
    <!-- Metrics Row -->
    <div class="metrics-grid" style="margin-bottom: 32px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
        <div class="metric-card glass-panel">
            <div class="metric-icon" style="color: #10B981;"><i class="ph ph-currency-dollar"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Today's Sales</p>
                <h3 style="font-size: 22px;">$<?= number_format($today_sales, 2) ?></h3>
            </div>
        </div>

        <div class="metric-card glass-panel">
            <div class="metric-icon" style="color: #6366f1;"><i class="ph ph-calendar-blank"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">This Week</p>
                <h3 style="font-size: 22px;">$<?= number_format($week_sales, 2) ?></h3>
            </div>
        </div>

        <div class="metric-card glass-panel">
            <div class="metric-icon" style="color: #F59E0B;"><i class="ph ph-calendar-check"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">This Year</p>
                <h3 style="font-size: 22px;">$<?= number_format($year_sales, 2) ?></h3>
            </div>
        </div>

        <div class="metric-card glass-panel">
            <div class="metric-icon" style="color: #8B5CF6;"><i class="ph ph-shopping-cart"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Avg Order Value</p>
                <h3 style="font-size: 22px;">$<?= number_format($aov, 2) ?></h3>
            </div>
        </div>

        <div class="metric-card glass-panel success">
            <div class="metric-icon"><i class="ph ph-trend-up"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Transactions</p>
                <h3 style="font-size: 22px;"><?= $today_txns ?></h3>
            </div>
        </div>

        <div class="metric-card glass-panel">
            <div class="metric-icon" style="color: var(--accent-color);"><i class="ph ph-arrow-u-up-left"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 4px;">Recent Returns</p>
                <h3 style="font-size: 22px;"><?= $ret_count ?> Items</h3>
            </div>
        </div>
    </div>

    <!-- Integrated Alerts Section -->
    <div style="margin-bottom: 32px;">
        <!-- Low Stock Items List (Wide) -->
        <div class="glass-panel" style="padding: 24px; border-left: 4px solid var(--accent-color);">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
                <div>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 4px;">Low Stock (Criteria: < 10 units)</p>
                    <h3 style="font-size: 24px; color: white;"><?= $low_stock ?> Items</h3>
                </div>
                <a href="inventory.php" class="btn btn-sm" style="padding: 10px 20px; font-size: 13px; background: rgba(244, 63, 94, 0.1); color: var(--accent-color); border: 2px solid var(--accent-color); border-radius: 10px; text-decoration: none; font-weight: 800; transition: all 0.2s;">
                    Manage Inventory
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                <?php if(empty($low_stock_items)): ?>
                    <p style="color: var(--text-muted); font-size: 14px; grid-column: 1/-1; padding: 20px; text-align: center;">All stock levels are optimal.</p>
                <?php else: ?>
                    <?php foreach($low_stock_items as $item): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px; background: rgba(244, 63, 94, 0.05); border-radius: 12px; border: 1px solid rgba(244, 63, 94, 0.1);">
                            <span style="font-size: 15px; font-weight: 600;"><?= htmlspecialchars($item['product_name']) ?></span>
                            <span style="background: var(--accent-color); color: white; padding: 4px 10px; border-radius: 8px; font-size: 13px; font-weight: 800; box-shadow: 0 4px 12px rgba(244, 63, 94, 0.3);">
                                <?= $item['quantity'] ?> units
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Analytics Row -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 32px; margin-bottom: 32px;">
        <!-- Sales Trend Chart -->
        <div class="glass-panel" style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-size: 18px;">Sales Performance (7 Days)</h3>
                <i class="ph ph-chart-line" style="color: var(--text-muted); font-size: 20px;"></i>
            </div>
            <canvas id="salesTrendChart" style="max-height: 280px;"></canvas>
        </div>

        <!-- Top Selling Products -->
        <div class="glass-panel" style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-size: 18px;">Top Selling Drugs</h3>
                <i class="ph ph-crown" style="color: #FBBF24; font-size: 20px;"></i>
            </div>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php if(empty($top_products)): ?>
                    <p style="color: var(--text-muted); font-size: 14px;">No sales data available.</p>
                <?php else: ?>
                    <?php foreach($top_products as $idx => $p): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 12px; font-weight: 800; color: var(--text-muted); width: 20px;"><?= $idx + 1 ?></span>
                                <span style="font-size: 14px;"><?= htmlspecialchars($p['product_name']) ?></span>
                            </div>
                            <span style="font-weight: 700; color: var(--secondary-color); font-size: 14px;"><?= $p['sold'] ?> sold</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Advanced Analytics Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 32px; margin-bottom: 32px;">
        <!-- Top Revenue Products -->
        <div class="glass-panel" style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-size: 18px;">Top Revenue Products <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">(This Week)</span></h3>
                <i class="ph ph-money" style="color: #10B981; font-size: 20px;"></i>
            </div>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php if(empty($top_revenue_products)): ?>
                    <p style="color: var(--text-muted); font-size: 14px;">No sales data available.</p>
                <?php else: ?>
                    <?php foreach($top_revenue_products as $idx => $p): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 12px; font-weight: 800; color: var(--text-muted); width: 20px;"><?= $idx + 1 ?></span>
                                <span style="font-size: 14px;"><?= htmlspecialchars($p['product_name']) ?></span>
                            </div>
                            <span style="font-weight: 700; color: #10B981; font-size: 14px;">$<?= number_format($p['revenue'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Behavior (Symptoms) -->
        <div class="glass-panel" style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-size: 18px;">Top Symptoms Treated <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">(This Week)</span></h3>
                <i class="ph ph-users" style="color: #6366f1; font-size: 20px;"></i>
            </div>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php if(empty($top_symptoms)): ?>
                    <p style="color: var(--text-muted); font-size: 14px;">No symptom data available.</p>
                <?php else: ?>
                    <?php foreach($top_symptoms as $idx => $s): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 12px; font-weight: 800; color: var(--text-muted); width: 20px;"><?= $idx + 1 ?></span>
                                <span style="font-size: 14px;"><?= htmlspecialchars($s['symptom_name']) ?></span>
                            </div>
                            <span style="font-weight: 700; color: var(--secondary-color); font-size: 14px;"><?= $s['units'] ?> units</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Promotion Performance -->
        <div class="glass-panel" style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-size: 18px;">Promo Performance</h3>
                <i class="ph ph-tag" style="color: #F59E0B; font-size: 20px;"></i>
            </div>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php if(empty($promo_performance)): ?>
                    <p style="color: var(--text-muted); font-size: 14px;">No active promo data available.</p>
                <?php else: ?>
                    <?php foreach($promo_performance as $idx => $pr): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-size: 12px; font-weight: 800; color: var(--text-muted); width: 20px;"><?= $idx + 1 ?></span>
                                <div>
                                    <span style="font-size: 14px; display: block;"><?= htmlspecialchars($pr['promo_name']) ?></span>
                                    <span style="font-size: 11px; color: var(--text-muted);"><?= $pr['duration_days'] ?> Days</span>
                                </div>
                            </div>
                            <span style="font-weight: 700; color: #10B981; font-size: 14px;">$<?= number_format($pr['promo_revenue'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Sales Table -->
    <div class="glass-panel" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="font-size: 18px;">Recent Transactions</h3>
            <a href="reports.php" style="color: var(--primary-color); font-size: 13px; font-weight: 600; text-decoration: none;">View All History</a>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 12px; text-transform: uppercase;">
                        <th style="padding: 12px 16px;">Sale ID</th>
                        <th style="padding: 12px 16px;">Timestamp</th>
                        <th style="padding: 12px 16px;">Total Amount</th>
                        <th style="padding: 12px 16px; text-align: right;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($recent_sales)): ?>
                        <tr><td colspan="4" style="padding: 40px; text-align: center; color: var(--text-muted);">No transactions recorded today.</td></tr>
                    <?php else: ?>
                        <?php foreach($recent_sales as $sale): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 16px; font-weight: 700;">#<?= str_pad($sale['sale_id'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td style="padding: 16px; color: var(--text-muted); font-size: 14px;"><?= date('d M Y, H:i', strtotime($sale['sale_date'])) ?></td>
                                <td style="padding: 16px; font-weight: 800; color: var(--secondary-color);">$<?= number_format($sale['total'], 2) ?></td>
                                <td style="padding: 16px; text-align: right;">
                                    <span style="background: rgba(16, 185, 129, 0.1); color: var(--secondary-color); padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;">Completed</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Sales Trend Chart Integration
    const ctx = document.getElementById('salesTrendChart').getContext('2d');
    
    <?php
        $days = []; $totals = [];
        foreach($sales_trend_data as $d) { $days[] = $d['day']; $totals[] = (float)$d['total']; }
        // Pad with empty data if needed
        if(count($days) < 2) { $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']; $totals = [0,0,0,0,0,0,0]; }
    ?>

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($days) ?>,
            datasets: [{
                label: 'Sales ($)',
                data: <?= json_encode($totals) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#6366f1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } },
                y: { 
                    grid: { color: 'rgba(148, 163, 184, 0.1)' },
                    ticks: { color: '#94a3b8', callback: (v) => '$' + v }
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
