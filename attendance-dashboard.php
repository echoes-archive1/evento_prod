<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/middleware/Auth.php';

// Require admin or faculty role
Auth::requireAnyRole(['admin', 'faculty', 'club_leader']);

$current_user = $_SESSION['user'];
$db = Database::getInstance()->getConnection();

// Get events based on user role
$events_sql = "";
$params = [];

if ($current_user['role'] === 'admin') {
    // Admin can see all events
    $events_sql = "
        SELECT e.*, c.club_name, u.full_name as creator_name,
               COUNT(er.id) as total_registrations,
               COUNT(CASE WHEN er.attendance_status = 'attended' THEN 1 END) as attended_count,
               COUNT(CASE WHEN er.attendance_status = 'absent' THEN 1 END) as absent_count,
               COUNT(CASE WHEN er.attendance_status = 'registered' THEN 1 END) as pending_count
        FROM events e
        LEFT JOIN clubs c ON e.club_id = c.id
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN event_registrations er ON e.id = er.event_id
        WHERE e.status IN ('approved', 'active')
        GROUP BY e.id
        ORDER BY e.event_date DESC
    ";
} else if ($current_user['role'] === 'faculty') {
    // Faculty can see their own events
    $events_sql = "
        SELECT e.*, c.club_name, u.full_name as creator_name,
               COUNT(er.id) as total_registrations,
               COUNT(CASE WHEN er.attendance_status = 'attended' THEN 1 END) as attended_count,
               COUNT(CASE WHEN er.attendance_status = 'absent' THEN 1 END) as absent_count,
               COUNT(CASE WHEN er.attendance_status = 'registered' THEN 1 END) as pending_count
        FROM events e
        LEFT JOIN clubs c ON e.club_id = c.id
        LEFT JOIN users u ON e.created_by = u.id
        LEFT JOIN event_registrations er ON e.id = er.event_id
        WHERE e.created_by = :user_id AND e.status IN ('approved', 'active')
        GROUP BY e.id
        ORDER BY e.event_date DESC
    ";
    $params['user_id'] = $current_user['id'];
} else if ($current_user['role'] === 'club_leader') {
    // Club leaders can see their club's events
    $club_sql = "SELECT club_id FROM club_members WHERE user_id = :user_id AND role = 'leader'";
    $club_stmt = $db->prepare($club_sql);
    $club_stmt->execute(['user_id' => $current_user['id']]);
    $club = $club_stmt->fetch();
    
    if ($club) {
        $events_sql = "
            SELECT e.*, c.club_name, u.full_name as creator_name,
                   COUNT(er.id) as total_registrations,
                   COUNT(CASE WHEN er.attendance_status = 'attended' THEN 1 END) as attended_count,
                   COUNT(CASE WHEN er.attendance_status = 'absent' THEN 1 END) as absent_count,
                   COUNT(CASE WHEN er.attendance_status = 'registered' THEN 1 END) as pending_count
            FROM events e
            LEFT JOIN clubs c ON e.club_id = c.id
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN event_registrations er ON e.id = er.event_id
            WHERE e.club_id = :club_id AND e.status IN ('approved', 'active')
            GROUP BY e.id
            ORDER BY e.event_date DESC
        ";
        $params['club_id'] = $club['club_id'];
    }
}

$events = [];
if (!empty($events_sql)) {
    $stmt = $db->prepare($events_sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
}

// Get overall statistics
$total_events = count($events);
$total_registrations = array_sum(array_column($events, 'total_registrations'));
$total_attended = array_sum(array_column($events, 'attended_count'));
$average_attendance = $total_registrations > 0 ? round(($total_attended / $total_registrations) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <style>
        .attendance-dashboard {
            padding: 2rem;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .dashboard-actions {
            display: flex;
            gap: 1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .events-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .event-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .event-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .event-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .attendance-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .attendance-stat {
            text-align: center;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius);
        }
        
        .attendance-stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .attendance-stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .event-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success), var(--primary));
            transition: width 0.3s ease;
        }
        
        .filter-section {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-controls {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            position: relative;
            background: var(--glass-bg);
            margin: 5% auto;
            padding: 2rem;
            width: 90%;
            max-width: 800px;
            border-radius: var(--radius);
            border: 1px solid var(--glass-border);
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            font-size: 2rem;
            cursor: pointer;
            color: var(--text-secondary);
        }
        
        .attendee-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .attendee-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .attendee-info {
            flex: 1;
        }
        
        .attendee-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .attendee-details {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-attended {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-registered {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
        }
        
        .status-absent {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
        }
        
        @media (max-width: 768px) {
            .attendance-dashboard {
                padding: 1rem;
            }
            
            .dashboard-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .dashboard-actions {
                justify-content: center;
            }
            
            .filter-controls {
                justify-content: center;
            }
            
            .event-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <a href="<?php echo BASE_URL; ?>">📅 <?php echo APP_NAME; ?></a>
        </div>
        <div class="nav-links">
            <a href="<?php echo BASE_URL . '/' . strtolower($current_user['role']); ?>/dashboard.php">Dashboard</a>
            <a href="<?php echo BASE_URL; ?>/logout.php">Logout</a>
        </div>
    </nav>

    <div class="attendance-dashboard">
        <div class="dashboard-header">
            <div>
                <h1>📊 Attendance Dashboard</h1>
                <p>Track and manage event attendance</p>
            </div>
            <div class="dashboard-actions">
                <a href="<?php echo BASE_URL; ?>/qr-scanner.php" class="btn btn-primary">📷 QR Scanner</a>
                <button onclick="exportAllData()" class="btn btn-outline">📊 Export All</button>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_events; ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_registrations; ?></div>
                <div class="stat-label">Total Registrations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_attended; ?></div>
                <div class="stat-label">Total Attended</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $average_attendance; ?>%</div>
                <div class="stat-label">Average Attendance</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <h3>Filters</h3>
            <div class="filter-controls">
                <select id="statusFilter" onchange="filterEvents()">
                    <option value="">All Statuses</option>
                    <option value="upcoming">Upcoming Events</option>
                    <option value="ongoing">Ongoing Events</option>
                    <option value="completed">Completed Events</option>
                </select>
                
                <input type="date" id="dateFilter" onchange="filterEvents()" />
                
                <input type="text" id="searchFilter" placeholder="Search events..." 
                       onkeyup="filterEvents()" style="flex: 1; min-width: 200px;" />
                
                <button onclick="resetFilters()" class="btn btn-outline">Reset</button>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="events-grid" id="eventsGrid">
            <?php foreach ($events as $event): 
                $attendance_rate = $event['total_registrations'] > 0 
                    ? round(($event['attended_count'] / $event['total_registrations']) * 100, 1) 
                    : 0;
                
                $event_status = 'upcoming';
                $event_date = strtotime($event['event_date']);
                $now = time();
                
                if ($event_date < $now - 3600) { // More than 1 hour past
                    $event_status = 'completed';
                } else if ($event_date <= $now + 3600) { // Within 1 hour
                    $event_status = 'ongoing';
                }
            ?>
                <div class="event-card" data-status="<?php echo $event_status; ?>" 
                     data-date="<?php echo date('Y-m-d', $event_date); ?>"
                     data-name="<?php echo strtolower($event['event_name']); ?>">
                    
                    <div class="event-header">
                        <div>
                            <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                            <div class="event-meta">
                                <?php echo date('F j, Y g:i A', strtotime($event['event_date'])); ?> • 
                                <?php echo htmlspecialchars($event['venue']); ?>
                                <?php if ($event['club_name']): ?>
                                    • <?php echo htmlspecialchars($event['club_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?php echo $event_status; ?>">
                            <?php echo ucfirst($event_status); ?>
                        </span>
                    </div>

                    <div class="attendance-stats">
                        <div class="attendance-stat">
                            <div class="attendance-stat-number" style="color: var(--primary);">
                                <?php echo $event['total_registrations']; ?>
                            </div>
                            <div class="attendance-stat-label">Registered</div>
                        </div>
                        <div class="attendance-stat">
                            <div class="attendance-stat-number" style="color: var(--success);">
                                <?php echo $event['attended_count']; ?>
                            </div>
                            <div class="attendance-stat-label">Attended</div>
                        </div>
                        <div class="attendance-stat">
                            <div class="attendance-stat-number" style="color: var(--error);">
                                <?php echo $event['absent_count']; ?>
                            </div>
                            <div class="attendance-stat-label">Absent</div>
                        </div>
                        <div class="attendance-stat">
                            <div class="attendance-stat-number" style="color: var(--warning);">
                                <?php echo $event['pending_count']; ?>
                            </div>
                            <div class="attendance-stat-label">Pending</div>
                        </div>
                    </div>

                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $attendance_rate; ?>%;"></div>
                    </div>
                    <div style="text-align: center; font-size: 0.875rem; color: var(--text-secondary);">
                        Attendance Rate: <?php echo $attendance_rate; ?>%
                    </div>

                    <div class="event-actions">
                        <button onclick="viewAttendees(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['event_name']); ?>')" 
                                class="btn btn-outline">👥 View Attendees</button>
                        
                        <a href="<?php echo BASE_URL; ?>/qr-scanner.php?event_id=<?php echo $event['id']; ?>" 
                           class="btn btn-primary">📷 Scan QR</a>
                        
                        <button onclick="exportEventData(<?php echo $event['id']; ?>)" 
                                class="btn btn-secondary">📊 Export</button>
                        
                        <?php if ($event_status !== 'completed'): ?>
                            <button onclick="markBulkAttendance(<?php echo $event['id']; ?>)" 
                                    class="btn btn-success">✅ Bulk Mark</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($events)): ?>
            <div class="empty-state">
                <h3>No Events Found</h3>
                <p>No events are available for attendance tracking.</p>
                <?php if ($current_user['role'] !== 'student'): ?>
                    <a href="<?php echo BASE_URL . '/' . strtolower($current_user['role']); ?>/create-event.php" 
                       class="btn btn-primary">Create New Event</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Attendees Modal -->
    <div id="attendeesModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Event Attendees</h2>
            
            <div style="margin: 1rem 0;">
                <select id="attendeeFilter" onchange="filterAttendees()">
                    <option value="">All Participants</option>
                    <option value="attended">Attended</option>
                    <option value="registered">Registered</option>
                    <option value="absent">Absent</option>
                </select>
            </div>
            
            <div class="attendee-list" id="attendeeList">
                <!-- Attendee list will be populated dynamically -->
            </div>
        </div>
    </div>

    <script>
        let currentEventId = null;
        let allAttendees = [];

        function filterEvents() {
            const statusFilter = document.getElementById('statusFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
            
            const eventCards = document.querySelectorAll('.event-card');
            
            eventCards.forEach(card => {
                let show = true;
                
                // Status filter
                if (statusFilter && card.dataset.status !== statusFilter) {
                    show = false;
                }
                
                // Date filter
                if (dateFilter && card.dataset.date !== dateFilter) {
                    show = false;
                }
                
                // Search filter
                if (searchFilter && !card.dataset.name.includes(searchFilter)) {
                    show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }

        function resetFilters() {
            document.getElementById('statusFilter').value = '';
            document.getElementById('dateFilter').value = '';
            document.getElementById('searchFilter').value = '';
            filterEvents();
        }

        async function viewAttendees(eventId, eventName) {
            currentEventId = eventId;
            document.getElementById('modalTitle').textContent = `${eventName} - Attendees`;
            
            try {
                const response = await fetch(`/api/attendance.php?action=event_attendees&event_id=${eventId}`);
                const result = await response.json();
                
                if (result.success) {
                    allAttendees = result.data;
                    displayAttendees(allAttendees);
                    document.getElementById('attendeesModal').style.display = 'block';
                } else {
                    alert('Failed to load attendees: ' + result.error);
                }
            } catch (error) {
                alert('Error loading attendees');
                console.error(error);
            }
        }

        function displayAttendees(attendees) {
            const attendeeList = document.getElementById('attendeeList');
            
            if (attendees.length === 0) {
                attendeeList.innerHTML = '<p style="text-align: center; padding: 2rem;">No attendees found</p>';
                return;
            }
            
            attendeeList.innerHTML = attendees.map(attendee => `
                <div class="attendee-item">
                    <div class="attendee-info">
                        <div class="attendee-name">${escapeHtml(attendee.full_name)}</div>
                        <div class="attendee-details">
                            ${attendee.roll_number} • ${attendee.department} • ${attendee.email}
                            ${attendee.checked_in_at ? '<br>Checked in: ' + new Date(attendee.checked_in_at).toLocaleString() : ''}
                        </div>
                    </div>
                    <div>
                        <span class="status-badge status-${attendee.attendance_status}">
                            ${attendee.attendance_status.charAt(0).toUpperCase() + attendee.attendance_status.slice(1)}
                        </span>
                    </div>
                </div>
            `).join('');
        }

        function filterAttendees() {
            const filter = document.getElementById('attendeeFilter').value;
            const filteredAttendees = filter 
                ? allAttendees.filter(a => a.attendance_status === filter)
                : allAttendees;
            displayAttendees(filteredAttendees);
        }

        function closeModal() {
            document.getElementById('attendeesModal').style.display = 'none';
            currentEventId = null;
            allAttendees = [];
        }

        async function exportEventData(eventId) {
            try {
                window.open(`/api/attendance.php?action=attendance_export&event_id=${eventId}&format=csv`, '_blank');
            } catch (error) {
                alert('Export failed');
            }
        }

        async function exportAllData() {
            // This would require a new API endpoint for bulk export
            alert('Bulk export feature coming soon!');
        }

        async function markBulkAttendance(eventId) {
            if (!confirm('Mark all registered participants as attended?')) {
                return;
            }
            
            try {
                const response = await fetch(`/api/attendance.php?action=event_attendees&event_id=${eventId}&status=registered`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    const registrationIds = result.data.map(attendee => attendee.registration_id);
                    
                    const bulkResponse = await fetch('/api/attendance.php?action=bulk_mark', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            registration_ids: registrationIds,
                            status: 'attended'
                        })
                    });
                    
                    const bulkResult = await bulkResponse.json();
                    if (bulkResult.success) {
                        alert(`Successfully marked ${bulkResult.data.updated_count} participants as attended`);
                        location.reload();
                    } else {
                        alert('Bulk operation failed: ' + bulkResult.error);
                    }
                } else {
                    alert('No registered participants found');
                }
            } catch (error) {
                alert('Bulk operation failed');
                console.error(error);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('attendeesModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>