<?php
// dashboard.php
$page_title = 'Dashboard';
include 'includes/header.php';

$today_sales = 0.00;
$today_txns = 0;
$low_stock = 0;

if (isset($pdo) && $pdo) {
if (isset($pdo) && $pdo) {
    try {
        // Today's Sales
        $stmt = $pdo->query("SELECT SUM(si.unit_price * si.units_sold) as total FROM Sale_Item si JOIN Sale s ON si.sale_id = s.sale_id WHERE s.sale_date = CURDATE()");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res && $res['total']) $today_sales = $res['total'];

        // Transactions count
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM Sale WHERE sale_date = CURDATE()");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res) $today_txns = $res['cnt'];

        // Low Stock count
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM Stock WHERE quantity < 10");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if($res) $low_stock = $res['cnt'];

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

    <!-- Metrics Row -->
    <div class="metrics-grid" style="margin-bottom: 32px;">
        <div class="metric-card glass-panel">
            <div class="metric-icon"><i class="ph ph-receipt"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 4px;">Today's Sales</p>
                <h3 style="font-size: 24px;">$<?= number_format($today_sales, 2) ?></h3>
            </div>
        </div>

        <div class="metric-card glass-panel">
            <div class="metric-icon" style="color: var(--accent-color);"><i class="ph ph-arrow-u-up-left"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 4px;">Recent Returns</p>
                <h3 style="font-size: 24px;"><?= $ret_count ?> Items</h3>
            </div>
        </div>
        
        <div class="metric-card glass-panel success">
            <div class="metric-icon"><i class="ph ph-trend-up"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 4px;">Transactions</p>
                <h3 style="font-size: 24px;"><?= $today_txns ?></h3>
            </div>
        </div>
        
        <div class="metric-card glass-panel danger">
            <div class="metric-icon"><i class="ph ph-warning"></i></div>
            <div>
                <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 4px;">Low Stock Alerts</p>
                <h3 style="font-size: 24px;"><?= $low_stock ?> Items</h3>
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
