<?php
$page_title = 'System Users & Roles';
include 'includes/header.php';
require_once 'includes/db.php';

// Hard-stop: Only Admin can load this page
if (($_SESSION['role_name'] ?? '') !== 'Admin') {
    echo "<div class='page-container'><div class='glass-panel danger' style='padding:40px; text-align:center;'>
          <h3><i class='ph ph-lock-key'></i> Access Denied</h3>
          <p>You must be an Administrator to manage system users.</p>
          </div></div>";
    include 'includes/footer.php';
    exit();
}

$msg = '';

// Process Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $action = $_POST['action'] ?? '';
    
    try {
        // --- ROLE MANAGEMENT ---
        if ($action === 'add_role') {
            $rname = trim($_POST['role_name']);
            if ($rname) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO Role (role_name) VALUES (?)");
                $stmt->execute([$rname]);
                $msg = "Role '$rname' added successfully.";
            }
        } elseif ($action === 'del_role') {
            $rid = $_POST['role_id'];
            $stmt = $pdo->prepare("DELETE FROM Role WHERE role_id = ?");
            $stmt->execute([$rid]);
            $msg = "Role deleted.";
        } 
        // --- USER MANAGEMENT ---
        elseif ($action === 'add_user') {
            $u = trim($_POST['username']);
            $p = $_POST['password'];
            $f = trim($_POST['full_name']);
            $r = $_POST['role_id'];
            
            if ($u && $p && $f && $r) {
                $hash = password_hash($p, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT IGNORE INTO Users (username, password_hash, full_name, role_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$u, $hash, $f, $r]);
                $msg = "User '$u' created successfully.";
            }
        } elseif ($action === 'del_user') {
            $uid = $_POST['user_id'];
            $stmt = $pdo->prepare("DELETE FROM Users WHERE user_id = ?");
            $stmt->execute([$uid]);
            $msg = "User removed.";
        } elseif ($action === 'update_profile') {
            $uid = $_POST['user_id'];
            $f = trim($_POST['full_name']);
            $u = trim($_POST['username']);
            if ($f && $u) {
                $stmt = $pdo->prepare("UPDATE Users SET full_name = ?, username = ? WHERE user_id = ?");
                $stmt->execute([$f, $u, $uid]);
                $msg = "User profile updated successfully.";
            }
        } elseif ($action === 'update_role') {
            $uid = $_POST['user_id'];
            $r = $_POST['role_id'];
            $stmt = $pdo->prepare("UPDATE Users SET role_id = ? WHERE user_id = ?");
            $stmt->execute([$r, $uid]);
            $msg = "User role modified successfully.";
        } elseif ($action === 'reset_password') {
            $uid = $_POST['user_id'];
            $p = $_POST['password'] ?? '';
            if ($p) {
                $hash = password_hash($p, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE Users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$hash, $uid]);
                $msg = "Password forcibly reset to new value.";
            }
        }
    } catch(PDOException $e) {
        $msg = "Database Error: " . $e->getMessage();
    }
}

// Fetch Data
$roles = [];
$users = [];
$filter_role = $_GET['role'] ?? '';

if (isset($pdo)) {
    $roles = $pdo->query("SELECT * FROM Role ORDER BY role_id")->fetchAll(PDO::FETCH_ASSOC);
    
    $query = "SELECT u.*, r.role_name FROM Users u JOIN Role r ON u.role_id = r.role_id";
    $params = [];
    if ($filter_role) {
        $query .= " WHERE u.role_id = ?";
        $params[] = $filter_role;
    }
    $query .= " ORDER BY u.user_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<div class="page-container">
    
    <?php if($msg): ?>
    <div style="background: rgba(16, 185, 129, 0.1); color: var(--secondary-color); padding: 16px; border-radius: 8px; margin-bottom: 24px; border: 1px solid rgba(16, 185, 129, 0.2);">
        <i class="ph ph-check-circle" style="vertical-align: middle; margin-right: 8px;"></i> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
        
        <!-- Left: Roles Management & Add User Form -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- Roles Box -->
            <div class="glass-panel" style="padding: 24px;">
                <h3 style="margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">System Roles</h3>
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <?php foreach($roles as $r): ?>
                    <tr>
                        <td style="padding: 8px 0;"><?= htmlspecialchars($r['role_name']) ?></td>
                        <td style="padding: 8px 0; text-align: right;">
                            <?php if(!in_array($r['role_name'], ['Admin'])): // Protect system admin role ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this role?');">
                                <input type="hidden" name="action" value="del_role">
                                <input type="hidden" name="role_id" value="<?= $r['role_id'] ?>">
                                <button type="submit" style="background:none; border:none; color:var(--accent-color); cursor:pointer;"><i class="ph ph-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <form method="POST" style="display: flex; gap: 8px;">
                    <input type="hidden" name="action" value="add_role">
                    <input type="text" name="role_name" placeholder="New Role Name..." required
                           style="flex:1; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: white;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 16px;">Add</button>
                </form>
            </div>

            <!-- Add User Box -->
            <div class="glass-panel" style="padding: 24px;">
                <h3 style="margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">Add New User</h3>
                <form method="POST" style="display: flex; flex-direction: column; gap: 16px;">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div>
                        <label style="display:block; font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">Full Name</label>
                        <input type="text" name="full_name" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: white;">
                    </div>
                    
                    <div>
                        <label style="display:block; font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">Username</label>
                        <input type="text" name="username" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: white;">
                    </div>
                    
                    <div>
                        <label style="display:block; font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">Password</label>
                        <input type="password" name="password" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: white;">
                    </div>
                    
                    <div>
                        <label style="display:block; font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">Assign Role</label>
                        <select name="role_id" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: white;">
                            <?php foreach($roles as $r): ?>
                                <option value="<?= $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 8px;">Create User</button>
                </form>
            </div>
        </div>

        <!-- Right: Users Grid -->
        <div class="glass-panel" style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
                <h3 style="margin: 0;">Active Users</h3>
                
                <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                    <span style="font-size: 13px; color: var(--text-muted);"><i class="ph ph-funnel"></i> Filter:</span>
                    <select name="role" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-color); color: white; cursor: pointer;">
                        <option value="">All Roles</option>
                        <?php foreach($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>" <?= ($filter_role == $r['role_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['role_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--primary-color);">
                            <th style="padding: 12px 8px; color: var(--text-muted); font-weight: 500;">User</th>
                            <th style="padding: 12px 8px; color: var(--text-muted); font-weight: 500;">Role</th>
                            <th style="padding: 12px 8px; color: var(--text-muted); font-weight: 500; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 16px 8px;">
                                <div style="font-weight: 600;"><?= htmlspecialchars($u['full_name']) ?></div>
                                <div style="font-size: 12px; color: var(--text-muted);">@<?= htmlspecialchars($u['username']) ?></div>
                            </td>
                            <td style="padding: 16px 8px;">
                                <!-- Form to change role instantly -->
                                <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <select name="role_id" style="padding: 6px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-color); color: white;" onchange="this.form.submit()">
                                        <?php foreach($roles as $r): ?>
                                            <option value="<?= $r['role_id'] ?>" <?= ($r['role_id'] == $u['role_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($r['role_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td style="padding: 16px 8px; text-align: right;">
                                <form method="POST" style="display:inline; margin-right: 4px;" onsubmit="let f=prompt('Edit Full Name:', '<?=htmlspecialchars($u['full_name'])?>'); if(f===null) return false; let un=prompt('Edit Username:', '<?=htmlspecialchars($u['username'])?>'); if(un===null) return false; this.full_name.value=f; this.username.value=un; return true;">
                                    <input type="hidden" name="action" value="update_profile">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <input type="hidden" name="full_name" value="">
                                    <input type="hidden" name="username" value="">
                                    <button type="submit" class="btn" style="background: rgba(59, 130, 246, 0.1); color: #3B82F6; padding: 8px 12px; font-size: 12px;">Edit</button>
                                </form>
                                <form method="POST" style="display:inline; margin-right: 4px;" onsubmit="let pwd = prompt('Enter new strong password for <?=$u['username']?>:'); if(pwd) { this.password.value = pwd; return true; } return false;">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <input type="hidden" name="password" value="">
                                    <button type="submit" class="btn" style="background: rgba(16, 185, 129, 0.1); color: var(--secondary-color); padding: 8px 12px; font-size: 12px;">Reset Pwd</button>
                                </form>
                                <?php if($u['user_id'] != $_SESSION['user_id']): // Don't delete self ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this user completely?');">
                                    <input type="hidden" name="action" value="del_user">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <button type="submit" class="btn" style="background: rgba(244,63,94,0.1); color: var(--accent-color); padding: 8px 12px; font-size: 12px;">Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="font-size: 12px; color: var(--text-muted); margin-top: 16px;">
                <i class="ph ph-info"></i> Notice: Passwords are automatically secured using native PHP bcrypt standard hashes when users are created entirely independent of outside scripts.
            </p>
        </div>
        
    </div>
</div>

<?php include 'includes/footer.php'; ?>
