<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

// If logged in, redirect to appropriate dashboard
if (Auth::check()) {
    Auth::redirectToDashboard();
    exit;
}

// Public landing page - show all events
$db = Database::getInstance()->getConnection();

// Get all approved upcoming events
$events_sql = "
    SELECT e.*, c.club_name, u.full_name as creator_name
    FROM events e
    LEFT JOIN clubs c ON e.club_id = c.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.status = 'approved' 
    AND e.event_date >= NOW()
    AND e.registration_deadline >= NOW()
    ORDER BY e.event_date ASC
    LIMIT 12
";
$events_stmt = $db->query($events_sql);
$available_events = $events_stmt->fetchAll();

// Get event statistics
$stats_sql = "
    SELECT 
        (SELECT COUNT(*) FROM events WHERE status = 'approved' AND event_date >= NOW()) as upcoming_events,
        (SELECT COUNT(*) FROM clubs WHERE is_active = 1) as active_clubs,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM event_registrations) as total_registrations
";
$stats_stmt = $db->query($stats_sql);
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Discover College Events</title>
    <link rel="stylesheet" href="<?php echo assetUrl('css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('css/alerts.css'); ?>">
    <style>
        @keyframes buttonGlow {
            0%, 100% { 
                box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3),
                            0 0 20px rgba(107, 33, 168, 0.2);
            }
            50% { 
                box-shadow: 0 6px 25px rgba(107, 33, 168, 0.5),
                            0 0 40px rgba(139, 92, 246, 0.4);
            }
        }
        
        @keyframes buttonGlowHover {
            0%, 100% { 
                box-shadow: 0 8px 25px rgba(107, 33, 168, 0.4),
                            0 0 30px rgba(139, 92, 246, 0.3);
            }
            50% { 
                box-shadow: 0 12px 40px rgba(107, 33, 168, 0.6),
                            0 0 60px rgba(139, 92, 246, 0.5);
            }
        }
        
        .navbar-actions {
            display: flex !important;
            gap: clamp(8px, 1.5vw, 16px);
            align-items: center;
            flex-shrink: 0;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .btn-nav {
            padding: clamp(8px, 1.2vw, 12px) clamp(16px, 2vw, 24px);
            border-radius: 0.5rem;
            font-size: clamp(0.8rem, 1.5vw, 1rem);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex !important;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
            white-space: nowrap;
        }
        
        .btn-nav-primary {
            background: linear-gradient(135deg, #1d1388 0%, #380b5c 50%, #290747 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        
        .btn-nav-primary:hover {
            transform: translateY(-2px);
            animation: buttonGlowHover 2s ease-in-out infinite;
            background: linear-gradient(135deg, #5b21b6 0%, #7e22ce 50%, #86198f 100%);
        }
        
        .btn-nav-secondary {
            background: rgba(99, 102, 241, 0.08);
            color: white;
            border: 1px solid rgba(99, 102, 241, 0.25);
        }
        
        .btn-nav-secondary:hover {
            background: rgba(107, 33, 168, 0.15);
            border-color: rgba(139, 92, 246, 0.35);
            animation: buttonGlow 2s ease-in-out infinite;
        }
        
        .public-main {
            margin-top: clamp(60px, 8vh, 80px);
            padding: clamp(10px, 2vw, 40px);
            min-height: calc(100vh - clamp(60px, 8vh, 80px));
            width: 100%;
            box-sizing: border-box;
            position: relative;
        }
        
        .public-main::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #000000;
            z-index: -2;
        }
        
        .public-main::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(134, 90, 236, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(232, 71, 152, 0.03) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(68, 124, 214, 0.08) 0%, transparent 50%);
            animation: radialMove 25s ease infinite;
            z-index: -1;
        }
        
        @keyframes pageGradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        @keyframes radialMove {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.1); }
        }
        
        .hero-section {
            text-align: left;
            padding: clamp(40px, 6vh, 80px) 0 clamp(30px, 4vh, 50px);
            margin-bottom: clamp(30px, 4vh, 50px);
        }
        
        .hero-title {
            font-size: clamp(2rem, 6vw, 4rem);
            font-weight: 900;
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 50%, #f472b6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            background-size: 300% auto;
            animation: gradientShift 8s ease infinite;
            margin-bottom: clamp(1rem, 2vh, 1.5rem);
            line-height: 1.2;
            text-shadow: 0 0 40px rgba(168, 85, 247, 0.3);
        }
        
        @keyframes gradientShift {
            0%, 100% { background-position: 0% center; }
            50% { background-position: 100% center; }
        }
        
        .hero-subtitle {
            font-size: clamp(1rem, 2.5vw, 1.5rem);
            color: rgba(203, 213, 225, 0.9);
            margin-bottom: clamp(2rem, 4vh, 3rem);
            line-height: 1.6;
            font-weight: 400;
        }
        
        .stats-grid-public {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(clamp(150px, 20vw, 250px), 1fr));
            gap: clamp(12px, 2vw, 24px);
            margin-bottom: clamp(30px, 5vh, 60px);
            width: 100%;
        }
        
        .stat-card-public {
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: clamp(12px, 1.5vw, 20px);
            padding: clamp(1rem, 3vw, 2rem);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card-public::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(59, 130, 246, 0.08) 0%,
                rgba(139, 92, 246, 0.08) 33%,
                rgba(236, 72, 153, 0.08) 66%,
                rgba(251, 146, 60, 0.08) 100%);
            background-size: 300% 300%;
            animation: statGradientMove 12s ease infinite;
            opacity: 0.7;
            z-index: 0;
        }
        
        .stat-card-public > * {
            position: relative;
            z-index: 1;
        }
        
        @keyframes statGradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .stat-card-public:hover {
            transform: translateY(-5px) scale(1.02);
            border-color: rgba(139, 92, 246, 0.4);
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 12px 40px rgba(99, 102, 241, 0.2),
                        0 0 25px rgba(139, 92, 246, 0.15);
        }
        
        .stat-card-public:hover::before {
            opacity: 1;
            animation-duration: 6s;
        }
        
        .stat-value-public {
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 800;
            background: linear-gradient(135deg, #60a5fa 0%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: clamp(0.5rem, 1vh, 1rem);
        }
        
        .stat-label-public {
            font-size: clamp(0.75rem, 1.5vw, 1rem);
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .section-header-public {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: clamp(1.5rem, 3vh, 2.5rem);
        }
        
        .section-title-public {
            font-size: clamp(1.5rem, 3.5vw, 2rem);
            font-weight: 800;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0e7ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .event-card-public {
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: clamp(16px, 2vw, 24px);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
        }
        
        .event-card-public::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(-45deg, 
                rgba(99, 102, 241, 0.08) 0%, 
                rgba(139, 92, 246, 0.08) 25%, 
                rgba(168, 85, 247, 0.08) 50%,
                rgba(236, 72, 153, 0.08) 75%,
                rgba(59, 130, 246, 0.08) 100%);
            background-size: 400% 400%;
            animation: gradientMove 15s ease infinite;
            opacity: 0.6;
            z-index: 0;
        }
        
        .event-card-public > * {
            position: relative;
            z-index: 1;
        }
        
        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .event-card-public:hover {
            transform: translateY(clamp(-8px, -2vh, -12px)) scale(1.02);
            border-color: rgba(139, 92, 246, 0.4);
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 clamp(20px, 4vh, 35px) clamp(50px, 8vw, 80px) rgba(99, 102, 241, 0.2),
                        0 0 30px rgba(139, 92, 246, 0.15),
                        inset 0 0 20px rgba(99, 102, 241, 0.05);
        }
        
        .event-card-public:hover::before {
            opacity: 1;
            animation-duration: 8s;
        }
        
        .event-image-public {
            width: 100%;
            height: clamp(160px, 20vh, 220px);
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .event-content-public {
            padding: clamp(1rem, 2.5vw, 1.5rem);
            position: relative;
            background: rgba(15, 23, 42, 0.4);
        }
        
        .event-badge {
            display: inline-block;
            padding: clamp(3px, 0.5vw, 6px) clamp(10px, 1.5vw, 16px);
            background: rgba(99, 102, 241, 0.2);
            color: #c4b5fd;
            border-radius: 1.5rem;
            font-size: clamp(0.7rem, 1.5vw, 0.875rem);
            font-weight: 700;
            margin-bottom: clamp(0.5rem, 1.5vh, 1rem);
            border: 1px solid rgba(139, 92, 246, 0.3);
            letter-spacing: 0.03em;
            transition: all 0.3s ease;
        }
        
        .event-card-public:hover .event-badge {
            background: rgba(139, 92, 246, 0.3);
            color: #e0e7ff;
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.2);
        }
        
        .event-title-public {
            font-size: clamp(1rem, 2.2vw, 1.25rem);
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: clamp(0.5rem, 1vh, 0.75rem);
            line-height: 1.3;
        }
        
        .event-meta-public {
            display: flex;
            flex-direction: column;
            gap: clamp(6px, 1vh, 10px);
            color: rgba(255, 255, 255, 0.6);
            font-size: clamp(0.8rem, 1.8vw, 1rem);
            margin-bottom: clamp(1rem, 2vh, 1.25rem);
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: clamp(6px, 1vw, 10px);
        }
        
        .event-footer-public {
            padding-top: clamp(0.75rem, 2vh, 1.25rem);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: clamp(0.5rem, 1vw, 1rem);
        }
        
        .event-capacity {
            font-size: clamp(0.75rem, 1.6vw, 0.95rem);
            color: rgba(255, 255, 255, 0.5);
        }
        
        .btn-register-public {
            padding: clamp(6px, 1vw, 10px) clamp(12px, 2vw, 20px);
            background: linear-gradient(135deg, #1e1581 0%, #5d0c9f 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: clamp(0.8rem, 1.8vw, 1rem);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
            position: relative;
            z-index: 1;
        }
        
        .btn-register-public:hover {
            transform: scale(1.05) translateY(-2px);
            animation: buttonGlowHover 2s ease-in-out infinite;
            background: linear-gradient(135deg, #5b21b6 0%, #7e22ce 50%, #86198f 100%);
        }
        
        .events-grid-public {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(clamp(280px, 30vw, 350px), 1fr));
            gap: clamp(16px, 2.5vw, 32px);
            width: 100%;
            box-sizing: border-box;
        }
        
        .empty-state {
            text-align: center;
            padding: clamp(3rem, 8vh, 5rem) clamp(1rem, 3vw, 2rem);
            color: rgba(255, 255, 255, 0.5);
            font-size: clamp(0.9rem, 2vw, 1.1rem);
        }
        
        /* Tablet and below */
        @media (max-width: 1024px) {
            .public-main {
                padding: 30px;
            }
            
            .hero-title {
                font-size: 40px;
            }
            
            .hero-subtitle {
                font-size: 18px;
            }
            
            .stats-grid-public {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 16px;
                max-width: 900px;
            }
            
            .stat-value-public {
                font-size: 32px;
            }
            
            .events-grid-public {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 20px;
                max-width: 1200px;
            }
            
            .section-title-public {
                font-size: 24px;
            }
        }
        
        /* Mobile landscape and below */
        @media (max-width: 768px) {
            .public-navbar {
                padding: 0 20px;
                height: 60px;
            }
            
            .public-main {
                padding: 20px;
                margin-top: 60px;
            }
            
            .hero-section {
                padding: 40px 0 30px;
            }
            
            .hero-title {
                font-size: 32px;
            }
            
            .hero-subtitle {
                font-size: 16px;
                margin-bottom: 30px;
            }
            
            .stats-grid-public {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 40px;
            }
            
            .stat-card-public {
                padding: 20px;
            }
            
            .stat-value-public {
                font-size: 28px;
            }
            
            .stat-label-public {
                font-size: 12px;
            }
            
            .section-title-public {
                font-size: 22px;
            }
            
            .section-header-public {
                margin-bottom: 20px;
            }
            
            .events-grid-public {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .event-image-public {
                height: 200px;
            }
            
            .navbar-actions {
                gap: 8px;
            }
            
            .btn-nav {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            .btn-nav svg {
                width: 14px;
                height: 14px;
            }
        }
        
        /* Small mobile */
        @media (max-width: 480px) {
            .public-navbar {
                padding: 0 15px;
            }
            
            .public-main {
                padding: 15px;
            }
            
            .hero-section {
                padding: 30px 0 20px;
            }
            
            .hero-title {
                font-size: 24px;
            }
            
            .hero-subtitle {
                font-size: 14px;
                margin-bottom: 25px;
            }
            
            .stats-grid-public {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-bottom: 30px;
            }
            
            .stat-card-public {
                padding: 16px;
            }
            
            .stat-value-public {
                font-size: 24px;
            }
            
            .stat-label-public {
                font-size: 11px;
            }
            
            .section-title-public {
                font-size: 20px;
            }
            
            .event-image-public {
                height: 180px;
            }
            
            .event-content-public {
                padding: 16px;
            }
            
            .event-title-public {
                font-size: 16px;
            }
            
            .event-meta-public {
                font-size: 13px;
            }
            
            .btn-register-public {
                padding: 6px 14px;
                font-size: 13px;
            }
            
            .navbar-actions {
                gap: 6px;
            }
            
            .btn-nav {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .btn-nav span {
                display: none;
            }
            
            .btn-nav svg {
                margin: 0;
            }
        }
        
        /* Extra small devices */
        @media (max-width: 360px) {
            .hero-title {
                font-size: 22px;
            }
            
            .stat-value-public {
                font-size: 20px;
            }
            
            .event-footer-public {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
            
            .btn-register-public {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Public Navigation -->
    <nav class="public-navbar">
        <div class="navbar-brand">🎓 Evento</div>
        <div class="navbar-actions">
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn-nav btn-nav-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
                <span>Login</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/register.php" class="btn-nav btn-nav-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
                <span>Register</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="public-main">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1 class="hero-title">Discover College Events</h1>
            <p class="hero-subtitle">Join exciting events, workshops, and activities happening around campus</p>
        </div>

        <!-- Events Section -->
        <div class="section-header-public">
            <h2 class="section-title-public">Upcoming Events</h2>
        </div>

        <div class="events-grid-public">
            <?php if (count($available_events) > 0): ?>
                <?php foreach ($available_events as $event): ?>
                    <div class="event-card-public" onclick="viewEventDetails(<?php echo $event['id']; ?>)" style="cursor: pointer;">
                        <?php if (!empty($event['banner_image'])): ?>
                            <div class="event-image-public" style="background-image: url('<?php echo BASE_URL; ?>/public/uploads/events/<?php echo htmlspecialchars($event['banner_image']); ?>')"></div>
                        <?php else: ?>
                            <div class="event-image-public" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)"></div>
                        <?php endif; ?>
                        
                        <div class="event-content-public">
                            <?php if ($event['club_name']): ?>
                                <span class="event-badge"><?php echo htmlspecialchars($event['club_name']); ?></span>
                            <?php endif; ?>
                            
                            <h3 class="event-title-public"><?php echo htmlspecialchars($event['event_name']); ?></h3>
                            
                            <div class="event-meta-public">
                                <div class="event-meta-item">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <?php echo date('M d, Y', strtotime($event['event_date'])); ?> at <?php echo date('g:i A', strtotime($event['event_date'])); ?>
                                </div>
                                <div class="event-meta-item">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <?php echo htmlspecialchars($event['venue']); ?>
                                </div>
                            </div>
                            
                            <div class="event-footer-public">
                                <div class="event-capacity">
                                    <?php 
                                    $available = $event['max_participants'] - $event['current_participants'];
                                    echo $available . '/' . $event['max_participants'] . ' spots left';
                                    ?>
                                </div>
                                <button class="btn-register-public" onclick="event.stopPropagation(); registerForEvent(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['event_name'], ENT_QUOTES); ?>')">
                                    Register Now
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin: 0 auto 20px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <h3 style="color: white; margin-bottom: 10px;">No Events Available</h3>
                    <p>Check back later for upcoming events</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistics -->
        <div class="stats-grid-public" style="margin-top: 60px;">
            <div class="stat-card-public">
                <div class="stat-value-public"><?php echo $stats['upcoming_events']; ?></div>
                <div class="stat-label-public">Upcoming Events</div>
            </div>
            <div class="stat-card-public">
                <div class="stat-value-public"><?php echo $stats['active_clubs']; ?></div>
                <div class="stat-label-public">Active Clubs</div>
            </div>
            <div class="stat-card-public">
                <div class="stat-value-public"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label-public">Students</div>
            </div>
            <div class="stat-card-public">
                <div class="stat-value-public"><?php echo $stats['total_registrations']; ?></div>
                <div class="stat-label-public">Registrations</div>
            </div>
        </div>
    </main>

    <script src="<?php echo assetUrl('js/toast.js'); ?>"></script>
    <script>
        function viewEventDetails(eventId) {
            window.location.href = '<?php echo BASE_URL; ?>/event-details-public.php?id=' + eventId;
        }
        
        function registerForEvent(eventId, eventName) {
            // Store the event they want to register for
            sessionStorage.setItem('pending_event_registration', eventId);
            sessionStorage.setItem('pending_event_name', eventName);
            
            // Redirect to login/register
            showToast('Please login or register to participate in: ' + eventName, 'info');
            
            setTimeout(() => {
                window.location.href = '<?php echo BASE_URL; ?>/login.php?redirect=event&event_id=' + eventId;
            }, 1500);
        }
    </script>
</body>
</html>
