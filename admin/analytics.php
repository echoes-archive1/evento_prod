<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();

// Get time period filter
$period = $_GET['period'] ?? '30'; // days

// User Growth Statistics
$user_growth_sql = "
    SELECT DATE(created_at) as date, COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :period DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
";
$user_growth_stmt = $db->prepare($user_growth_sql);
$user_growth_stmt->execute(['period' => $period]);
$user_growth = $user_growth_stmt->fetchAll();

// Event Statistics
$event_stats_sql = "
    SELECT 
        status,
        COUNT(*) as count,
        (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id) as total_registrations
    FROM events e
    GROUP BY status
";
$event_stats_stmt = $db->query($event_stats_sql);
$event_stats = $event_stats_stmt->fetchAll();

// Popular Events
$popular_events_sql = "
    SELECT e.event_name, e.event_date, c.club_name, COUNT(er.id) as registrations
    FROM events e
    LEFT JOIN clubs c ON e.club_id = c.id
    LEFT JOIN event_registrations er ON e.id = er.event_id
    WHERE e.status = 'approved'
    GROUP BY e.id
    ORDER BY registrations DESC
    LIMIT 10
";
$popular_events_stmt = $db->query($popular_events_sql);
$popular_events = $popular_events_stmt->fetchAll();

// Department Distribution
$dept_stats_sql = "
    SELECT department, COUNT(*) as count
    FROM users
    WHERE department IS NOT NULL AND department != 'Administration'
    GROUP BY department
    ORDER BY count DESC
";
$dept_stats_stmt = $db->query($dept_stats_sql);
$dept_stats = $dept_stats_stmt->fetchAll();

// Club Activity
$club_activity_sql = "
    SELECT c.club_name, 
           COUNT(DISTINCT e.id) as total_events,
           COUNT(DISTINCT er.user_id) as total_participants,
           c.is_active
    FROM clubs c
    LEFT JOIN events e ON c.id = e.club_id
    LEFT JOIN event_registrations er ON e.id = er.event_id
    GROUP BY c.id
    ORDER BY total_events DESC
";
$club_activity_stmt = $db->query($club_activity_sql);
$club_activity = $club_activity_stmt->fetchAll();

// Recent Activity
$recent_activity_sql = "
    SELECT al.action, al.table_name, al.created_at, u.full_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 20
";
$recent_activity_stmt = $db->query($recent_activity_sql);
$recent_activity = $recent_activity_stmt->fetchAll();

// System Stats
$system_stats_sql = "
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
        (SELECT COUNT(*) FROM events) as total_events,
        (SELECT COUNT(*) FROM events WHERE status = 'approved') as approved_events,
        (SELECT COUNT(*) FROM event_registrations) as total_registrations,
        (SELECT COUNT(*) FROM clubs WHERE is_active = 1) as active_clubs
";
$system_stats_stmt = $db->query($system_stats_sql);
$system_stats = $system_stats_stmt->fetch();

$page_title = 'Analytics Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            
            <a href="roles.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
                <span>Role Management</span>
            </a>
            
            <a href="analytics.php" class="nav-item active">
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
                <h1 class="page-title">Analytics Dashboard 📊</h1>
                <p class="page-subtitle">System insights and trends</p>
            </div>
            
            <div class="header-right">
                <select class="form-control" onchange="window.location.href='?period='+this.value" style="max-width: 200px;">
                    <option value="7" <?php echo $period == 7 ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="30" <?php echo $period == 30 ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="90" <?php echo $period == 90 ? 'selected' : ''; ?>>Last 90 Days</option>
                    <option value="365" <?php echo $period == 365 ? 'selected' : ''; ?>>Last Year</option>
                </select>
            </div>
        </header>
        
        <!-- System Overview Stats -->
        <div class="stats-grid stats-grid-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $system_stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-trend"><?php echo $system_stats['active_users']; ?> active</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-accent">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $system_stats['total_events']; ?></div>
                    <div class="stat-label">Total Events</div>
                    <div class="stat-trend"><?php echo $system_stats['approved_events']; ?> approved</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $system_stats['total_registrations']; ?></div>
                    <div class="stat-label">Total Registrations</div>
                    <div class="stat-trend">All time</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-warning">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $system_stats['active_clubs']; ?></div>
                    <div class="stat-label">Active Clubs</div>
                    <div class="stat-trend">Currently active</div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 1 -->
        <div class="stats-grid stats-grid-2">
            <!-- User Growth Chart -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">User Growth Trend</h3>
                </div>
                <div class="card-body">
                    <canvas id="userGrowthChart"></canvas>
                </div>
            </div>
            
            <!-- Department Distribution Chart -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Department Distribution</h3>
                </div>
                <div class="card-body">
                    <canvas id="departmentChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Popular Events & Club Activity -->
        <div class="stats-grid stats-grid-2">
            <!-- Popular Events -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Top Events by Registrations</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Club</th>
                                    <th>Registrations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($popular_events as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                                        <td><?php echo htmlspecialchars($event['club_name'] ?? 'N/A'); ?></td>
                                        <td><strong><?php echo $event['registrations']; ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Club Activity -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Club Activity Overview</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Club</th>
                                    <th>Events</th>
                                    <th>Participants</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($club_activity as $club): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                                        <td><?php echo $club['total_events']; ?></td>
                                        <td><?php echo $club['total_participants']; ?></td>
                                        <td>
                                            <?php if ($club['is_active']): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="<?php echo BASE_URL; ?>/public/js/admin.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/toast.js?v=2"></script>
    <script>
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($user_growth, 'date')); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode(array_column($user_growth, 'count')); ?>,
                    borderColor: 'rgba(99, 102, 241, 1)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Department Chart
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($dept_stats, 'department')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($dept_stats, 'count')); ?>,
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(59, 130, 246, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>
</body>
</html>
