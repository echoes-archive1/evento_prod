<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireAuth();

$user = Auth::user();
$db = Database::getInstance()->getConnection();

// Get event ID
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$event_id) {
    header('Location: events.php');
    exit;
}

// Get event details
$sql = "
    SELECT e.*, c.club_name, c.club_logo, u.full_name as creator_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND user_id = :user_id) as is_registered
    FROM events e
    LEFT JOIN clubs c ON e.club_id = c.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id = :event_id
    AND e.status = 'approved'
";
$stmt = $db->prepare($sql);
$stmt->execute(['event_id' => $event_id, 'user_id' => Auth::id()]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: events.php');
    exit;
}

// Check if registration is still open
$registration_open = strtotime($event['registration_deadline']) > time();
$event_passed = strtotime($event['event_date']) < time();
$spots_available = $event['max_participants'] > $event['current_participants'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['event_name']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <style>
        .event-detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .event-hero {
            position: relative;
            height: 400px;
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 30px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: 1px solid var(--glass-border);
        }
        
        .event-hero-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .event-hero-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.95), transparent);
            padding: 40px;
            color: var(--text-primary);
        }
        
        .event-hero-title {
            font-size: 36px;
            font-weight: 700;
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }
        
        .event-hero-club {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            border: 1px solid var(--glass-border);
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .detail-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            border-radius: var(--radius);
            padding: 30px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px var(--glass-shadow);
        }
        
        .detail-section {
            margin-bottom: 30px;
        }
        
        .detail-section:last-child {
            margin-bottom: 0;
        }
        
        .detail-section-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 15px 0;
            color: var(--primary-light);
        }
        
        .detail-info-grid {
            display: grid;
            gap: 15px;
        }
        
        .detail-info-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: var(--radius-sm);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        
        .detail-info-item:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: var(--glass-border);
        }
        
        .detail-info-icon {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
            color: var(--primary-light);
        }
        
        .detail-info-content {
            flex: 1;
        }
        
        .detail-info-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-info-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .registration-card {
            position: sticky;
            top: 20px;
        }
        
        .registration-status {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid;
        }
        
        .registration-status.open {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .registration-status.closed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .registration-status.registered {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
            border-color: rgba(59, 130, 246, 0.3);
        }
        
        .registration-status-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 10px;
        }
        
        .registration-status-text {
            font-size: 18px;
            font-weight: 600;
            margin: 10px 0;
        }
        
        .registration-status-subtext {
            font-size: 14px;
            opacity: 0.8;
        }
        
        .participants-info {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            border: 1px solid var(--glass-border);
        }
        
        .participants-info-item {
            text-align: center;
        }
        
        .participants-info-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-light);
        }
        
        .participants-info-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-register {
            width: 100%;
            padding: 16px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .detail-card p {
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .registration-card {
                position: static;
            }
            
            .event-hero {
                height: 300px;
            }
            
            .event-hero-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="public-navbar">
        <div class="navbar-brand">🎓 Evento</div>
        <div class="navbar-actions" style="display: flex; gap: clamp(8px, 1.5vw, 16px); align-items: center;">
            <span style="color: rgba(255, 255, 255, 0.9); font-size: clamp(0.85rem, 1.5vw, 1rem); font-weight: 500;"><?php echo Security::sanitize($user['full_name']); ?></span>
            <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #60a5fa, #a78bfa); display: flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 700; color: white; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);">
                <?php 
                $nameParts = explode(' ', $user['full_name']);
                $initials = strtoupper(substr($nameParts[0], 0, 1));
                if (count($nameParts) > 1) {
                    $initials .= strtoupper(substr(end($nameParts), 0, 1));
                }
                echo $initials;
                ?>
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
            
            <a href="my-events.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                <span>My Events</span>
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
        <header class="header">
            <div class="header-left">
                <h1 class="page-title">Event Details</h1>
                <p class="page-subtitle">Complete event information</p>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                        $nameParts = explode(' ', $user['full_name']);
                        $initials = strtoupper(substr($nameParts[0], 0, 1));
                        if (count($nameParts) > 1) {
                            $initials .= strtoupper(substr(end($nameParts), 0, 1));
                        }
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo Security::sanitize($user['full_name']); ?></div>
                        <div class="user-role"><?php echo ucfirst($user['roles']); ?></div>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="event-detail-container">
            <!-- Event Hero -->
            <div class="event-hero">
                <?php if (!empty($event['banner_image'])): ?>
                    <img src="<?php echo BASE_URL; ?>/public/uploads/events/<?php echo htmlspecialchars($event['banner_image']); ?>" alt="Event Banner" class="event-hero-image">
                <?php endif; ?>
                <div class="event-hero-overlay">
                    <h1 class="event-hero-title"><?php echo htmlspecialchars($event['event_name']); ?></h1>
                    <?php if ($event['club_name']): ?>
                        <div class="event-hero-club">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span><?php echo htmlspecialchars($event['club_name']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Event Details Grid -->
            <div class="detail-grid">
                <!-- Left Column - Event Information -->
                <div class="detail-card">
                    <!-- Description -->
                    <div class="detail-section">
                        <h2 class="detail-section-title">About This Event</h2>
                        <p style="line-height: 1.8; color: var(--text-secondary);">
                            <?php echo nl2br(htmlspecialchars($event['event_description'])); ?>
                        </p>
                    </div>

                    <!-- Event Information -->
                    <div class="detail-section">
                        <h2 class="detail-section-title">Event Information</h2>
                        <div class="detail-info-grid">
                            <div class="detail-info-item">
                                <svg class="detail-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <div class="detail-info-content">
                                    <div class="detail-info-label">Event Date & Time</div>
                                    <div class="detail-info-value"><?php echo date('l, F j, Y - g:i A', strtotime($event['event_date'])); ?></div>
                                </div>
                            </div>

                            <div class="detail-info-item">
                                <svg class="detail-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <div class="detail-info-content">
                                    <div class="detail-info-label">Venue</div>
                                    <div class="detail-info-value"><?php echo htmlspecialchars($event['venue']); ?></div>
                                </div>
                            </div>

                            <?php if ($event['department']): ?>
                            <div class="detail-info-item">
                                <svg class="detail-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <div class="detail-info-content">
                                    <div class="detail-info-label">Department</div>
                                    <div class="detail-info-value"><?php echo htmlspecialchars($event['department']); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="detail-info-item">
                                <svg class="detail-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <div class="detail-info-content">
                                    <div class="detail-info-label">Organized By</div>
                                    <div class="detail-info-value"><?php echo htmlspecialchars($event['creator_name']); ?></div>
                                </div>
                            </div>

                            <div class="detail-info-item">
                                <svg class="detail-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="detail-info-content">
                                    <div class="detail-info-label">Registration Deadline</div>
                                    <div class="detail-info-value"><?php echo date('F j, Y - g:i A', strtotime($event['registration_deadline'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Registration -->
                <div>
                    <div class="detail-card registration-card">
                        <!-- Registration Status -->
                        <?php if ($event['is_registered']): ?>
                            <div class="registration-status registered">
                                <svg class="registration-status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="registration-status-text">Already Registered</div>
                                <div class="registration-status-subtext">You're all set for this event!</div>
                            </div>
                            <a href="my-events.php" class="btn btn-primary btn-register">View My Events</a>
                        <?php elseif ($event_passed): ?>
                            <div class="registration-status closed">
                                <svg class="registration-status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="registration-status-text">Event Has Passed</div>
                                <div class="registration-status-subtext">This event has already taken place</div>
                            </div>
                        <?php elseif (!$registration_open): ?>
                            <div class="registration-status closed">
                                <svg class="registration-status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                <div class="registration-status-text">Registration Closed</div>
                                <div class="registration-status-subtext">Deadline has passed</div>
                            </div>
                        <?php elseif (!$spots_available): ?>
                            <div class="registration-status closed">
                                <svg class="registration-status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <div class="registration-status-text">Event Full</div>
                                <div class="registration-status-subtext">All spots have been filled</div>
                            </div>
                        <?php else: ?>
                            <div class="registration-status open">
                                <svg class="registration-status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="registration-status-text">Registration Open</div>
                                <div class="registration-status-subtext">Secure your spot now!</div>
                            </div>
                        <?php endif; ?>

                        <!-- Participants Info -->
                        <div class="participants-info">
                            <div class="participants-info-item">
                                <div class="participants-info-value"><?php echo $event['current_participants']; ?></div>
                                <div class="participants-info-label">Registered</div>
                            </div>
                            <div class="participants-info-item">
                                <div class="participants-info-value"><?php echo $event['max_participants']; ?></div>
                                <div class="participants-info-label">Capacity</div>
                            </div>
                            <div class="participants-info-item">
                                <div class="participants-info-value"><?php echo max(0, $event['max_participants'] - $event['current_participants']); ?></div>
                                <div class="participants-info-label">Available</div>
                            </div>
                        </div>

                        <!-- Register Button -->
                        <?php if (!$event['is_registered'] && $registration_open && !$event_passed && $spots_available): ?>
                            <button class="btn btn-primary btn-register" id="registerBtn">
                                <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Register Now
                            </button>
                        <?php endif; ?>

                        <a href="events.php" class="btn btn-secondary" style="width: 100%; margin-top: 10px;">
                            <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back to Events
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js"></script>
    <script>
        const registerBtn = document.getElementById('registerBtn');
        if (registerBtn) {
            registerBtn.addEventListener('click', async function() {
                if (confirm('Are you sure you want to register for this event?')) {
                    // Show loading state
                    const originalHTML = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<svg class="btn-icon animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Registering...';
                    this.style.opacity = '0.7';
                    
                    try {
                        const response = await fetch('<?php echo BASE_URL; ?>/api/register-event.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({event_id: <?php echo $event_id; ?>})
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.innerHTML = '<svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Registered!';
                            this.style.background = 'linear-gradient(135deg, #4ade80 0%, #22c55e 100%)';
                            showToast('Successfully registered!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            this.innerHTML = originalHTML;
                            this.disabled = false;
                            this.style.opacity = '1';
                            showToast(data.message || 'Registration failed', 'error');
                        }
                    } catch (error) {
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                        this.style.opacity = '1';
                        showToast('An error occurred. Please try again.', 'error');
                    }
                }
            });
        }
    </script>
</body>
</html>
