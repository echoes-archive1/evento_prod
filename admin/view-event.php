<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();
$error = '';
$event = null;
$registrations = [];

// Get event ID
$event_id = (int)($_GET['id'] ?? 0);

if ($event_id <= 0) {
    header('Location: events.php');
    exit;
}

// Fetch event details
try {
    $sql = "
        SELECT e.*, 
               c.club_name,
               u.full_name as creator_name,
               u.email as creator_email,
               admin_user.full_name as approver_name,
               (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as total_registrations
        FROM events e
        LEFT JOIN clubs c ON e.club_id = c.id
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN users admin_user ON e.approved_by = admin_user.id
        WHERE e.id = :event_id
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(['event_id' => $event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: events.php');
        exit;
    }
    
    // Fetch registrations
    $reg_sql = "
        SELECT er.*, 
               u.full_name,
               u.email,
               u.department
        FROM event_registrations er
        JOIN users u ON er.user_id = u.id
        WHERE er.event_id = :event_id
        ORDER BY er.registration_date DESC
    ";
    $reg_stmt = $db->prepare($reg_sql);
    $reg_stmt->execute(['event_id' => $event_id]);
    $registrations = $reg_stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Event view error: " . $e->getMessage());
    error_log("Event view trace: " . $e->getTraceAsString());
    $error = 'Failed to load event details: ' . $e->getMessage();
}

$page_title = 'Event Details';
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
        .event-detail-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .event-header {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(168, 85, 247, 0.15));
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(99, 102, 241, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .event-title-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .club-logo-large {
            width: 80px;
            height: 80px;
            border-radius: 15px;
            object-fit: cover;
            border: 2px solid rgba(99, 102, 241, 0.3);
        }
        
        .event-title h1 {
            font-size: 2rem;
            margin: 0 0 0.5rem 0;
            color: #f1f5f9;
        }
        
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            color: #cbd5e1;
            font-size: 0.9rem;
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .event-meta-item svg {
            width: 18px;
            height: 18px;
        }
        
        .event-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .event-main-info,
        .event-sidebar {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .info-section {
            margin-bottom: 2rem;
        }
        
        .info-section:last-child {
            margin-bottom: 0;
        }
        
        .info-section h3 {
            font-size: 1.1rem;
            margin: 0 0 1rem 0;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-section h3 svg {
            width: 20px;
            height: 20px;
            color: #818cf8;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #94a3b8;
        }
        
        .info-value {
            color: #e2e8f0;
            text-align: right;
        }
        
        .event-description {
            line-height: 1.6;
            color: #cbd5e1;
        }
        
        .status-badge-large {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .registrations-section {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .registrations-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .registrations-header h2 {
            font-size: 1.3rem;
            margin: 0;
            color: #f1f5f9;
        }
        
        .reg-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reg-table thead {
            background: rgba(15, 23, 42, 0.5);
        }
        
        .reg-table th,
        .reg-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }
        
        .reg-table th {
            font-weight: 600;
            color: #cbd5e1;
            font-size: 0.9rem;
        }
        
        .reg-table td {
            color: #e2e8f0;
        }
        
        .reg-table tbody tr:hover {
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
        
        .no-registrations {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }
        
        @media (max-width: 968px) {
            .event-grid {
                grid-template-columns: 1fr;
            }
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
            
            <a href="events.php" class="nav-item active">
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
                <h1 class="page-title">Event Details</h1>
                <p class="page-subtitle">Complete event information</p>
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

        <div class="event-detail-container">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($event): ?>
                <a href="events.php" class="back-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Events
                </a>
                
                <!-- Event Header -->
                <div class="event-header">
                    <div class="event-title-section">
                        <div class="event-title">
                            <h1><?php echo htmlspecialchars($event['event_name']); ?></h1>
                            <div class="event-meta">
                                <span class="event-meta-item">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <?php echo htmlspecialchars($event['club_name'] ?? 'N/A'); ?>
                                </span>
                                <span class="event-meta-item">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Created by <?php echo htmlspecialchars($event['creator_name']); ?>
                                </span>
                                <span class="event-meta-item">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Created <?php echo date('M d, Y', strtotime($event['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Event Grid -->
                <div class="event-grid">
                    <!-- Main Info -->
                    <div class="event-main-info">
                        <div class="info-section">
                            <h3>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
                                </svg>
                                Description
                            </h3>
                            <div class="event-description">
                                <?php echo nl2br(htmlspecialchars($event['description'] ?? 'No description available')); ?>
                            </div>
                        </div>

                        <?php if (!empty($event['requirements'])): ?>
                        <div class="info-section">
                            <h3>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                                Requirements
                            </h3>
                            <div class="event-description">
                                <?php echo nl2br(htmlspecialchars($event['requirements'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($event['status'] === 'rejected' && !empty($event['rejection_reason'])): ?>
                        <div class="info-section">
                            <h3>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Rejection Reason
                            </h3>
                            <div class="event-description" style="color: #dc2626;">
                                <?php echo nl2br(htmlspecialchars($event['rejection_reason'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="event-sidebar">
                        <div class="info-section">
                            <h3>Event Information</h3>
                            <div class="info-row">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <span class="status-badge-large status-<?php echo $event['status']; ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Event Date</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Time</span>
                                <span class="info-value"><?php echo !empty($event['event_time']) ? date('g:i A', strtotime($event['event_time'])) : 'Not specified'; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Venue</span>
                                <span class="info-value"><?php echo htmlspecialchars($event['venue']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Department</span>
                                <span class="info-value"><?php echo htmlspecialchars($event['department']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Event Type</span>
                                <span class="info-value"><?php echo !empty($event['event_type']) ? ucfirst(str_replace('_', ' ', $event['event_type'])) : 'Not specified'; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Max Participants</span>
                                <span class="info-value"><?php echo $event['max_participants'] ?: 'Unlimited'; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Registration Fee</span>
                                <span class="info-value">₹<?php echo number_format($event['registration_fee'] ?? 0, 2); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Registrations</span>
                                <span class="info-value"><?php echo $event['total_registrations']; ?></span>
                            </div>
                            <?php if ($event['is_featured']): ?>
                            <div class="info-row">
                                <span class="info-label">Featured</span>
                                <span class="info-value" style="color: #f59e0b;">⭐ Yes</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($event['status'] === 'approved' && !empty($event['approver_name'])): ?>
                        <div class="info-section">
                            <h3>Approval Details</h3>
                            <div class="info-row">
                                <span class="info-label">Approved By</span>
                                <span class="info-value"><?php echo htmlspecialchars($event['approver_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Approved On</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($event['approved_at'])); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="info-section">
                            <h3>Contact</h3>
                            <div class="info-row">
                                <span class="info-label">Creator</span>
                                <span class="info-value"><?php echo htmlspecialchars($event['creator_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value" style="font-size: 0.85rem;">
                                    <a href="mailto:<?php echo htmlspecialchars($event['creator_email']); ?>" style="color: #6366f1;">
                                        <?php echo htmlspecialchars($event['creator_email']); ?>
                                    </a>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Registrations -->
                <div class="registrations-section">
                    <div class="registrations-header">
                        <h2>Registrations (<?php echo count($registrations); ?>)</h2>
                        <?php if (count($registrations) > 0): ?>
                            <a href="<?php echo BASE_URL; ?>/api/export.php?type=registrations&event_id=<?php echo $event_id; ?>" class="btn btn-primary">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export CSV
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (count($registrations) > 0): ?>
                        <table class="reg-table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>QR Code</th>
                                    <th></th>Status</th>
                                    <th>Registered On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registrations as $reg): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reg['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                        <td><?php echo htmlspecialchars($reg['department']); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <img src="https://chart.googleapis.com/chart?chs=80x80&cht=qr&chl=<?php echo urlencode($reg['qr_code']); ?>&choe=UTF-8" 
                                                     alt="QR Code" 
                                                     style="border: 2px solid rgba(99, 102, 241, 0.3); border-radius: 8px; cursor: pointer;"
                                                     onclick="showQRModal('<?php echo htmlspecialchars($reg['qr_code']); ?>', '<?php echo htmlspecialchars($reg['full_name']); ?>')" 
                                                     title="Click to enlarge" />
                                                <code style="font-size: 0.75rem; color: #818cf8; background: rgba(99, 102, 241, 0.1); padding: 0.25rem 0.5rem; border-radius: 4px;"><?php echo htmlspecialchars($reg['qr_code']); ?></code>
                                            </div>
                                        </td>
                                        <td><span class="status-badge status-<?php echo $reg['attendance_status']; ?>">
                                                <?php echo ucfirst($reg['attendance_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($reg['registration_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-registrations">
                            <svg style="width: 64px; height: 64px; margin-bottom: 1rem; opacity: 0.3;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <p>No registrations yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- QR Code Modal -->
    <div id="qrModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.85); z-index: 9999; align-items: center; justify-content: center;" onclick="closeQRModal()">
        <div style="background: rgba(30, 41, 59, 0.95); padding: 2rem; border-radius: 20px; border: 2px solid rgba(99, 102, 241, 0.3); max-width: 400px; text-align: center; backdrop-filter: blur(10px);" onclick="event.stopPropagation()">
            <h3 style="margin: 0 0 1rem 0; color: #f1f5f9;" id="qrModalTitle">QR Code</h3>
            <div style="background: white; padding: 1rem; border-radius: 12px; margin-bottom: 1rem;">
                <img id="qrModalImage" src="" alt="QR Code" style="display: block; width: 250px; height: 250px; margin: 0 auto;" />
            </div>
            <code id="qrModalCode" style="display: block; font-size: 1rem; color: #818cf8; background: rgba(99, 102, 241, 0.1); padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem;"></code>
            <button onclick="closeQRModal()" class="btn btn-secondary" style="width: 100%;">Close</button>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js?v=2"></script>
    <script>
    function showQRModal(qrCode, studentName) {
        document.getElementById('qrModalTitle').textContent = 'QR Code - ' + studentName;
        document.getElementById('qrModalImage').src = 'https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=' + encodeURIComponent(qrCode) + '&choe=UTF-8';
        document.getElementById('qrModalCode').textContent = qrCode;
        document.getElementById('qrModal').style.display = 'flex';
    }
    
    function closeQRModal() {
        document.getElementById('qrModal').style.display = 'none';
    }
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeQRModal();
    });
    </script>
</body>
</html>
