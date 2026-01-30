<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireAuth();

$user = Auth::user();
$db = Database::getInstance()->getConnection();

// Get statistics
$stats_sql = "
    SELECT 
        (SELECT COUNT(*) FROM event_registrations WHERE user_id = :user_id1) as total_registered,
        (SELECT COUNT(*) FROM event_registrations WHERE user_id = :user_id2 AND attendance_status = 'attended') as total_attended,
        (SELECT COUNT(*) FROM event_registrations er JOIN events e ON er.event_id = e.id WHERE er.user_id = :user_id3 AND e.event_date >= NOW()) as upcoming_count
";
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute(['user_id1' => Auth::id(), 'user_id2' => Auth::id(), 'user_id3' => Auth::id()]);
$stats = $stats_stmt->fetch();

// Get upcoming registered events
$upcoming_sql = "
    SELECT e.*, er.registration_date, er.attendance_status, er.qr_code, c.club_name
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    LEFT JOIN clubs c ON e.club_id = c.id
    WHERE er.user_id = :user_id
    AND e.event_date >= NOW()
    ORDER BY e.event_date ASC
";
$upcoming_stmt = $db->prepare($upcoming_sql);
$upcoming_stmt->execute(['user_id' => Auth::id()]);
$upcoming_events = $upcoming_stmt->fetchAll();

// Get past registered events
$past_sql = "
    SELECT e.*, er.registration_date, er.attendance_status, er.qr_code, c.club_name
    FROM event_registrations er
    JOIN events e ON er.event_id = e.id
    LEFT JOIN clubs c ON e.club_id = c.id
    WHERE er.user_id = :user_id
    AND e.event_date < NOW()
    ORDER BY e.event_date DESC
";
$past_stmt = $db->prepare($past_sql);
$past_stmt->execute(['user_id' => Auth::id()]);
$past_events = $past_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - <?php echo APP_NAME; ?></title>
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
            <a href="dashboard.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>
            
            <a href="my-events.php" class="nav-item active">
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
                <h1 class="page-title">My Events</h1>
                <p class="page-subtitle">Track your registered events and statistics</p>
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

        <div class="content-wrapper">
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['total_registered']; ?></div>
                        <div class="stat-label">Total Registered</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-icon-success">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['total_attended']; ?></div>
                        <div class="stat-label">Events Attended</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon stat-icon-accent">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $stats['upcoming_count']; ?></div>
                        <div class="stat-label">Upcoming Events</div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events Section -->
            <section class="content-section" style="margin-top: 2rem;">
                <div class="section-header">
                    <h2 class="section-title">Upcoming Events</h2>
                </div>
                
                <?php if (count($upcoming_events) > 0): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Club</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_events as $event): ?>
                                    <tr data-event-id="<?php echo $event['id']; ?>">
                                        <td class="font-semibold"><?php echo htmlspecialchars($event['event_name']); ?></td>
                                        <td><?php echo htmlspecialchars($event['club_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($event['registration_date'])); ?></td>
                                        <td>
                                            <?php if ($event['status'] == 'approved'): ?>
                                                <span class="badge badge-success">Approved</span>
                                            <?php elseif ($event['status'] == 'pending'): ?>
                                                <span class="badge badge-warning">Pending</span>
                                            <?php else: ?>
                                                <span class="badge badge-error">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger unregister-btn" data-event-id="<?php echo $event['id']; ?>" title="Unregister from event">
                                                Unregister
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <h3>No Upcoming Events</h3>
                        <p>You haven't registered for any upcoming events.</p>
                        <a href="dashboard.php" class="btn btn-primary" style="margin-top: 1rem;">Browse Events</a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Past Events Section -->
            <section class="content-section" style="margin-top: 2rem;">
                <div class="section-header">
                    <h2 class="section-title">Past Events</h2>
                </div>
                
                <?php if (count($past_events) > 0): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Club</th>
                                    <th>Date</th>
                                    <th>Venue</th>
                                    <th>Registration Date</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($past_events as $event): ?>
                                    <tr data-event-id="<?php echo $event['id']; ?>">
                                        <td class="font-semibold"><?php echo htmlspecialchars($event['event_name']); ?></td>
                                        <td><?php echo htmlspecialchars($event['club_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($event['registration_date'])); ?></td>
                                        <td>
                                            <?php if ($event['attendance_status'] == 'attended'): ?>
                                                <span class="badge badge-success">Attended</span>
                                            <?php elseif ($event['attendance_status'] == 'absent'): ?>
                                                <span class="badge badge-error">Absent</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Not Marked</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3>No Past Events</h3>
                        <p>You haven't attended any events yet.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js"></script>
    <script>
    // Unregister from event with instant UI update
    document.querySelectorAll('.unregister-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const eventId = this.dataset.eventId;
            const button = this;
            const row = button.closest('tr');
            
            if (confirm('Are you sure you want to unregister from this event?')) {
                // Disable button immediately and show loading state
                button.disabled = true;
                button.textContent = 'Unregistering...';
                
                try {
                    const response = await fetch('<?php echo BASE_URL; ?>/api/unregister-event.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({event_id: eventId})
                    });
                    const data = await response.json();
                    if (data.success) {
                        // Instantly remove the row with animation
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-20px)';
                        
                        setTimeout(() => {
                            row.remove();
                            
                            // Check if table is now empty
                            const tbody = document.querySelector('.data-table tbody');
                            if (tbody && tbody.children.length === 0) {
                                document.querySelector('.table-container').innerHTML = `
                                    <div class="empty-state">
                                        <svg class="empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                        </svg>
                                        <h3>No Registered Events</h3>
                                        <p>You haven't registered for any events yet.</p>
                                        <a href="dashboard.php" class="btn btn-primary" style="margin-top: 1rem;">Browse Events</a>
                                    </div>
                                `;
                            }
                        }, 300);
                        
                        showToast('Successfully unregistered!', 'success');
                    } else {
                        button.disabled = false;
                        button.textContent = 'Unregister';
                        showToast(data.message || 'Unregistration failed', 'error');
                    }
                } catch (error) {
                    button.disabled = false;
                    button.textContent = 'Unregister';
                    showToast('An error occurred', 'error');
                }
            }
        });
    });
    </script>
</body>
</html>
