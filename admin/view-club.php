<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();
$error = '';
$club = null;
$events = [];
$members = [];

// Get club ID
$club_id = (int)($_GET['id'] ?? 0);

if ($club_id <= 0) {
    header('Location: clubs.php');
    exit;
}

// Fetch club details
try {
    $sql = "
        SELECT c.*, 
               u.full_name as leader_name,
               u.email as leader_email,
               (SELECT COUNT(*) FROM events WHERE club_id = c.id) as total_events,
               (SELECT COUNT(*) FROM events WHERE club_id = c.id AND status = 'approved') as approved_events,
               (SELECT COUNT(*) FROM event_registrations er 
                JOIN events e ON er.event_id = e.id 
                WHERE e.club_id = c.id) as total_participants
        FROM clubs c
        LEFT JOIN users u ON c.leader_id = u.id
        WHERE c.id = :club_id
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(['club_id' => $club_id]);
    $club = $stmt->fetch();
    
    if (!$club) {
        header('Location: clubs.php');
        exit;
    }
    
    // Fetch club events
    $events_sql = "
        SELECT e.*,
               (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registrations
        FROM events e
        WHERE e.club_id = :club_id
        ORDER BY e.event_date DESC
        LIMIT 10
    ";
    $events_stmt = $db->prepare($events_sql);
    $events_stmt->execute(['club_id' => $club_id]);
    $events = $events_stmt->fetchAll();
    
    // Fetch club members (users who registered for club events)
    $members_sql = "
        SELECT DISTINCT u.id, u.full_name, u.email, u.department,
               COUNT(DISTINCT er.event_id) as events_attended
        FROM users u
        JOIN event_registrations er ON u.id = er.user_id
        JOIN events e ON er.event_id = e.id
        WHERE e.club_id = :club_id
        GROUP BY u.id
        ORDER BY events_attended DESC, u.full_name
        LIMIT 20
    ";
    $members_stmt = $db->prepare($members_sql);
    $members_stmt->execute(['club_id' => $club_id]);
    $members = $members_stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Club view error: " . $e->getMessage());
    $error = 'Failed to load club details: ' . $e->getMessage();
}

$page_title = 'Club Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
    <style>
        .club-detail-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .club-header {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(168, 85, 247, 0.15));
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(99, 102, 241, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .club-title-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .club-title h1 {
            font-size: 2rem;
            margin: 0 0 0.5rem 0;
            color: #f1f5f9;
        }
        
        .club-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            color: #cbd5e1;
            font-size: 0.9rem;
        }
        
        .club-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .club-meta-item svg {
            width: 18px;
            height: 18px;
        }
        
        .club-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .club-stat-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        .club-section {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .section-title {
            font-size: 1.3rem;
            margin: 0 0 1.5rem 0;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-title svg {
            width: 24px;
            height: 24px;
            color: #818cf8;
        }
        
        .events-grid {
            display: grid;
            gap: 1rem;
        }
        
        .event-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.1);
            transition: all 0.3s ease;
        }
        
        .event-item:hover {
            background: rgba(15, 23, 42, 0.7);
            border-color: rgba(99, 102, 241, 0.3);
        }
        
        .event-info h4 {
            color: #f1f5f9;
            margin: 0 0 0.5rem 0;
        }
        
        .event-info p {
            color: #94a3b8;
            font-size: 0.85rem;
            margin: 0;
        }
        
        .members-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .members-table thead {
            background: rgba(15, 23, 42, 0.5);
        }
        
        .members-table th,
        .members-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }
        
        .members-table th {
            font-weight: 600;
            color: #cbd5e1;
            font-size: 0.9rem;
        }
        
        .members-table td {
            color: #e2e8f0;
        }
        
        .members-table tbody tr:hover {
            background: rgba(15, 23, 42, 0.3);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid rgba(99, 102, 241, 0.3);
            margin-bottom: 1.5rem;
        }
        
        .back-btn:hover {
            background: rgba(99, 102, 241, 0.2);
            transform: translateX(-5px);
        }
        
        .back-btn svg {
            width: 18px;
            height: 18px;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }
    </style>
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
            
            <a href="roles.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                <span>Roles</span>
            </a>
            
            <a href="audit-logs.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span>Audit Logs</span>
            </a>
            
            <a href="<?php echo BASE_URL; ?>/logout.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header">
            <div class="header-left">
                <h1 class="page-title">Club Details</h1>
                <p class="page-subtitle">Complete club information</p>
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

        <div class="club-detail-container">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($club): ?>
                <a href="clubs.php" class="back-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Clubs
                </a>
                
                <!-- Club Header -->
                <div class="club-header">
                    <div class="club-title-section">
                        <div class="club-title">
                            <h1><?php echo htmlspecialchars($club['club_name']); ?></h1>
                            <div class="club-meta">
                                <span class="club-meta-item">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Leader: <?php echo htmlspecialchars($club['leader_name'] ?? 'No leader assigned'); ?>
                                </span>
                                <span class="club-meta-item">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Created <?php echo date('M d, Y', strtotime($club['created_at'])); ?>
                                </span>
                                <span class="club-meta-item">
                                    <?php if ($club['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Inactive</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="club-stats-grid">
                    <div class="club-stat-card">
                        <div class="stat-value"><?php echo $club['total_events']; ?></div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="club-stat-card">
                        <div class="stat-value"><?php echo $club['approved_events']; ?></div>
                        <div class="stat-label">Approved Events</div>
                    </div>
                    <div class="club-stat-card">
                        <div class="stat-value"><?php echo $club['total_participants']; ?></div>
                        <div class="stat-label">Total Participants</div>
                    </div>
                    <div class="club-stat-card">
                        <div class="stat-value"><?php echo count($members); ?></div>
                        <div class="stat-label">Active Members</div>
                    </div>
                </div>

                <!-- Events Section -->
                <div class="club-section">
                    <h2 class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Recent Events
                    </h2>
                    
                    <?php if (count($events) > 0): ?>
                        <div class="events-grid">
                            <?php foreach ($events as $event): ?>
                                <div class="event-item">
                                    <div class="event-info">
                                        <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                                        <p>
                                            <?php echo date('M d, Y', strtotime($event['event_date'])); ?> • 
                                            <?php echo htmlspecialchars($event['venue']); ?> • 
                                            <?php echo $event['registrations']; ?> registrations
                                        </p>
                                    </div>
                                    <span class="badge badge-<?php echo $event['status']; ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No events yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Members Section -->
                <div class="club-section">
                    <h2 class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        Active Members
                    </h2>
                    
                    <?php if (count($members) > 0): ?>
                        <table class="members-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Events Attended</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td><?php echo htmlspecialchars($member['department']); ?></td>
                                        <td><?php echo $member['events_attended']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No members yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js?v=2"></script>
</body>
</html>
