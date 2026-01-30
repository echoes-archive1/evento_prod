<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: users.php');
    exit;
}

// Get user details with roles
$user_sql = "
    SELECT u.*, 
           GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name) as roles
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.id = :user_id
    GROUP BY u.id
";
$user_stmt = $db->prepare($user_sql);
$user_stmt->execute(['user_id' => $user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Get user's event registrations
$registrations_sql = "
    SELECT e.*, c.club_name, er.registration_date
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    LEFT JOIN clubs c ON e.club_id = c.id
    WHERE er.user_id = :user_id
    ORDER BY er.registration_date DESC
";
$registrations_stmt = $db->prepare($registrations_sql);
$registrations_stmt->execute(['user_id' => $user_id]);
$registrations = $registrations_stmt->fetchAll();

// Get user's created events (if faculty/club leader)
$created_events_sql = "
    SELECT e.*, c.club_name, 
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as total_registrations
    FROM events e
    LEFT JOIN clubs c ON e.club_id = c.id
    WHERE e.created_by = :user_id
    ORDER BY e.created_at DESC
";
$created_events_stmt = $db->prepare($created_events_sql);
$created_events_stmt->execute(['user_id' => $user_id]);
$created_events = $created_events_stmt->fetchAll();

// Get clubs if club leader
$clubs_sql = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM events WHERE club_id = c.id) as total_events
    FROM clubs c
    WHERE c.leader_id = :user_id
";
$clubs_stmt = $db->prepare($clubs_sql);
$clubs_stmt->execute(['user_id' => $user_id]);
$clubs = $clubs_stmt->fetchAll();

// Get recent audit logs for this user
$audit_sql = "
    SELECT al.*, u.full_name as actor_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.record_id = :user_id AND al.table_name = 'users'
    ORDER BY al.created_at DESC
    LIMIT 10
";
$audit_stmt = $db->prepare($audit_sql);
$audit_stmt->execute(['user_id' => $user_id]);
$audit_logs = $audit_stmt->fetchAll();

$page_title = 'View User - ' . htmlspecialchars($user['full_name']);
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
                <div>
                    <a href="users.php" style="color: rgba(255, 255, 255, 0.6); text-decoration: none; font-size: 14px; display: block; margin-bottom: 0.5rem;">
                        ← Back to Users
                    </a>
                    <h1 class="page-title"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p class="page-subtitle">User profile and details</p>
                </div>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                        $currentUser = Auth::user();
                        $nameParts = explode(' ', $currentUser['full_name']);
                        $initials = strtoupper(substr($nameParts[0], 0, 1));
                        if (count($nameParts) > 1) {
                            $initials .= strtoupper(substr(end($nameParts), 0, 1));
                        }
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                        <div class="user-role">Admin</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- User Profile Overview -->
        <div class="content-section">
            <div class="user-profile-header">
                <div class="user-avatar-large">
                    <?php 
                    $nameParts = explode(' ', $user['full_name']);
                    $initials = strtoupper(substr($nameParts[0], 0, 1));
                    if (count($nameParts) > 1) {
                        $initials .= strtoupper(substr(end($nameParts), 0, 1));
                    }
                    echo $initials;
                    ?>
                </div>
                <div class="user-profile-info">
                    <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                    <div class="user-meta">
                        <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                        <?php if ($user['email_verified']): ?>
                            <span class="badge badge-info">Email Verified</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Email Not Verified</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Details Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 24px;">
            <!-- Basic Information -->
            <div class="content-section">
                <h3 class="section-title">Basic Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Roll Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['roll_number']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Department</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['department']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">User ID</span>
                        <span class="info-value">#<?php echo $user['id']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Joined</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Roles & Permissions -->
            <div class="content-section">
                <h3 class="section-title">Roles & Permissions</h3>
                <div class="roles-display">
                    <?php 
                    $roles = explode(',', $user['roles'] ?? '');
                    if (!empty($roles[0])): 
                        foreach ($roles as $role): 
                    ?>
                        <span class="badge badge-primary" style="font-size: 14px; padding: 8px 16px;">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', trim($role)))); ?>
                        </span>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <p style="color: rgba(255, 255, 255, 0.6);">No roles assigned</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Clubs Led (if applicable) -->
        <?php if (count($clubs) > 0): ?>
        <div class="content-section">
            <h3 class="section-title">Clubs Led (<?php echo count($clubs); ?>)</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Club Name</th>
                            <th>Total Events</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clubs as $club): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                            <td><?php echo $club['total_events']; ?></td>
                            <td>
                                <span class="badge <?php echo $club['is_active'] ? 'badge-success' : 'badge-error'; ?>">
                                    <?php echo $club['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="view-club.php?id=<?php echo $club['id']; ?>" class="btn-icon-small">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Created Events (if applicable) -->
        <?php if (count($created_events) > 0): ?>
        <div class="content-section">
            <h3 class="section-title">Created Events (<?php echo count($created_events); ?>)</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Date</th>
                            <th>Club</th>
                            <th>Status</th>
                            <th>Registrations</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($created_events as $event): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                            <td><?php echo htmlspecialchars($event['club_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                $status_class = match($event['status']) {
                                    'approved' => 'badge-success',
                                    'pending' => 'badge-warning',
                                    'rejected' => 'badge-error',
                                    default => 'badge-default'
                                };
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo ucfirst($event['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $event['total_registrations']; ?> / <?php echo $event['max_participants']; ?></td>
                            <td>
                                <a href="view-event.php?id=<?php echo $event['id']; ?>" class="btn-icon-small">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Event Registrations -->
        <div class="content-section">
            <h3 class="section-title">Event Registrations (<?php echo count($registrations); ?>)</h3>
            <?php if (count($registrations) > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Date</th>
                            <th>Club</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reg['event_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($reg['event_date'])); ?></td>
                            <td><?php echo htmlspecialchars($reg['club_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($reg['registration_date'])); ?></td>
                            <td>
                                <a href="view-event.php?id=<?php echo $reg['id']; ?>" class="btn-icon-small">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="color: rgba(255, 255, 255, 0.6); text-align: center; padding: 40px;">No event registrations yet.</p>
            <?php endif; ?>
        </div>

        <!-- Activity Log -->
        <?php if (count($audit_logs) > 0): ?>
        <div class="content-section">
            <h3 class="section-title">Recent Activity</h3>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Performed By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit_logs as $log): ?>
                        <tr>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo ucwords(str_replace('_', ' ', $log['action'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/admin.js?v=2"></script>
    
    <style>
        .user-profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 8px 0;
        }
        
        .user-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        
        .user-profile-info h2 {
            margin: 0 0 8px 0;
            font-size: 28px;
            color: white;
        }
        
        .user-email {
            margin: 0 0 12px 0;
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
        }
        
        .user-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .info-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 16px;
            color: white;
            font-weight: 500;
        }
        
        .roles-display {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        @media (max-width: 768px) {
            .user-profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .user-profile-info {
                align-items: center;
            }
        }
    </style>
</body>
</html>
