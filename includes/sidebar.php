<?php
// includes/sidebar.php
?>
<aside class="sidebar">
    <div class="brand">
        <i class="ph ph-prescription"></i>
        <span>DEVMACY</span>
    </div>
    
    <ul class="nav-links">
        
        <?php 
            $curr_role = $_SESSION['role_name'] ?? ''; 
            $is_admin = ($curr_role === 'Admin');
        ?>

        <!-- Everyone can see the Dashboard -->
        <li>
            <a href="dashboard.php">
                <i class="ph ph-squares-four"></i>
                Dashboard
            </a>
        </li>
        
        <!-- POS accessible by Cashier, Admin, Pharmacist -->
        <li>
            <a href="pos.php">
                <i class="ph ph-cash-register"></i>
                Point of Sale
            </a>
        </li>

        <li>
            <a href="recommendations.php">
                <i class="ph ph-stethoscope"></i>
                Clinical Helper
            </a>
        </li>

        <?php if($is_admin || $curr_role === 'Pharmacist'): ?>
        <!-- Inventory management -->
        <li>
            <a href="inventory.php">
                <i class="ph ph-pill"></i>
                Inventory
            </a>
        </li>
        <?php endif; ?>

        <?php if($is_admin || $curr_role === 'Pharmacist'): ?>
        <li>
            <a href="products.php">
                <i class="ph ph-package"></i>
                Products & Pricing
            </a>
        </li>
        <?php endif; ?>

        <?php if($is_admin): ?>
        <li>
            <a href="reports.php">
                <i class="ph ph-receipt"></i>
                Reports
            </a>
        </li>
        <li>
            <a href="suppliers.php">
                <i class="ph ph-truck"></i>
                Suppliers
            </a>
        </li>
        <li>
            <a href="users.php">
                <i class="ph ph-users"></i>
                Manage Users
            </a>
        </li>
        <?php endif; ?>

        
        <li style="margin-top: auto;">
            <a href="logout.php" style="color: var(--accent-color);">
                <i class="ph ph-sign-out"></i>
                Logout
            </a>
        </li>
    </ul>
</aside>
