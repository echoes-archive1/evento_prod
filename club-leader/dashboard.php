<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('club_leader');

$user = Auth::user();
$db = Database::getInstance()->getConnection();

// Get user's club
$club_sql = "SELECT c.* FROM clubs c WHERE c.leader_id = :user_id AND c.is_active = 1 LIMIT 1";
$club_stmt = $db->prepare($club_sql);
$club_stmt->execute(['user_id' => Auth::id()]);
$my_club = $club_stmt->fetch();

$club_id = $my_club['id'] ?? null;

// Get club statistics
if ($club_id) {
    $stats_sql = "
        SELECT 
            (SELECT COUNT(*) FROM events WHERE club_id = :club_id1) as total_events,
            (SELECT COUNT(*) FROM events WHERE club_id = :club_id2 AND status = 'approved') as approved_events,
            (SELECT SUM(current_participants) FROM events WHERE club_id = :club_id3) as total_members,
            (SELECT COUNT(*) FROM event_registrations er 
             JOIN events e ON er.event_id = e.id 
             WHERE e.club_id = :club_id4 AND er.attendance_status = 'attended') as total_attendance
    ";
    $stats_stmt = $db->prepare($stats_sql);
    $stats_stmt->execute([
        'club_id1' => $club_id,
        'club_id2' => $club_id,
        'club_id3' => $club_id,
        'club_id4' => $club_id
    ]);
    $stats = $stats_stmt->fetch();
} else {
    $stats = ['total_events' => 0, 'approved_events' => 0, 'total_members' => 0, 'total_attendance' => 0];
}

// Get club events
$events_sql = "
    SELECT e.*, 
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registrations,
           u.full_name as creator_name
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.club_id = :club_id
    ORDER BY e.created_at DESC
    LIMIT 10
";
$events_stmt = $db->prepare($events_sql);
$events_stmt->execute(['club_id' => $club_id]);
$club_events = $events_stmt->fetchAll();

// Get current theme
$theme_sql = "
    SELECT t.* FROM themes t
    JOIN theme_assignments ta ON t.id = ta.theme_id
    WHERE ta.club_id = :club_id
    LIMIT 1
";
$theme_stmt = $db->prepare($theme_sql);
$theme_stmt->execute(['club_id' => $club_id]);
$current_theme = $theme_stmt->fetch();

$page_title = 'Club Leader Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
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

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item active">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>
            
            <a href="club-events.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>Club Events</span>
            </a>
            
            <a href="members.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span>Members</span>
            </a>
            
            <a href="theme-settings.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                </svg>
                <span>Theme Settings</span>
            </a>
            
            <a href="analytics.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>Analytics</span>
            </a>
            
            <a href="profile.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>Profile</span>
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
        <!-- Top Bar -->
        <header class="top-bar">
            <div class="top-bar-left">
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <h1 class="page-title">Club Leader Dashboard</h1>
            </div>
            
            <div class="top-bar-right">
                <div class="user-menu">
                    <img src="<?php echo BASE_URL; ?>/public/images/default-avatar.png" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="user-role">Club Leader</span>
                    </div>
                </div>
            </div>
        </header>

        <?php if (!$my_club): ?>
            <!-- No Club Assigned Alert -->
            <div class="alert alert-warning">
                <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span>You are not assigned to any club. Please contact the administrator.</span>
            </div>
        <?php else: ?>
            <!-- Club Info Banner -->
            <div class="club-banner" style="background: linear-gradient(135deg, <?php echo htmlspecialchars($current_theme['primary_color'] ?? '#6366f1'); ?> 0%, <?php echo htmlspecialchars($current_theme['accent_color'] ?? '#8b5cf6'); ?> 100%);">
                <div class="club-banner-content">
                    <div class="club-info">
                        <?php if (!empty($my_club['logo_url'])): ?>
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($my_club['logo_url']); ?>" alt="Club Logo" class="club-logo">
                        <?php endif; ?>
                        <div>
                            <h2 class="club-name"><?php echo htmlspecialchars($my_club['club_name']); ?></h2>
                            <p class="club-description"><?php echo htmlspecialchars($my_club['description'] ?? $my_club['club_description'] ?? ''); ?></p>
                        </div>
                    </div>
                    <a href="edit-club.php" class="btn btn-light">
                        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Club
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?php echo $stats['total_events'] ?? 0; ?>">0</h3>
                        <p class="stat-label">Total Events</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-icon-success">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?php echo $stats['approved_events'] ?? 0; ?>">0</h3>
                        <p class="stat-label">Approved Events</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-icon-info">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?php echo $stats['total_members'] ?? 0; ?>">0</h3>
                        <p class="stat-label">Total Registrations</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon stat-icon-warning">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value" data-count="<?php echo $stats['total_attendance'] ?? 0; ?>">0</h3>
                        <p class="stat-label">Total Attendance</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Quick Actions</h2>
                </div>
                <div class="action-buttons">
                    <a href="create-event.php" class="btn btn-primary">
                        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create Event
                    </a>
                    <a href="theme-settings.php" class="btn btn-secondary">
                        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                        </svg>
                        Customize Theme
                    </a>
                    <a href="members.php" class="btn btn-secondary">
                        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        Manage Members
                    </a>
                    <a href="analytics.php" class="btn btn-secondary">
                        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        View Analytics
                    </a>
                </div>
            </div>

            <!-- Club Events Table -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Club Events</h2>
                    <a href="club-events.php" class="btn btn-text">View All</a>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Date</th>
                                <th>Venue</th>
                                <th>Created By</th>
                                <th>Registrations</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($club_events)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No events yet. <a href="create-event.php">Create your first event</a></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($club_events as $event): ?>
                                    <tr>
                                        <td>
                                            <div class="table-cell-main"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                        <td><?php echo htmlspecialchars($event['creator_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo $event['current_participants']; ?> / <?php echo $event['max_participants']; ?>
                                            </span>
                                        </td>
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
                                        <td>
                                            <div class="action-buttons-small">
                                                <a href="view-event.php?id=<?php echo $event['id']; ?>" class="btn-icon-small" title="View">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                </a>
                                                <a href="registrations.php?event_id=<?php echo $event['id']; ?>" class="btn-icon-small" title="Registrations">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js"></script>
    <script>
        // Initialize counter animations
        document.addEventListener('DOMContentLoaded', () => {
            animateCounters();
        });
    </script>
    
    <style>
        .club-banner {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            color: white;
        }
        
        .club-banner-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
        }
        
        .club-info {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        
        .club-logo {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .club-name {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
        }
        
        .club-description {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }
        
        .btn-light {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-light:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        @media (max-width: 768px) {
            .club-banner-content {
                flex-direction: column;
                text-align: center;
            }
            
            .club-info {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</body>
</html>
