<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireAuth();

$user = Auth::user();
$db = Database::getInstance()->getConnection();

// Check if user should auto-register for an event (coming from public page)
$auto_register_message = '';
$auto_register_event = null;
if (isset($_SESSION['auto_register_event'])) {
    $auto_register_event = (int)$_SESSION['auto_register_event'];
    unset($_SESSION['auto_register_event']);
} elseif (isset($_GET['auto_register'])) {
    $auto_register_event = (int)$_GET['auto_register'];
}

// Get statistics
$stats_sql = "
    SELECT 
        (SELECT COUNT(*) FROM event_registrations WHERE user_id = :user_id1) as total_registered,
        (SELECT COUNT(*) FROM event_registrations WHERE user_id = :user_id2 AND attendance_status = 'attended') as total_attended,
        (SELECT COUNT(*) FROM events WHERE status = 'approved' AND event_date >= NOW()) as upcoming_events
";
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute(['user_id1' => Auth::id(), 'user_id2' => Auth::id()]);
$stats = $stats_stmt->fetch();

// Get registered events
$registered_sql = "
    SELECT e.*, er.registration_date, er.attendance_status, c.club_name
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    LEFT JOIN clubs c ON e.club_id = c.id
    WHERE er.user_id = :user_id
    ORDER BY e.event_date DESC
    LIMIT 5
";
$registered_stmt = $db->prepare($registered_sql);
$registered_stmt->execute(['user_id' => Auth::id()]);
$registered_events = $registered_stmt->fetchAll();

// Get available events
$events_sql = "
    SELECT e.*, c.club_name, u.full_name as creator_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND user_id = :user_id) as is_registered
    FROM events e
    LEFT JOIN clubs c ON e.club_id = c.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.status = 'approved' 
    AND e.event_date >= NOW()
    AND e.registration_deadline >= NOW()
    ORDER BY e.event_date ASC
";
$events_stmt = $db->prepare($events_sql);
$events_stmt->execute(['user_id' => Auth::id()]);
$available_events = $events_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
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
            <a href="dashboard.php" class="nav-item active">
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
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <h1 class="page-title">Welcome, <?php echo explode(' ', $user['full_name'])[0]; ?>! 👋</h1>
                <p class="page-subtitle">Discover and join amazing college events</p>
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
        
        <!-- Available Events Section -->
        <section class="content-section">
            <div class="section-header">
                <h2 class="section-title">Available Events to Register</h2>
            </div>
            
            <div class="events-grid">
                <?php if (empty($available_events)): ?>
                    <div class="empty-state">
                        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p>No events available at the moment</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($available_events as $event): ?>
                        <div class="event-card" data-event-id="<?php echo $event['id']; ?>">
                            <?php if ($event['banner_image']): ?>
                                <div class="event-image" style="background-image: url('<?php echo BASE_URL; ?>/public/uploads/<?php echo $event['banner_image']; ?>')"></div>
                            <?php else: ?>
                                <div class="event-image event-image-placeholder"></div>
                            <?php endif; ?>
                            
                            <div class="event-content">
                                <div class="event-header">
                                    <h3 class="event-title"><?php echo Security::sanitize($event['event_name']); ?></h3>
                                    <?php if ($event['club_name']): ?>
                                        <span class="event-badge"><?php echo Security::sanitize($event['club_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="event-description">
                                    <?php echo substr(Security::sanitize($event['event_description']), 0, 100); ?>...
                                </p>
                                
                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                    </div>
                                    
                                    <div class="event-meta-item">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <?php echo Security::sanitize($event['venue']); ?>
                                    </div>
                                </div>
                                
                                <div class="event-footer">
                                    <?php if ($event['max_participants']): ?>
                                        <div class="event-seats">
                                            <?php echo ($event['max_participants'] - $event['current_participants']); ?> / <?php echo $event['max_participants']; ?> seats
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($event['is_registered']): ?>
                                        <button class="btn btn-success btn-sm" disabled>
                                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Registered
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-primary btn-sm register-btn" data-event-id="<?php echo $event['id']; ?>">
                                            Register Now
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Recent Registrations -->
        <?php if (!empty($registered_events)): ?>
        <section class="content-section">
            <div class="section-header">
                <h2 class="section-title">My Recent Registrations</h2>
                <a href="my-events.php" class="section-link">
                    View All
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </a>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Club</th>
                            <th>Date</th>
                            <th>Registered On</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registered_events as $event): ?>
                            <tr>
                                <td><?php echo Security::sanitize($event['event_name']); ?></td>
                                <td><?php echo Security::sanitize($event['club_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($event['registration_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $event['attendance_status']; ?>">
                                        <?php echo ucfirst($event['attendance_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
    
    <div id="toast-container"></div>
    
    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js"></script>
    <script>
        // Make event cards clickable
        document.querySelectorAll('.event-card').forEach(card => {
            card.style.cursor = 'pointer';
            card.addEventListener('click', function(e) {
                // Don't navigate if clicking on register button
                if (!e.target.closest('.register-btn')) {
                    const eventId = this.dataset.eventId;
                    window.location.href = 'event-details.php?id=' + eventId;
                }
            });
        });
        
        // Event registration handler
        document.querySelectorAll('.register-btn').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.stopPropagation(); // Prevent card click event
                const eventId = this.dataset.eventId;
                
                if (confirm('Are you sure you want to register for this event?')) {
                    // Show loading state
                    const originalHTML = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<svg class="btn-icon animate-spin" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Registering...';
                    this.style.opacity = '0.7';
                    
                    try {
                        const response = await fetch('<?php echo BASE_URL; ?>/api/register-event.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ event_id: eventId })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.innerHTML = '<svg class="btn-icon" style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Registered!';
                            this.style.background = 'linear-gradient(135deg, #4ade80 0%, #22c55e 100%)';
                            showToast(data.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            this.innerHTML = originalHTML;
                            this.disabled = false;
                            this.style.opacity = '1';
                            showToast(data.message, 'error');
                        }
                    } catch (error) {
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                        this.style.opacity = '1';
                        showToast('Registration failed. Please try again.', 'error');
                    }
                }
            });
        });
        
        // Auto-register for event if coming from public page
        <?php if ($auto_register_event): ?>
            const autoRegisterEventId = <?php echo $auto_register_event; ?>;
            const autoRegisterButton = document.querySelector(`.register-btn[data-event-id="${autoRegisterEventId}"]`);
            
            if (autoRegisterButton) {
                // Scroll to the event
                autoRegisterButton.closest('.event-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Auto-click after a brief delay to show the event
                setTimeout(() => {
                    showToast('Registering you for this event...', 'info');
                    autoRegisterButton.click();
                }, 1000);
            }
        <?php endif; ?>
    </script>
</body>
</html>
