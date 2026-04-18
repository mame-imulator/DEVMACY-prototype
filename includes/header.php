<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Connect to Database
require_once dirname(__DIR__) . '/includes/db.php';

// Native Auth Lock
if (!isset($is_login_page) || !$is_login_page) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

$page_title = $page_title ?? 'DEVMACY - Pharmacy POS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    
    <!-- Icons (Phosphor Icons or FontAwesome placeholder) -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Placeholder for XCRUD CSS Headers -->
    <!-- <?php // echo Xcrud::load_css(); ?> -->
</head>
<body>
    <?php if(!isset($is_login_page)): ?>
    <!-- Application Wrapper -->
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <header class="top-header">
            <h2 class="gradient-text"><?= htmlspecialchars($page_title) ?></h2>
            <div class="user-profile">
                <!-- Username from Session -->
                <span>Hi, Admin</span> 
                <div class="avatar">AD</div>
            </div>
        </header>
    <?php endif; ?>
