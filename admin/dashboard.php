<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();

// Get system statistics
$stats_sql = "
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM events) as total_events,
        (SELECT COUNT(*) FROM events WHERE status = 'pending') as pending_events,
        (SELECT COUNT(*) FROM events WHERE status = 'approved') as approved_events,
        (SELECT COUNT(*) FROM event_registrations) as total_registrations,
        (SELECT COUNT(*) FROM clubs WHERE is_active = 1) as active_clubs,
        (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_users_week
";
$stats_stmt = $db->query($stats_sql);
$stats = $stats_stmt->fetch();

// Get pending events for approval
$pending_sql = "
    SELECT e.*, u.full_name as creator_name, u.email as creator_email, c.club_name
    FROM events e
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN clubs c ON e.club_id = c.id
    WHERE e.status = 'pending'
    ORDER BY e.created_at DESC
    LIMIT 10
";
$pending_stmt = $db->query($pending_sql);
$pending_events = $pending_stmt->fetchAll();

// Get role distribution
$roles_sql = "
    SELECT r.role_name, COUNT(ur.user_id) as count
    FROM roles r
    LEFT JOIN user_roles ur ON r.id = ur.role_id
    GROUP BY r.id
    ORDER BY count DESC
";
$roles_stmt = $db->query($roles_sql);
$role_distribution = $roles_stmt->fetchAll();

$page_title = 'Admin Dashboard';
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
        /* Premium Dark Black Glass with Superior Liquid Effect */
        .btn-liquid-glass {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.95);
            background: 
                radial-gradient(ellipse at top left, rgba(60, 60, 65, 0.4) 0%, transparent 50%),
                radial-gradient(ellipse at bottom right, rgba(40, 40, 45, 0.4) 0%, transparent 50%),
                linear-gradient(135deg, 
                    rgba(35, 35, 40, 0.95) 0%, 
                    rgba(25, 25, 30, 0.98) 30%,
                    rgba(20, 20, 25, 1) 60%,
                    rgba(25, 25, 30, 0.98) 100%),
                linear-gradient(to bottom,
                    rgba(255, 255, 255, 0.03),
                    rgba(0, 0, 0, 0.6));
            backdrop-filter: blur(40px) saturate(100%) brightness(0.45) contrast(1.15);
            -webkit-backdrop-filter: blur(40px) saturate(100%) brightness(0.45) contrast(1.15);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            cursor: pointer;
            isolation: isolate;
            overflow: hidden;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.6),
                0 4px 16px rgba(0, 0, 0, 0.4),
                0 2px 8px rgba(0, 0, 0, 0.3),
                0 1px 2px rgba(255, 255, 255, 0.05),
                inset 0 1px 1px rgba(255, 255, 255, 0.12),
                inset 0 -1px 1px rgba(0, 0, 0, 0.8),
                inset 1px 0 1px rgba(255, 255, 255, 0.06),
                inset -1px 0 1px rgba(255, 255, 255, 0.06);
        }
        


        
        /* Sophisticated glass edge with refraction effect */
        .btn-liquid-glass::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                linear-gradient(90deg, 
                    transparent 0%, 
                    rgba(255, 255, 255, 0.08) 25%,
                    rgba(255, 255, 255, 0.12) 50%,
                    rgba(255, 255, 255, 0.08) 75%,
                    transparent 100%),
                linear-gradient(0deg, 
                    transparent 0%, 
                    rgba(255, 255, 255, 0.06) 25%,
                    rgba(255, 255, 255, 0.1) 50%,
                    rgba(255, 255, 255, 0.06) 75%,
                    transparent 100%);
            border-radius: 16px;
            z-index: 0;
            opacity: 0.5;
            filter: blur(1px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            mix-blend-mode: overlay;
        }
        
        /* Superior liquid glass reflection with multiple light sources */
        .btn-liquid-glass::after {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: 
                radial-gradient(ellipse 600px 120px at 50% -10%, rgba(255, 255, 255, 0.18) 0%, transparent 50%),
                radial-gradient(ellipse 300px 80px at 20% 15%, rgba(255, 255, 255, 0.12) 0%, transparent 55%),
                radial-gradient(ellipse 200px 60px at 80% 20%, rgba(255, 255, 255, 0.08) 0%, transparent 50%),
                radial-gradient(circle 150px at 85% 85%, rgba(255, 255, 255, 0.06) 0%, transparent 40%),
                linear-gradient(to bottom,
                    rgba(255, 255, 255, 0.15) 0%,
                    rgba(255, 255, 255, 0.08) 20%,
                    rgba(255, 255, 255, 0.02) 50%,
                    transparent 100%);
            border-radius: 16px;
            z-index: 1;
            opacity: 0.8;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            mix-blend-mode: soft-light;
            animation: liquidShimmer 6s ease-in-out infinite;
        }
        
        @keyframes liquidShimmer {
            0%, 100% {
                opacity: 0.8;
            }
            50% {
                opacity: 0.95;
            }
        }
        
        .btn-liquid-glass:hover {
            transform: translateY(-2px) scale(1.02);
            background: 
                radial-gradient(ellipse at top left, rgba(70, 70, 75, 0.5) 0%, transparent 50%),
                radial-gradient(ellipse at bottom right, rgba(50, 50, 55, 0.5) 0%, transparent 50%),
                linear-gradient(135deg, 
                    rgba(45, 45, 50, 0.98) 0%, 
                    rgba(35, 35, 40, 1) 30%,
                    rgba(30, 30, 35, 1) 60%,
                    rgba(35, 35, 40, 1) 100%),
                linear-gradient(to bottom,
                    rgba(255, 255, 255, 0.05),
                    rgba(0, 0, 0, 0.7));
            backdrop-filter: blur(50px) saturate(110%) brightness(0.55) contrast(1.25);
            -webkit-backdrop-filter: blur(50px) saturate(110%) brightness(0.55) contrast(1.25);
            border-color: rgba(255, 255, 255, 0.18);
            box-shadow: 
                0 16px 48px rgba(0, 0, 0, 0.7),
                0 8px 24px rgba(0, 0, 0, 0.5),
                0 4px 16px rgba(0, 0, 0, 0.4),
                0 2px 4px rgba(255, 255, 255, 0.08),
                inset 0 2px 2px rgba(255, 255, 255, 0.18),
                inset 0 -2px 2px rgba(0, 0, 0, 0.9),
                inset 2px 0 2px rgba(255, 255, 255, 0.1),
                inset -2px 0 2px rgba(255, 255, 255, 0.1);
        }
        
        .btn-liquid-glass:hover::before {
            opacity: 0.75;
            filter: blur(0.5px);
            animation: glassRefraction 4s ease-in-out infinite;
        }
        
        @keyframes glassRefraction {
            0%, 100% {
                opacity: 0.7;
            }
            50% {
                opacity: 0.9;
            }
        }
        
        .btn-liquid-glass:hover::after {
            opacity: 1;
            background: 
                radial-gradient(ellipse 600px 120px at 50% -10%, rgba(255, 255, 255, 0.25) 0%, transparent 50%),
                radial-gradient(ellipse 300px 80px at 25% 15%, rgba(255, 255, 255, 0.18) 0%, transparent 55%),
                radial-gradient(ellipse 200px 60px at 75% 20%, rgba(255, 255, 255, 0.12) 0%, transparent 50%),
                radial-gradient(circle 150px at 85% 85%, rgba(255, 255, 255, 0.1) 0%, transparent 40%),
                linear-gradient(to bottom,
                    rgba(255, 255, 255, 0.2) 0%,
                    rgba(255, 255, 255, 0.12) 20%,
                    rgba(255, 255, 255, 0.04) 50%,
                    transparent 100%);
            animation: liquidFlow 5s ease-in-out infinite;
        }
        
        @keyframes liquidFlow {
            0%, 100% {
                background: 
                    radial-gradient(ellipse 600px 120px at 50% -10%, rgba(255, 255, 255, 0.25) 0%, transparent 50%),
                    radial-gradient(ellipse 300px 80px at 25% 15%, rgba(255, 255, 255, 0.18) 0%, transparent 55%),
                    radial-gradient(ellipse 200px 60px at 75% 20%, rgba(255, 255, 255, 0.12) 0%, transparent 50%),
                    radial-gradient(circle 150px at 85% 85%, rgba(255, 255, 255, 0.1) 0%, transparent 40%),
                    linear-gradient(to bottom,
                        rgba(255, 255, 255, 0.2) 0%,
                        rgba(255, 255, 255, 0.12) 20%,
                        rgba(255, 255, 255, 0.04) 50%,
                        transparent 100%);
            }
            50% {
                background: 
                    radial-gradient(ellipse 600px 120px at 50% -10%, rgba(255, 255, 255, 0.22) 0%, transparent 50%),
                    radial-gradient(ellipse 300px 80px at 75% 15%, rgba(255, 255, 255, 0.18) 0%, transparent 55%),
                    radial-gradient(ellipse 200px 60px at 25% 20%, rgba(255, 255, 255, 0.14) 0%, transparent 50%),
                    radial-gradient(circle 150px at 15% 15%, rgba(255, 255, 255, 0.12) 0%, transparent 40%),
                    linear-gradient(to bottom,
                        rgba(255, 255, 255, 0.18) 0%,
                        rgba(255, 255, 255, 0.1) 20%,
                        rgba(255, 255, 255, 0.03) 50%,
                        transparent 100%);
            }
        }
        

        
        .btn-liquid-glass:active {
            transform: translateY(0px) scale(0.98);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: 
                linear-gradient(135deg, 
                    rgba(25, 25, 30, 1) 0%, 
                    rgba(20, 20, 25, 1) 50%,
                    rgba(15, 15, 20, 1) 100%);
            backdrop-filter: blur(35px) saturate(100%) brightness(0.35);
            -webkit-backdrop-filter: blur(35px) saturate(100%) brightness(0.35);
            box-shadow: 
                0 4px 16px rgba(0, 0, 0, 0.8),
                0 2px 8px rgba(0, 0, 0, 0.6),
                inset 0 4px 8px rgba(0, 0, 0, 0.9),
                inset 0 1px 2px rgba(255, 255, 255, 0.1);
        }
        
        .btn-liquid-content {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-shadow: 
                0 1px 2px rgba(0, 0, 0, 0.8),
                0 2px 4px rgba(0, 0, 0, 0.6),
                0 0 10px rgba(0, 0, 0, 0.4);
            transition: all 0.7s cubic-bezier(0.4, 0, 0.2, 1);
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.5));
        }
        
        .btn-liquid-glass:hover .btn-liquid-content {
            text-shadow: 
                0 2px 4px rgba(0, 0, 0, 0.9),
                0 1px 2px rgba(255, 255, 255, 0.4),
                0 0 20px rgba(255, 255, 255, 0.2);
            filter: brightness(1.15) contrast(1.1) drop-shadow(0 2px 3px rgba(0, 0, 0, 0.6));
        }
        
        /* Premium liquid shine sweep */
        .btn-liquid-shine {
            position: absolute;
            top: -50%;
            left: -100%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(255, 255, 255, 0.02) 25%,
                rgba(255, 255, 255, 0.08) 50%,
                rgba(255, 255, 255, 0.02) 75%,
                transparent 100%
            );
            transform: rotate(45deg) translateX(-100%);
            transition: transform 1.5s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 3;
            filter: blur(2px);
            mix-blend-mode: overlay;
        }
        
        .btn-liquid-glass:hover .btn-liquid-shine {
            transform: rotate(45deg) translateX(100%);
        }
        
        /* Icon rotation animation */
        @keyframes liquidSpin {
            from { 
                transform: rotate(0deg);
                filter: drop-shadow(0 0 2px rgba(255, 255, 255, 0.3));
            }
            50% {
                filter: drop-shadow(0 0 4px rgba(255, 255, 255, 0.5));
            }
            to { 
                transform: rotate(360deg);
                filter: drop-shadow(0 0 2px rgba(255, 255, 255, 0.3));
            }
        }
        
        .btn-liquid-glass:active .btn-liquid-content svg {
            animation: liquidSpin 1.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Premium chromatic shine sweep */
        .btn-liquid-shine {
            position: absolute;
            top: -100%;
            left: -100%;
            width: 300%;
            height: 300%;
            background: 
                linear-gradient(90deg,
                    transparent 0%,
                    rgba(99, 102, 241, 0.15) 20%,
                    rgba(255, 255, 255, 0.4) 40%,
                    rgba(168, 85, 247, 0.15) 60%,
                    rgba(236, 72, 153, 0.1) 80%,
                    transparent 100%);
            transform: rotate(45deg) translateX(-150%);
            transition: transform 1.5s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 3;
            filter: blur(3px);
            mix-blend-mode: screen;
        }
        
        .btn-liquid-glass:hover .btn-liquid-shine {
            transform: rotate(45deg) translateX(150%);
            animation: chromaticSweep 4s ease-in-out infinite;
        }
        
        @keyframes chromaticSweep {
            0%, 100% {
                background: 
                    linear-gradient(90deg,
                        transparent 0%,
                        rgba(99, 102, 241, 0.15) 20%,
                        rgba(255, 255, 255, 0.4) 40%,
                        rgba(168, 85, 247, 0.15) 60%,
                        rgba(236, 72, 153, 0.1) 80%,
                        transparent 100%);
                filter: blur(3px);
            }
            50% {
                background: 
                    linear-gradient(90deg,
                        transparent 0%,
                        rgba(236, 72, 153, 0.15) 20%,
                        rgba(255, 255, 255, 0.5) 40%,
                        rgba(99, 102, 241, 0.2) 60%,
                        rgba(168, 85, 247, 0.15) 80%,
                        transparent 100%);
                filter: blur(4px);
            }
        }
        
        /* Icon rotation with chromatic glow */
        @keyframes liquidSpin {
            from { 
                transform: rotate(0deg);
                filter: drop-shadow(0 0 4px rgba(99, 102, 241, 0.6));
            }
            50% {
                filter: drop-shadow(0 0 8px rgba(168, 85, 247, 0.8))
                        drop-shadow(0 0 12px rgba(99, 102, 241, 0.5));
            }
            to { 
                transform: rotate(360deg);
                filter: drop-shadow(0 0 4px rgba(168, 85, 247, 0.6));
            }
        }
        
        .btn-liquid-glass:active .btn-liquid-content svg {
            animation: liquidSpin 1.2s cubic-bezier(0.4, 0, 0.2, 1);
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
            <a href="dashboard.php" class="nav-item active">
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
                <?php if ($stats['pending_events'] > 0): ?>
                    <span class="nav-badge"><?php echo $stats['pending_events']; ?></span>
                <?php endif; ?>
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
                <h1 class="page-title">Admin Dashboard 🛡️</h1>
                <p class="page-subtitle">System overview and management</p>
            </div>
            
            <div class="header-right">
                <button class="btn-liquid-glass" onclick="location.reload()">
                    <span class="btn-liquid-content">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refresh
                    </span>
                    <span class="btn-liquid-shine"></span>
                </button>
            </div>
        </header>
        
        <!-- Pending Events Section -->
        <?php if (!empty($pending_events)): ?>
        <section class="content-section">
            <div class="section-header">
                <h2 class="section-title">Pending Event Approvals</h2>
                <a href="events.php" class="section-link">View All</a>
            </div>
            
            <div class="admin-cards-grid">
                <?php foreach ($pending_events as $event): ?>
                    <div class="admin-event-card">
                        <div class="admin-card-header">
                            <h3 class="admin-card-title"><?php echo Security::sanitize($event['event_name']); ?></h3>
                            <span class="status-badge status-pending">Pending</span>
                        </div>
                        
                        <div class="admin-card-meta">
                            <div class="meta-item">
                                <strong>Created by:</strong> <?php echo Security::sanitize($event['creator_name']); ?>
                            </div>
                            <div class="meta-item">
                                <strong>Club:</strong> <?php echo Security::sanitize($event['club_name'] ?? 'N/A'); ?>
                            </div>
                            <div class="meta-item">
                                <strong>Date:</strong> <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                            </div>
                            <div class="meta-item">
                                <strong>Venue:</strong> <?php echo Security::sanitize($event['venue']); ?>
                            </div>
                        </div>
                        
                        <div class="admin-card-actions">
                            <button class="btn btn-success btn-sm approve-btn" data-event-id="<?php echo $event['id']; ?>">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Approve
                            </button>
                            <button class="btn btn-error btn-sm reject-btn" data-event-id="<?php echo $event['id']; ?>">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Reject
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="stats-grid stats-grid-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-trend">+<?php echo $stats['new_users_week']; ?> this week</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-accent">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_events']; ?></div>
                    <div class="stat-label">Total Events</div>
                    <div class="stat-trend"><?php echo $stats['approved_events']; ?> approved</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-warning">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['pending_events']; ?></div>
                    <div class="stat-label">Pending Approval</div>
                    <div class="stat-trend">Requires action</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_registrations']; ?></div>
                    <div class="stat-label">Total Registrations</div>
                    <div class="stat-trend"><?php echo $stats['active_clubs']; ?> active clubs</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Management Section -->
        <section class="content-section">
            <div class="section-header">
                <h2 class="section-title">Quick Management</h2>
            </div>
            
            <div class="management-cards-grid">
                <a href="users.php?role=student" class="management-card card-primary">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <div class="card-content">
                        <h3>Manage Students</h3>
                        <p>View and manage student accounts</p>
                    </div>
                    <div class="card-arrow">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
                
                <a href="users.php?role=faculty" class="management-card card-accent">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                    </div>
                    <div class="card-content">
                        <h3>Manage Faculties</h3>
                        <p>View and manage faculty accounts</p>
                    </div>
                    <div class="card-arrow">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
                
                <a href="roles.php" class="management-card card-success">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div class="card-content">
                        <h3>Manage Roles</h3>
                        <p>Configure user roles and permissions</p>
                    </div>
                    <div class="card-arrow">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
                
                <a href="clubs.php" class="management-card card-warning">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div class="card-content">
                        <h3>Manage Clubs</h3>
                        <p>View and manage all clubs</p>
                    </div>
                    <div class="card-arrow">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
                
                <a href="events.php" class="management-card card-info">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="card-content">
                        <h3>Manage Events</h3>
                        <p>Approve and manage all events</p>
                    </div>
                    <div class="card-arrow">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
                
                <a href="analytics.php" class="management-card card-secondary">
                    <div class="card-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="card-content">
                        <h3>Analytics</h3>
                        <p>View system analytics and reports</p>
                    </div>
                    <div class="card-arrow">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </a>
            </div>
        </section>
    </main>
    
    <div id="toast-container"></div>
    
    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/admin.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/toast.js?v=2"></script>
</body>
</html>
