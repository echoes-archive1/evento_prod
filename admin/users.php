<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$error = '';

// Handle user status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $action = $_POST['action'];
        
        try {
            if ($action === 'edit_user') {
                $full_name = Security::sanitize($_POST['full_name'] ?? '');
                $email = Security::sanitize($_POST['email'] ?? '');
                $department = Security::sanitize($_POST['department'] ?? '');
                $roll_number = Security::sanitize($_POST['roll_number'] ?? '');
                
                // Validate email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Invalid email address.';
                } else {
                    // Check if email is already used by another user
                    $email_check_sql = "SELECT COUNT(*) as count FROM users WHERE email = :email AND id != :user_id";
                    $email_check_stmt = $db->prepare($email_check_sql);
                    $email_check_stmt->execute(['email' => $email, 'user_id' => $user_id]);
                    
                    if ($email_check_stmt->fetch()['count'] > 0) {
                        $error = 'Email already exists for another user.';
                    } else {
                        $sql = "UPDATE users SET full_name = :full_name, email = :email, department = :department, roll_number = :roll_number WHERE id = :user_id";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            'full_name' => $full_name,
                            'email' => $email,
                            'department' => $department,
                            'roll_number' => $roll_number,
                            'user_id' => $user_id
                        ]);
                        
                        Security::logAudit(Auth::id(), 'user_updated', 'users', $user_id);
                        $success = 'User updated successfully.';
                    }
                }
                
            } elseif ($action === 'toggle_status') {
                $sql = "UPDATE users SET is_active = NOT is_active WHERE id = :user_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['user_id' => $user_id]);
                
                Security::logAudit(Auth::id(), 'user_status_toggle', 'users', $user_id);
                $success = 'User status updated successfully.';
                
            } elseif ($action === 'verify_email') {
                $sql = "UPDATE users SET email_verified = 1, email_verified_at = NOW() WHERE id = :user_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['user_id' => $user_id]);
                
                Security::logAudit(Auth::id(), 'email_verified', 'users', $user_id);
                $success = 'Email verified successfully.';
                
            } elseif ($action === 'delete_user') {
                // Don't delete admin or self
                if ($user_id == 1 || $user_id == Auth::id()) {
                    $error = 'Cannot delete this user.';
                } else {
                    $db->beginTransaction();
                    
                    // Delete user roles
                    $sql = "DELETE FROM user_roles WHERE user_id = :user_id";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(['user_id' => $user_id]);
                    
                    // Delete user
                    $sql = "DELETE FROM users WHERE id = :user_id";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(['user_id' => $user_id]);
                    
                    $db->commit();
                    
                    Security::logAudit(Auth::id(), 'user_deleted', 'users', $user_id);
                    $success = 'User deleted successfully.';
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("User management error: " . $e->getMessage());
            $error = 'Operation failed. Please try again.';
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE :search OR u.email LIKE :search OR u.roll_number LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.is_active = :status";
    $params['status'] = $status_filter === 'active' ? 1 : 0;
}

if (!empty($department_filter)) {
    $where_conditions[] = "u.department = :department";
    $params['department'] = $department_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with roles
$users_sql = "
    SELECT u.*, 
           GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name) as roles,
           GROUP_CONCAT(DISTINCT r.id ORDER BY r.role_name) as role_ids
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
";
$users_stmt = $db->prepare($users_sql);
$users_stmt->execute($params);
$users = $users_stmt->fetchAll();

// If role filter is applied, filter the results
if (!empty($role_filter)) {
    $users = array_filter($users, function($user) use ($role_filter) {
        $roles = explode(',', strtolower($user['roles'] ?? ''));
        return in_array(strtolower($role_filter), $roles);
    });
}

// Get all roles for assignment
$roles_sql = "SELECT id, role_name, role_description FROM roles ORDER BY role_name";
$roles_stmt = $db->query($roles_sql);
$all_roles = $roles_stmt->fetchAll();

// Get departments
$departments = ['Computer Science', 'Information Technology', 'Electronics', 'Mechanical', 'Civil', 'Electrical'];

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
        SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_users,
        SUM(CASE WHEN email_verified = 0 THEN 1 ELSE 0 END) as unverified_users,
        COUNT(DISTINCT CASE WHEN year IN ('1','2','3','4') THEN id END) as students_count,
        COUNT(DISTINCT CASE WHEN year = 'Faculty' THEN id END) as faculty_count
    FROM users
";
$stats_stmt = $db->query($stats_sql);
$stats = $stats_stmt->fetch();

$csrf_token = generateCSRFToken();
$page_title = 'User Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/alerts.css">
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

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>
            
            <a href="users.php" class="nav-item active">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Users</span>
            </a>
            
            <a href="events.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Events</span>
            </a>
            
            <a href="clubs.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span>Clubs</span>
            </a>
            
            <a href="analytics.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>Analytics</span>
            </a>
            
            <a href="settings.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span>Settings</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <a href="<?php echo BASE_URL; ?>/logout.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <h1 class="page-title"><?php echo $page_title; ?></h1>
                <p class="page-subtitle">Manage system users</p>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                        $user = Auth::user();
                        $nameParts = explode(' ', $user['full_name']);
                        $initials = strtoupper(substr($nameParts[0], 0, 1));
                        if (count($nameParts) > 1) {
                            $initials .= strtoupper(substr(end($nameParts), 0, 1));
                        }
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="user-role">Admin</div>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['active_users']; ?></div>
                    <div class="stat-label">Active Users</div>
                    <div class="stat-subtext"><?php echo $stats['inactive_users']; ?> Inactive</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['students_count']; ?></div>
                    <div class="stat-label">Students</div>
                    <div class="stat-subtext"><?php echo $stats['faculty_count']; ?> Faculty</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['verified_users']; ?></div>
                    <div class="stat-label">Verified Emails</div>
                    <div class="stat-subtext"><?php echo $stats['unverified_users']; ?> Unverified</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="content-section">
            <div class="filters-container">
                <div class="filters-left">
                    <a href="create-user.php" class="btn btn-primary">
                        <svg style="width: 18px; height: 18px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create User
                    </a>
                </div>
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Search by name, email, or roll number..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <select name="role" class="filter-select">
                            <option value="">All Roles</option>
                            <?php foreach ($all_roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role['role_name']); ?>" 
                                        <?php echo $role_filter === $role['role_name'] ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $role['role_name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="department" class="filter-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                        <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Filter
                    </button>
                    
                    <a href="users.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">All Users (<?php echo count($users); ?>)</h2>
                <a href="export-users.php" class="btn btn-secondary">
                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export CSV
                </a>
            </div>
            
            <div class="table-container" style="overflow-x: auto;">
                <table class="data-table" style="min-width: 1200px;">
                    <thead>
                        <tr>
                            <th style="width: 200px; min-width: 200px;">User</th>
                            <th style="width: 250px; min-width: 220px;">Email</th>
                            <th style="width: 200px; min-width: 180px;">Department</th>
                            <th style="width: 100px; min-width: 90px;">Year</th>
                            <th style="width: 150px; min-width: 130px;">Roles</th>
                            <th style="width: 100px; min-width: 100px;">Status</th>
                            <th style="width: 120px; min-width: 120px;">Joined</th>
                            <th style="width: 150px; min-width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td style="min-width: 200px;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 40px; height: 40px; min-width: 40px; border-radius: 10px; background: linear-gradient(135deg, #60a5fa, #a78bfa); display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 700; color: white; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);">
                                                <?php 
                                                $nameParts = explode(' ', $user['full_name'] ?? 'U');
                                                $initials = strtoupper(substr($nameParts[0], 0, 1));
                                                if (count($nameParts) > 1) {
                                                    $initials .= strtoupper(substr(end($nameParts), 0, 1));
                                                }
                                                echo $initials;
                                                ?>
                                            </div>
                                            <div>
                                                <div class="table-cell-main"><?php echo htmlspecialchars($user['full_name'] ?? 'No Name'); ?></div>
                                                <div class="table-cell-sub"><?php echo htmlspecialchars($user['roll_number'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="max-width: 250px;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; overflow: hidden;">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                            <?php if (!$user['email_verified']): ?>
                                                <span class="badge badge-warning" style="font-size: 0.65rem; padding: 0.25rem 0.5rem;">
                                                    <svg style="width: 10px; height: 10px; margin-right: 2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                    </svg>
                                                    Unverified
                                                </span>
                                            <?php else: ?>
                                                <svg style="width: 16px; height: 16px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="max-width: 200px;">
                                        <div class="table-cell-main" style="font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($user['year']) && $user['year'] !== 'Faculty') {
                                            $yearLabels = ['1' => 'Year 1', '2' => 'Year 2', '3' => 'Year 3', '4' => 'Year 4'];
                                            echo '<span class="badge badge-primary" style="white-space: nowrap;">' . ($yearLabels[$user['year']] ?? 'Year ' . $user['year']) . '</span>';
                                        } elseif ($user['year'] === 'Faculty') {
                                            echo '<span class="badge badge-info" style="white-space: nowrap;">Faculty</span>';
                                        } else {
                                            echo '<span style="color: rgba(255,255,255,0.5);">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="roles-container">
                                            <?php 
                                            $roles = explode(',', $user['roles'] ?? '');
                                            foreach ($roles as $role): 
                                                if (!empty(trim($role))):
                                            ?>
                                                <span class="badge badge-info"><?php echo ucwords(str_replace('_', ' ', trim($role))); ?></span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-error'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons-small">
                                            <button onclick="viewUser(<?php echo $user['id']; ?>)" class="btn-icon-small" title="View">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                            
                                            <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="btn-icon-small" title="Edit">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                            
                                            <?php if ($user['id'] != 1 && $user['id'] != Auth::id()): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle user status?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <button type="submit" class="btn-icon-small" title="Toggle Status">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <button type="submit" class="btn-icon-small btn-icon-danger" title="Delete">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Edit User</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="user_id" id="editUserId">
                <input type="hidden" name="action" value="edit_user">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" id="editFullName" required class="filter-input">
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="editEmail" required class="filter-input">
                </div>
                
                <div class="form-group">
                    <label>Roll Number *</label>
                    <input type="text" name="roll_number" id="editRollNumber" required class="filter-input">
                </div>
                
                <div class="form-group">
                    <label>Department *</label>
                    <select name="department" id="editDepartment" required class="filter-select">
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/admin.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/toast.js?v=2"></script>
    <script>
        function viewUser(userId) {
            window.location.href = 'view-user.php?id=' + userId;
        }
        
        function editUser(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editFullName').value = user.full_name;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editRollNumber').value = user.roll_number;
            document.getElementById('editDepartment').value = user.department;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
        
        // Enhanced table interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats cards on load
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        card.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
            
            // Add smooth scroll to top button
            let scrollTimeout;
            window.addEventListener('scroll', function() {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    if (window.scrollY > 300) {
                        if (!document.getElementById('scrollTopBtn')) {
                            const btn = document.createElement('button');
                            btn.id = 'scrollTopBtn';
                            btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 20px; height: 20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>';
                            btn.style.cssText = 'position: fixed; bottom: 2rem; right: 2rem; width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; color: white; cursor: pointer; box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4); z-index: 1000; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;';
                            btn.onclick = function() {
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            };
                            btn.onmouseenter = function() {
                                this.style.transform = 'translateY(-4px)';
                                this.style.boxShadow = '0 12px 32px rgba(99, 102, 241, 0.5)';
                            };
                            btn.onmouseleave = function() {
                                this.style.transform = 'translateY(0)';
                                this.style.boxShadow = '0 8px 24px rgba(99, 102, 241, 0.4)';
                            };
                            document.body.appendChild(btn);
                        }
                    } else {
                        const btn = document.getElementById('scrollTopBtn');
                        if (btn) btn.remove();
                    }
                }, 100);
            });
            
            // Real-time search filter
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const searchTerm = this.value.toLowerCase();
                    
                    searchTimeout = setTimeout(() => {
                        const rows = document.querySelectorAll('.data-table tbody tr');
                        rows.forEach(row => {
                            const text = row.textContent.toLowerCase();
                            if (text.includes(searchTerm)) {
                                row.style.display = '';
                                row.style.animation = 'fadeIn 0.3s ease';
                            } else {
                                row.style.opacity = '0';
                                setTimeout(() => {
                                    if (!text.includes(searchInput.value.toLowerCase())) {
                                        row.style.display = 'none';
                                    }
                                }, 200);
                            }
                        });
                    }, 300);
                });
            }
        });
        
        // Add fadeIn animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        `;
        document.head.appendChild(style);
    </script>
    
    <style>
        .filters-container {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 24px;
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
            gap: 16px;
            align-items: center;
        }
        
        .filter-input, .filter-select {
            width: 100%;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }
        
        .filter-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .btn-icon-danger {
            color: #ef4444;
        }
        
        .btn-icon-danger:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: rgba(20, 20, 40, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 24px;
            color: white;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
        }
        
        @media (max-width: 1200px) {
            .filters-form {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
