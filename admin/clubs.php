<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$error = '';

// Handle club actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $club_id = (int)($_POST['club_id'] ?? 0);
        $action = $_POST['action'];
        
        try {
            if ($action === 'toggle_status') {
                $sql = "UPDATE clubs SET is_active = NOT is_active WHERE id = :club_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['club_id' => $club_id]);
                
                Security::logAudit(Auth::id(), 'club_status_toggle', 'clubs', $club_id);
                $success = 'Club status updated successfully.';
                
            } elseif ($action === 'edit_club') {
                $club_name = Security::sanitize($_POST['club_name'] ?? '');
                $description = Security::sanitize($_POST['description'] ?? '');
                
                $sql = "UPDATE clubs SET club_name = :club_name, description = :description WHERE id = :club_id";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    'club_name' => $club_name,
                    'description' => $description,
                    'club_id' => $club_id
                ]);
                
                Security::logAudit(Auth::id(), 'club_updated', 'clubs', $club_id);
                $success = 'Club updated successfully.';
                
            } elseif ($action === 'assign_leader') {
                $user_id = (int)($_POST['user_id'] ?? 0);
                
                // Update club
                $sql = "UPDATE clubs SET leader_id = :user_id WHERE id = :club_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['user_id' => $user_id, 'club_id' => $club_id]);
                
                // Assign club leader role if not already assigned
                $role_check_sql = "SELECT COUNT(*) as count FROM user_roles 
                                   WHERE user_id = :user_id AND role_id = (SELECT id FROM roles WHERE role_name = 'club_leader')";
                $role_check_stmt = $db->prepare($role_check_sql);
                $role_check_stmt->execute(['user_id' => $user_id]);
                $has_role = $role_check_stmt->fetch()['count'];
                
                if (!$has_role) {
                    $role_sql = "INSERT INTO user_roles (user_id, role_id, assigned_by) 
                                 SELECT :user_id, id, :admin_id FROM roles WHERE role_name = 'club_leader'";
                    $role_stmt = $db->prepare($role_sql);
                    $role_stmt->execute(['user_id' => $user_id, 'admin_id' => Auth::id()]);
                }
                
                Security::logAudit(Auth::id(), 'club_leader_assigned', 'clubs', $club_id);
                $success = 'Club leader assigned successfully.';
                
            } elseif ($action === 'delete_club') {
                $db->beginTransaction();
                
                // Delete theme assignments
                $sql = "DELETE FROM theme_assignments WHERE club_id = :club_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['club_id' => $club_id]);
                
                // Update events to remove club association
                $sql = "UPDATE events SET club_id = NULL WHERE club_id = :club_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['club_id' => $club_id]);
                
                // Delete club
                $sql = "DELETE FROM clubs WHERE id = :club_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['club_id' => $club_id]);
                
                $db->commit();
                
                Security::logAudit(Auth::id(), 'club_deleted', 'clubs', $club_id);
                $success = 'Club deleted successfully.';
                
            } elseif ($action === 'create_club') {
                $club_name = Security::sanitize($_POST['club_name'] ?? '');
                $description = Security::sanitize($_POST['description'] ?? '');
                
                $sql = "INSERT INTO clubs (club_name, description, is_active) VALUES (:club_name, :description, 1)";
                $stmt = $db->prepare($sql);
                $stmt->execute(['club_name' => $club_name, 'description' => $description]);
                
                $new_club_id = $db->lastInsertId();
                
                Security::logAudit(Auth::id(), 'club_created', 'clubs', $new_club_id);
                $success = 'Club created successfully.';
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Club management error: " . $e->getMessage());
            $error = 'Operation failed. Please try again.';
        }
    }
}

// Get clubs with leader info and event counts
$clubs_sql = "
    SELECT c.id, c.club_name, c.is_active, c.leader_id, c.created_at,
           u.full_name as leader_name,
           u.email as leader_email,
           (SELECT COUNT(*) FROM events WHERE club_id = c.id) as total_events,
           (SELECT COUNT(*) FROM events WHERE club_id = c.id AND status = 'approved') as approved_events,
           (SELECT SUM(current_participants) FROM events WHERE club_id = c.id) as total_members
    FROM clubs c
    LEFT JOIN users u ON c.leader_id = u.id
    ORDER BY c.club_name
";
$clubs_stmt = $db->query($clubs_sql);
$clubs = $clubs_stmt->fetchAll();

// Get potential club leaders (students)
$leaders_sql = "
    SELECT DISTINCT u.id, u.full_name, u.email, u.roll_number
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    WHERE r.role_name = 'student' AND u.is_active = 1
    ORDER BY u.full_name
";
$leaders_stmt = $db->query($leaders_sql);
$potential_leaders = $leaders_stmt->fetchAll();

$csrf_token = generateCSRFToken();
$page_title = 'Club Management';
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
            
            <a href="users.php" class="nav-item">
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
            
            <a href="clubs.php" class="nav-item active">
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
                <p class="page-subtitle">Manage college clubs</p>
            </div>
            
            <div class="header-right">
                <button onclick="openCreateModal()" class="btn btn-primary" style="margin-right: 1rem;">
                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create Club
                </button>
                
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

        <!-- Clubs Grid -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">All Clubs (<?php echo count($clubs); ?>)</h2>
            </div>
            
            <div class="clubs-grid">
                <?php if (empty($clubs)): ?>
                    <div class="empty-state">
                        <p>No clubs created yet. <a href="#" onclick="openCreateModal()">Create your first club</a></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($clubs as $club): ?>
                        <div class="club-card <?php echo $club['is_active'] ? '' : 'club-inactive'; ?>">
                            <div class="club-card-header" onclick="window.location.href='view-club.php?id=<?php echo $club['id']; ?>'" style="cursor: pointer;">
                                <?php if (!empty($club['logo_url'])): ?>
                                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($club['logo_url']); ?>" alt="Logo" class="club-card-logo">
                                <?php else: ?>
                                    <div class="club-card-logo-placeholder">
                                        <?php echo strtoupper(substr($club['club_name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <span class="badge <?php echo $club['is_active'] ? 'badge-success' : 'badge-error'; ?>">
                                    <?php echo $club['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <h3 class="club-card-title" onclick="window.location.href='view-club.php?id=<?php echo $club['id']; ?>'" style="cursor: pointer;"><?php echo htmlspecialchars($club['club_name']); ?></h3>
                            <p class="club-card-description" onclick="window.location.href='view-club.php?id=<?php echo $club['id']; ?>'" style="cursor: pointer;">
                                <?php 
                                $desc = $club['description'] ?? 'No description available';
                                echo htmlspecialchars(strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc); 
                                ?>
                            </p>
                            
                            <div class="club-card-stats" onclick="window.location.href='view-club.php?id=<?php echo $club['id']; ?>'" style="cursor: pointer;">
                                <div class="club-stat">
                                    <span class="club-stat-value"><?php echo $club['total_events'] ?? 0; ?></span>
                                    <span class="club-stat-label">Events</span>
                                </div>
                                <div class="club-stat">
                                    <span class="club-stat-value"><?php echo $club['approved_events'] ?? 0; ?></span>
                                    <span class="club-stat-label">Approved</span>
                                </div>
                                <div class="club-stat">
                                    <span class="club-stat-value"><?php echo $club['total_members'] ?? 0; ?></span>
                                    <span class="club-stat-label">Members</span>
                                </div>
                            </div>
                            
                            <div class="club-card-leader" onclick="window.location.href='view-club.php?id=<?php echo $club['id']; ?>'" style="cursor: pointer;">
                                <svg class="club-leader-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <div>
                                    <div class="club-leader-name"><?php echo htmlspecialchars($club['leader_name'] ?? 'No Leader'); ?></div>
                                    <div class="club-leader-email"><?php echo htmlspecialchars($club['leader_email'] ?? 'Not assigned'); ?></div>
                                </div>
                            </div>
                            
                            <div class="club-card-actions">
                                <button onclick="event.stopPropagation(); editClub(<?php echo htmlspecialchars(json_encode($club)); ?>)" class="btn btn-secondary btn-sm">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Edit
                                </button>
                                
                                <button onclick="event.stopPropagation(); assignLeader(<?php echo $club['id']; ?>, '<?php echo htmlspecialchars($club['club_name']); ?>')" class="btn btn-secondary btn-sm">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                    </svg>
                                    Assign Leader
                                </button>
                                
                                <form method="POST" style="display: inline;" onsubmit="event.stopPropagation(); return confirm('Toggle club status?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <button type="submit" class="btn btn-secondary btn-sm" onclick="event.stopPropagation();">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                        </svg>
                                        <?php echo $club['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;" onsubmit="event.stopPropagation(); return confirm('Delete this club? Events will be unlinked.')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                    <input type="hidden" name="action" value="delete_club">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="event.stopPropagation();">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 16px; height: 16px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Create Club Modal -->
    <div id="createModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Create New Club</h3>
            <form method="POST" id="createForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="create_club">
                
                <div class="form-group">
                    <label>Club Name *</label>
                    <input type="text" name="club_name" required class="filter-input" placeholder="e.g., Tech Club">
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="4" required class="filter-input" placeholder="Describe the club's purpose and activities..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeCreateModal()" class="btn btn-secondary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create Club
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Club Modal -->
    <div id="editClubModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Edit Club</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="club_id" id="editClubId">
                <input type="hidden" name="action" value="edit_club">
                
                <div class="form-group">
                    <label>Club Name *</label>
                    <input type="text" name="club_name" id="editClubName" required class="filter-input" placeholder="Enter club name">
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" id="editClubDescription" rows="4" required class="filter-input" placeholder="Describe the club's purpose and activities"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeEditClubModal()" class="btn btn-secondary">
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

    <!-- Assign Leader Modal -->
    <div id="leaderModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3 id="leaderModalTitle">Assign Leader</h3>
            <form method="POST" id="leaderForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="club_id" id="leaderClubId">
                <input type="hidden" name="action" value="assign_leader">
                
                <div class="form-group">
                    <label>Search Student *</label>
                    <input type="text" id="leaderSearch" placeholder="Search by name or roll number..." 
                           class="filter-input" style="margin-bottom: 10px;">
                    <select name="user_id" id="leaderSelect" required class="filter-select" size="8" 
                            style="min-height: 200px; width: 100%;">
                        <option value="">Choose a student...</option>
                        <?php foreach ($potential_leaders as $leader): ?>
                            <option value="<?php echo $leader['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars(strtolower($leader['full_name'])); ?>" 
                                    data-roll="<?php echo htmlspecialchars(strtolower($leader['roll_number'])); ?>">
                                <?php echo htmlspecialchars($leader['full_name']); ?> (<?php echo htmlspecialchars($leader['roll_number']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeLeaderModal()" class="btn btn-secondary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Assign Leader
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/admin.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/toast.js?v=2"></script>
    <script>
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'flex';
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }
        
        function editClub(club) {
            document.getElementById('editClubId').value = club.id;
            document.getElementById('editClubName').value = club.club_name;
            document.getElementById('editClubDescription').value = club.description || '';
            document.getElementById('editClubModal').style.display = 'flex';
        }
        
        function closeEditClubModal() {
            document.getElementById('editClubModal').style.display = 'none';
        }
        
        function assignLeader(clubId, clubName) {
            document.getElementById('leaderClubId').value = clubId;
            document.getElementById('leaderModalTitle').textContent = 'Assign Leader to ' + clubName;
            document.getElementById('leaderModal').style.display = 'flex';
            document.getElementById('leaderSearch').value = '';
            filterLeaders(); // Reset filter
            document.getElementById('leaderSearch').focus();
        }
        
        function closeLeaderModal() {
            document.getElementById('leaderModal').style.display = 'none';
        }
        
        // Filter leaders based on search input
        function filterLeaders() {
            const searchInput = document.getElementById('leaderSearch');
            const select = document.getElementById('leaderSelect');
            const filter = searchInput.value.toLowerCase();
            const options = select.options;
            
            for (let i = 1; i < options.length; i++) { // Start at 1 to skip "Choose a student..."
                const option = options[i];
                const name = option.getAttribute('data-name') || '';
                const roll = option.getAttribute('data-roll') || '';
                
                if (name.includes(filter) || roll.includes(filter)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
            
            // Auto-select first visible option if search has results
            if (filter) {
                for (let i = 1; i < options.length; i++) {
                    if (options[i].style.display !== 'none') {
                        select.selectedIndex = i;
                        break;
                    }
                }
            }
        }
        
        // Add event listener for search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('leaderSearch');
            if (searchInput) {
                searchInput.addEventListener('input', filterLeaders);
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        document.getElementById('leaderSelect').focus();
                    }
                });
            }
        });
        
        // Close modal on outside click
        window.onclick = function(event) {
            const createModal = document.getElementById('createModal');
            const leaderModal = document.getElementById('leaderModal');
            const editModal = document.getElementById('editClubModal');
            if (event.target === createModal) {
                closeCreateModal();
            }
            if (event.target === leaderModal) {
                closeLeaderModal();
            }
            if (event.target === editModal) {
                closeEditClubModal();
            }
        }
    </script>
    
    <style>
        .clubs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }
        
        .club-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        
        .club-card:hover {
            transform: translateY(-4px);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.1);
        }
        
        .club-inactive {
            opacity: 0.6;
        }
        
        .club-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .club-card-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
        }
        
        .club-card-logo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        
        .club-card-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 12px 0;
            color: white;
        }
        
        .club-card-description {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .club-card-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
            padding: 16px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .club-stat {
            text-align: center;
        }
        
        .club-stat-value {
            display: block;
            font-size: 24px;
            font-weight: 700;
            color: #6366f1;
        }
        
        .club-stat-label {
            display: block;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 4px;
        }
        
        .club-card-leader {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .club-leader-icon {
            width: 40px;
            height: 40px;
            stroke: rgba(255, 255, 255, 0.5);
        }
        
        .club-leader-name {
            font-weight: 600;
            color: white;
        }
        
        .club-leader-email {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .club-card-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
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
        .form-group textarea,
        .form-group select {
            width: 100%;
        }
        
        .form-group textarea {
            resize: vertical;
        }
        
        .filter-input, .filter-select {
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        @media (max-width: 768px) {
            .clubs-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
