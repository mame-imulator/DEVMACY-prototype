<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$is_login_page = true;
$page_title = 'Login - DEVMACY';
require_once 'includes/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password && isset($pdo)) {
        $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM Users u JOIN Role r ON u.role_id = r.role_id WHERE u.username = ? AND u.is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['full_name'] = $user['full_name'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'Invalid credentials or account disabled.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}

include 'includes/header.php';
?>

<div class="login-split">
    <!-- Visual Left Side -->
    <div class="login-left">
        <h1 style="font-size: 48px; color: white; margin-bottom: 24px; position: relative; z-index: 1;">
            Welcome to <br><span class="gradient-text">DEVMACY</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 18px; max-width: 400px; text-align: center; position: relative; z-index: 1;">
            Advanced Pharmacy Management & Point of Sale System.
        </p>
    </div>

    <!-- Logic Right Side -->
    <div class="login-right">
        <div class="login-box glass-panel">
            <div style="padding: 40px;">
                <h2 style="margin-bottom: 8px;">Sign In</h2>
                <p style="color: var(--text-muted); margin-bottom: 32px;">Please enter your credentials.</p>
                
                <?php if($error): ?>
                <div style="background: rgba(244,63,94,0.1); color: var(--accent-color); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Username</label>
                        <input type="text" name="username" required
                               style="width: 100%; padding: 12px 16px; border-radius: 8px; 
                                      border: 1px solid var(--border-color); background: var(--bg-color); 
                                      color: white; outline: none;" 
                               placeholder="admin">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Password</label>
                        <input type="password" name="password" required
                               style="width: 100%; padding: 12px 16px; border-radius: 8px; 
                                      border: 1px solid var(--border-color); background: var(--bg-color); 
                                      color: white; outline: none;" 
                               placeholder="••••••••">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 12px; width: 100%;">
                        Authorize Access
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
