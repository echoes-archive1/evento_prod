<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$error = '';

// Handle role actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'];
        
        try {
            if ($action === 'create_role') {
                $role_name = Security::sanitize($_POST['role_name'] ?? '');
                $display_name = Security::sanitize($_POST['display_name'] ?? '');
                $permissions = $_POST['permissions'] ?? [];
                
                $sql = "INSERT INTO roles (role_name, display_name, permissions) VALUES (:role_name, :display_name, :permissions)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    'role_name' => $role_name,
                    'display_name' => $display_name,
                    'permissions' => json_encode($permissions)
                ]);
                
                Security::logAudit(Auth::id(), 'role_created', 'roles', $db->lastInsertId());
                $success = 'Role created successfully.';
                
            } elseif ($action === 'edit_role') {
                $role_id = (int)($_POST['role_id'] ?? 0);
                $display_name = Security::sanitize($_POST['display_name'] ?? '');
                $role_description = Security::sanitize($_POST['role_description'] ?? '');
                
                $sql = "UPDATE roles SET display_name = :display_name, role_description = :role_description WHERE id = :role_id";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    'display_name' => $display_name,
                    'role_description' => $role_description,
                    'role_id' => $role_id
                ]);
                
                Security::logAudit(Auth::id(), 'role_updated', 'roles', $role_id);
                $success = 'Role updated successfully.';
                
            } elseif ($action === 'remove_user_role') {
                $user_id = (int)($_POST['user_id'] ?? 0);
                $role_id = (int)($_POST['role_id'] ?? 0);
                
                $sql = "DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['user_id' => $user_id, 'role_id' => $role_id]);
                
                Security::logAudit(Auth::id(), 'role_removed', 'user_roles', $user_id);
                $success = 'Role removed successfully.';
            }
        } catch (Exception $e) {
            error_log("Role management error: " . $e->getMessage());
            $error = 'Operation failed. Please try again.';
        }
    }
}

// Get all roles with user counts
$roles_sql = "
    SELECT r.*, COUNT(ur.user_id) as user_count
    FROM roles r
    LEFT JOIN user_roles ur ON r.id = ur.role_id
    GROUP BY r.id
    ORDER BY r.role_name
";
$roles_stmt = $db->query($roles_sql);
$roles = $roles_stmt->fetchAll();

// Get users with their roles
$users_sql = "
    SELECT u.id, u.full_name, u.email, u.department, GROUP_CONCAT(r.role_name SEPARATOR ', ') as roles_list,
           GROUP_CONCAT(r.id SEPARATOR ',') as role_ids
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.is_active = 1
    GROUP BY u.id
    ORDER BY u.full_name
";
$users_stmt = $db->query($users_sql);
$users = $users_stmt->fetchAll();

$page_title = 'Role Management';
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

    <!-- Sidebar (same as other admin pages) -->
    <aside class="sidebar">
        <div class="sidebar-header">
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>Dashboard</span>
            </a>
            
            <a href="users.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Manage Users</span>
            </a>
            
            <a href="events.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Manage Events</span>
            </a>
            
            <a href="clubs.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span>Manage Clubs</span>
            </a>
            
            <a href="roles.php" class="nav-item active">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                <span>Role Management</span>
            </a>
            
            <a href="analytics.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>Analytics</span>
            </a>
            
            <a href="audit-logs.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Audit Logs</span>
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
        <header class="header">
            <div class="header-left">
                <h1 class="page-title">Role Management 🛡️</h1>
                <p class="page-subtitle">Manage system roles and permissions</p>
            </div>
            
            <div class="header-right">
                <button class="btn btn-primary" onclick="exportData('audit')">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export
                </button>
            </div>
        </header>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Roles Overview -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">System Roles</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Role Name</th>
                                <th>Display Name</th>
                                <th>Users Count</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($role['role_name']); ?></span></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $role['role_name']))); ?></td>
                                    <td><?php echo $role['user_count']; ?> users</td>
                                    <td><?php echo date('M d, Y', strtotime($role['created_at'])); ?></td>
                                    <td>
                                        <button onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)" class="btn-icon-small" title="Edit">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- User Roles Assignment -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">User Role Assignments</h2>
                <input type="text" class="form-control" placeholder="Search users..." onkeyup="filterTable(this.value, 'userRolesTable')" style="max-width: 300px;">
            </div>
            <div class="card-body" style="padding-top: 32px;">
                <div class="table-container">
                    <table class="data-table" id="userRolesTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Assigned Roles</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['department']); ?></td>
                                    <td>
                                        <?php if ($user['roles_list']): ?>
                                            <?php 
                                            $user_roles = explode(', ', $user['roles_list']);
                                            foreach ($user_roles as $urole):
                                            ?>
                                                <span class="badge badge-accent"><?php echo htmlspecialchars($urole); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No roles assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="openModal('assignRoleModal'); document.getElementById('assign_user_id').value=<?php echo $user['id']; ?>">
                                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            Assign Role
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Edit Role Modal -->
    <div id="editRoleModal" class="modal" style="display: none;">
        <div class="modal-content modal-premium">
            <div class="modal-header-premium">
                <div class="modal-icon-premium">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 28px; height: 28px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="modal-title-premium">Edit Role</h3>
                    <p class="modal-subtitle-premium">Update role information and permissions</p>
                </div>
                <button class="modal-close-premium" onclick="closeEditRoleModal()" type="button">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" name="role_id" id="editRoleId">
                
                <div class="modal-body-premium">
                    <div class="form-group-premium">
                        <label class="form-label-premium">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            Role Name
                        </label>
                        <div class="input-with-icon">
                            <input type="text" id="editRoleName" class="form-input-premium" readonly>
                            <span class="input-badge">System Role</span>
                        </div>
                        <span class="form-hint">This is a system-defined role and cannot be renamed</span>
                    </div>
                    
                    <div class="form-group-premium">
                        <label class="form-label-premium">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            Display Name
                            <span class="required-star">*</span>
                        </label>
                        <input type="text" name="display_name" id="editDisplayName" class="form-input-premium" required placeholder="Enter display name">
                        <span class="form-hint">This is how the role appears to users</span>
                    </div>
                    
                    <div class="form-group-premium">
                        <label class="form-label-premium">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                            </svg>
                            Description
                        </label>
                        <textarea name="role_description" id="editRoleDescription" class="form-textarea-premium" rows="4" placeholder="Describe the role's purpose and responsibilities..."></textarea>
                        <span class="form-hint">Provide a clear description of this role's purpose</span>
                    </div>
                </div>
                
                <div class="modal-footer-premium">
                    <button type="button" class="btn-premium btn-secondary-premium" onclick="closeEditRoleModal()">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </button>
                    <button type="submit" class="btn-premium btn-primary-premium">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Assign Role Modal -->
    <div id="assignRoleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Role to User</h3>
                <button class="modal-close" onclick="closeModal('assignRoleModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_user_role">
                    <input type="hidden" name="user_id" id="assign_user_id">
                    
                    <div class="form-group">
                        <label>Select Role</label>
                        <select name="role_id" class="form-control" required>
                            <option value="">Choose a role...</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $role['role_name']))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('assignRoleModal')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Assign Role
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="<?php echo BASE_URL; ?>/public/js/admin.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/toast.js?v=2"></script>
    <script>
        function editRole(role) {
            document.getElementById('editRoleId').value = role.id;
            document.getElementById('editRoleName').value = role.role_name;
            document.getElementById('editDisplayName').value = role.display_name || '';
            document.getElementById('editRoleDescription').value = role.role_description || '';
            document.getElementById('editRoleModal').style.display = 'flex';
        }
        
        function closeEditRoleModal() {
            document.getElementById('editRoleModal').style.display = 'none';
        }
        
        // Close modal on outside click
        window.addEventListener('click', function(event) {
            const editModal = document.getElementById('editRoleModal');
            if (event.target === editModal) {
                closeEditRoleModal();
            }
        });
    </script>
    
    <style>
        /* Premium Modal Styles */
        .modal-premium {
            max-width: 600px;
            background: linear-gradient(135deg, rgba(20, 20, 40, 0.98) 0%, rgba(30, 30, 50, 0.98) 100%);
            backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 24px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.8), 0 0 100px rgba(99, 102, 241, 0.15);
            animation: modalSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .modal-header-premium {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 32px 32px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, transparent 100%);
            position: relative;
        }
        
        .modal-icon-premium {
            width: 56px;
            height: 56px;
            min-width: 56px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
            animation: iconPulse 2s ease-in-out infinite;
        }
        
        @keyframes iconPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 12px 32px rgba(99, 102, 241, 0.6); }
        }
        
        .modal-title-premium {
            font-size: 24px;
            font-weight: 700;
            color: white;
            margin: 0 0 4px 0;
            letter-spacing: -0.5px;
        }
        
        .modal-subtitle-premium {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            margin: 0;
        }
        
        .modal-close-premium {
            position: absolute;
            top: 24px;
            right: 24px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .modal-close-premium:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #ef4444;
            transform: rotate(90deg);
        }
        
        .modal-close-premium svg {
            width: 20px;
            height: 20px;
        }
        
        .modal-body-premium {
            padding: 32px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-body-premium::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-body-premium::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
        
        .modal-body-premium::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.4);
            border-radius: 4px;
        }
        
        .modal-body-premium::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.6);
        }
        
        .form-group-premium {
            margin-bottom: 28px;
        }
        
        .form-group-premium:last-child {
            margin-bottom: 0;
        }
        
        .form-label-premium {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .required-star {
            color: #ef4444;
            font-size: 16px;
            margin-left: 2px;
        }
        
        .form-input-premium,
        .form-textarea-premium {
            width: 100%;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .form-input-premium:focus,
        .form-textarea-premium:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }
        
        .form-input-premium:read-only {
            background: rgba(255, 255, 255, 0.02);
            border-color: rgba(255, 255, 255, 0.05);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .form-input-premium::placeholder,
        .form-textarea-premium::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        
        .form-textarea-premium {
            resize: vertical;
            min-height: 100px;
            line-height: 1.6;
        }
        
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-badge {
            position: absolute;
            right: 14px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        
        .form-hint {
            display: block;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 8px;
            line-height: 1.5;
        }
        
        .modal-footer-premium {
            padding: 24px 32px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            background: rgba(0, 0, 0, 0.2);
        }
        
        .btn-premium {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            outline: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-premium::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-premium:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-premium svg {
            position: relative;
            z-index: 1;
        }
        
        .btn-primary-premium {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }
        
        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.6);
        }
        
        .btn-primary-premium:active {
            transform: translateY(0);
        }
        
        .btn-secondary-premium {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .btn-secondary-premium:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        @media (max-width: 640px) {
            .modal-premium {
                max-width: 95%;
                border-radius: 20px;
            }
            
            .modal-header-premium {
                padding: 24px 20px 20px;
            }
            
            .modal-body-premium {
                padding: 24px 20px;
            }
            
            .modal-footer-premium {
                padding: 20px;
                flex-direction: column;
            }
            
            .btn-premium {
                width: 100%;
                justify-content: center;
            }
            
            .modal-icon-premium {
                width: 48px;
                height: 48px;
            }
            
            .modal-title-premium {
                font-size: 20px;
            }
        }
    </style>
</body>
</html>
