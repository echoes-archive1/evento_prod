<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$user = Auth::user();
$db = Database::getInstance()->getConnection();
$success = '';
$error = '';

// Get roles and departments for dropdowns
$roles_sql = "SELECT id, role_name FROM roles ORDER BY role_name";
$roles = $db->query($roles_sql)->fetchAll();

$departments = ['Computer Science', 'Electronics', 'Mechanical', 'Civil', 'IT', 'All Departments'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $full_name = Security::sanitize($_POST['full_name'] ?? '');
        $roll_number = Security::sanitize($_POST['roll_number'] ?? '');
        $email = Security::sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $department = Security::sanitize($_POST['department'] ?? '');
        $year = intval($_POST['year'] ?? 1);
        $phone = Security::sanitize($_POST['phone'] ?? '');
        $selected_roles = $_POST['roles'] ?? [];
        
        // Validate
        if (empty($full_name) || empty($email) || empty($password)) {
            $error = 'Please fill all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check if email exists
            $check_sql = "SELECT COUNT(*) as count FROM users WHERE email = :email";
            $check_stmt = $db->prepare($check_sql);
            $check_stmt->execute(['email' => $email]);
            
            if ($check_stmt->fetch()['count'] > 0) {
                $error = 'Email already exists.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Hash password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user
                    $insert_sql = "INSERT INTO users (full_name, roll_number, email, password, department, year, phone, is_active, email_verified, email_verified_at) 
                                   VALUES (:full_name, :roll_number, :email, :password, :department, :year, :phone, 1, 1, NOW())";
                    $stmt = $db->prepare($insert_sql);
                    $stmt->execute([
                        'full_name' => $full_name,
                        'roll_number' => $roll_number,
                        'email' => $email,
                        'password' => $password_hash,
                        'department' => $department,
                        'year' => $year,
                        'phone' => $phone
                    ]);
                    
                    $user_id = $db->lastInsertId();
                    
                    // Assign roles
                    if (!empty($selected_roles)) {
                        $role_sql = "INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (:user_id, :role_id, :admin_id)";
                        $role_stmt = $db->prepare($role_sql);
                        
                        foreach ($selected_roles as $role_id) {
                            $role_stmt->execute([
                                'user_id' => $user_id,
                                'role_id' => intval($role_id),
                                'admin_id' => Auth::id()
                            ]);
                        }
                    }
                    
                    $db->commit();
                    
                    Security::logAudit(Auth::id(), 'user_created', 'users', $user_id);
                    $success = 'User created successfully!';
                    
                    // Clear form
                    $_POST = [];
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Failed to create user: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="public-navbar">
        <div class="navbar-brand">🎓 Evento</div>
        <div class="navbar-actions" style="display: flex; gap: clamp(8px, 1.5vw, 16px); align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.75rem; color: white;">
                <span style="color: rgba(255, 255, 255, 0.9); font-size: clamp(0.85rem, 1.5vw, 1rem); font-weight: 500;"><?php $currentUser = Auth::user(); echo htmlspecialchars($currentUser['full_name']); ?></span>
                <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #60a5fa, #a78bfa); display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 700; color: white;">
                    <?php 
                    $nameParts = explode(' ', $currentUser['full_name']);
                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                    if (count($nameParts) > 1) {
                        $initials .= strtoupper(substr(end($nameParts), 0, 1));
                    }
                    echo $initials;
                    ?>
                </div>
            </div>
        </div>
    </nav>

    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div class="header-left">
                <h1 class="page-title">Create New User</h1>
                <p class="page-subtitle">Add a new user to the system</p>
            </div>
            
            <div class="header-right">
                <a href="users.php" class="btn btn-secondary" style="margin-right: 1rem;">Back to Users</a>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                        $nameParts = explode(' ', $user['full_name']);
                        $initials = strtoupper(substr($nameParts[0], 0, 1));
                        if (count($nameParts) > 1) {
                            $initials .= strtoupper(substr(end($nameParts), 0, 1));
                        }
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo Security::sanitize($user['full_name']); ?></div>
                        <div class="user-role"><?php echo ucfirst($user['roles']); ?></div>
                    </div>
                </div>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="glass-card">
                <form method="POST" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group col-span-2">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-input" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Roll Number *</label>
                            <input type="text" name="roll_number" class="form-input" required value="<?php echo htmlspecialchars($_POST['roll_number'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-input" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-input" required minlength="6">
                            <small class="form-hint">Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Department *</label>
                            <select name="department" class="form-input" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>" <?php echo (($_POST['department'] ?? '') === $dept) ? 'selected' : ''; ?>>
                                        <?php echo $dept; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Year *</label>
                            <select name="year" class="form-input" required>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (($_POST['year'] ?? 1) == $i) ? 'selected' : ''; ?>>Year <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-span-2">
                            <label class="form-label">Assign Roles</label>
                            <div class="checkbox-group">
                                <?php foreach ($roles as $role): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>" 
                                               <?php echo in_array($role['id'], $_POST['roles'] ?? []) ? 'checked' : ''; ?>>
                                        <span><?php echo ucfirst(str_replace('_', ' ', $role['role_name'])); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create User</button>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/admin.js"></script>
</body>
</html>
