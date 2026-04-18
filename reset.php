<?php
// reset.php
require_once 'includes/db.php';

try {
    // Generate a fresh, perfect bcrypt hash for the word 'admin'
    $new_hash = password_hash('admin', PASSWORD_DEFAULT);
    
    // Check if connected
    if (!$pdo) {
        die("<h3>Database connection failed. Please check includes/db.php credentials.</h3>");
    }

    // Update the master admin account in your live database
    $stmt = $pdo->prepare("UPDATE Users SET password_hash = ? WHERE username = 'admin'");
    $stmt->execute([$new_hash]);
    
    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
    echo "<h2 style='color: #10B981;'>Password Successfully Reset!</h2>";
    echo "<p>Your master admin password is now strictly set to: <b>admin</b></p>";
    echo "<p>Please delete this file and <a href='login.php'>return to Login.</a></p>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "Error resetting password: " . $e->getMessage();
}
?>
