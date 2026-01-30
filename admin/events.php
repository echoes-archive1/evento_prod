<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$error = '';

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $action = $_POST['action'];
        
        try {
            if ($action === 'approve') {
                $sql = "UPDATE events SET status = 'approved', approved_by = :admin_id, approved_at = NOW() WHERE id = :event_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['admin_id' => Auth::id(), 'event_id' => $event_id]);
                
                Security::logAudit(Auth::id(), 'event_approved', 'events', $event_id);
                $success = 'Event approved successfully.';
                
            } elseif ($action === 'edit_event') {
                $event_name = Security::sanitize($_POST['event_name'] ?? '');
                $event_date = $_POST['event_date'] ?? '';
                $event_time = $_POST['event_time'] ?? '';
                $venue = Security::sanitize($_POST['venue'] ?? '');
                $event_description = Security::sanitize($_POST['description'] ?? '');
                $max_participants = (int)($_POST['max_participants'] ?? 0);
                $department = Security::sanitize($_POST['department'] ?? '');
                $registration_deadline = $_POST['registration_deadline'] ?? '';
                $club_id = $_POST['club_id'] ?? null;
                $status = $_POST['status'] ?? 'pending';
                
                // Validate inputs
                if (empty($event_name) || empty($event_date) || empty($event_time) || empty($venue)) {
                    $error = 'Please fill all required fields.';
                } else {
                    // Combine date and time into datetime format
                    $event_datetime = $event_date . ' ' . $event_time;
                    
                    // If status is being changed to approved, set approved_by and approved_at
                    if ($status === 'approved') {
                        $sql = "UPDATE events SET event_name = :event_name, event_date = :event_date, 
                                venue = :venue, event_description = :event_description, max_participants = :max_participants, 
                                department = :department, registration_deadline = :registration_deadline, club_id = :club_id,
                                status = :status, approved_by = :approved_by, approved_at = NOW()
                                WHERE id = :event_id";
                        $params = [
                            'event_name' => $event_name,
                            'event_date' => $event_datetime,
                            'venue' => $venue,
                            'event_description' => $event_description,
                            'max_participants' => $max_participants,
                            'department' => $department,
                            'registration_deadline' => $registration_deadline,
                            'club_id' => $club_id ?: null,
                            'status' => $status,
                            'approved_by' => Auth::id(),
                            'event_id' => $event_id
                        ];
                    } else {
                        $sql = "UPDATE events SET event_name = :event_name, event_date = :event_date, 
                                venue = :venue, event_description = :event_description, max_participants = :max_participants, 
                                department = :department, registration_deadline = :registration_deadline, club_id = :club_id,
                                status = :status
                                WHERE id = :event_id";
                        $params = [
                            'event_name' => $event_name,
                            'event_date' => $event_datetime,
                            'venue' => $venue,
                            'event_description' => $event_description,
                            'max_participants' => $max_participants,
                            'department' => $department,
                            'registration_deadline' => $registration_deadline,
                            'club_id' => $club_id ?: null,
                            'status' => $status,
                            'event_id' => $event_id
                        ];
                    }
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    
                    Security::logAudit(Auth::id(), 'event_updated', 'events', $event_id);
                    $success = 'Event updated successfully.';
                }
                
            } elseif ($action === 'reject') {
                $reason = Security::sanitize($_POST['reason'] ?? 'Not specified');
                $sql = "UPDATE events SET status = 'rejected', rejection_reason = :reason WHERE id = :event_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['reason' => $reason, 'event_id' => $event_id]);
                
                Security::logAudit(Auth::id(), 'event_rejected', 'events', $event_id);
                $success = 'Event rejected.';
                
            } elseif ($action === 'delete') {
                $db->beginTransaction();
                
                // Delete registrations first
                $sql = "DELETE FROM event_registrations WHERE event_id = :event_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['event_id' => $event_id]);
                
                // Delete event
                $sql = "DELETE FROM events WHERE id = :event_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['event_id' => $event_id]);
                
                $db->commit();
                
                Security::logAudit(Auth::id(), 'event_deleted', 'events', $event_id);
                $success = 'Event deleted successfully.';
                
            } elseif ($action === 'toggle_featured') {
                $sql = "UPDATE events SET is_featured = NOT is_featured WHERE id = :event_id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['event_id' => $event_id]);
                
                Security::logAudit(Auth::id(), 'event_featured_toggle', 'events', $event_id);
                $success = 'Event featured status updated.';
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Event management error: " . $e->getMessage());
            $error = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$club_filter = $_GET['club'] ?? '';
$department_filter = $_GET['department'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "e.status = :status";
    $params['status'] = $status_filter;
}

if (!empty($club_filter)) {
    $where_conditions[] = "e.club_id = :club_id";
    $params['club_id'] = $club_filter;
}

if (!empty($department_filter)) {
    $where_conditions[] = "e.department = :department";
    $params['department'] = $department_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(e.event_name LIKE :search OR e.venue LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($date_filter)) {
    if ($date_filter === 'upcoming') {
        $where_conditions[] = "e.event_date >= NOW()";
    } elseif ($date_filter === 'past') {
        $where_conditions[] = "e.event_date < NOW()";
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get events
$events_sql = "
    SELECT e.*, 
           c.club_name,
           u.full_name as creator_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as total_registrations,
           admin_user.full_name as approver_name
    FROM events e
    LEFT JOIN clubs c ON e.club_id = c.id
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN users admin_user ON e.approved_by = admin_user.id
    $where_clause
    ORDER BY e.created_at DESC
";
$events_stmt = $db->prepare($events_sql);
$events_stmt->execute($params);
$events = $events_stmt->fetchAll();

// Get clubs for filter
$clubs_sql = "SELECT id, club_name FROM clubs WHERE is_active = 1 ORDER BY club_name";
$clubs_stmt = $db->query($clubs_sql);
$clubs = $clubs_stmt->fetchAll();

// Get departments
$departments = ['Computer Science', 'Information Technology', 'Electronics', 'Mechanical', 'Civil', 'Electrical', 'All Departments'];

$csrf_token = generateCSRFToken();
$page_title = 'Event Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/alerts.css">
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
            
            <a href="settings.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span>Settings</span>
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
                <h1 class="page-title"><?php echo $page_title; ?></h1>
                <p class="page-subtitle">Manage and approve events</p>
            </div>
            
            <div class="header-right">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                        $user = Auth::user();
                        $nameParts = explode(' ', $user['full_name']);
                        $initials = strtoupper(substr($nameParts[0], 0, 1));
                        if (count($nameParts) > 1) {
                            $initials .= strtoupper(substr(end($nameParts), 0, 1));
                        }
                        echo $initials;
                        ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                        <div class="user-role">Admin</div>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="content-section">
            <div class="filters-container">
                <div class="filters-left">
                    <a href="create-event.php" class="btn btn-primary">
                        <svg style="width: 18px; height: 18px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create Event
                    </a>
                </div>
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <input type="text" name="search" placeholder="Search events..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="club" class="filter-select">
                            <option value="">All Clubs</option>
                            <?php foreach ($clubs as $club): ?>
                                <option value="<?php echo $club['id']; ?>" 
                                        <?php echo $club_filter == $club['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($club['club_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <select name="date" class="filter-select">
                            <option value="">All Dates</option>
                            <option value="upcoming" <?php echo $date_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="past" <?php echo $date_filter === 'past' ? 'selected' : ''; ?>>Past</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Filter
                    </button>
                    
                    <a href="events.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
        </div>

        <!-- Events Table -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">All Events (<?php echo count($events); ?>)</h2>
                <a href="export-events.php" class="btn btn-secondary">
                    <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export CSV
                </a>
            </div>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Date & Venue</th>
                            <th>Club/Creator</th>
                            <th>Registrations</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No events found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td>
                                        <div class="table-cell-main"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                        <div class="table-cell-sub"><?php echo htmlspecialchars(substr($event['event_description'], 0, 60)); ?>...</div>
                                    </td>
                                    <td>
                                        <div class="table-cell-main"><?php echo date('M d, Y g:i A', strtotime($event['event_date'])); ?></div>
                                        <div class="table-cell-sub"><?php echo htmlspecialchars($event['venue']); ?></div>
                                    </td>
                                    <td>
                                        <div class="table-cell-main"><?php echo htmlspecialchars($event['club_name'] ?? 'N/A'); ?></div>
                                        <div class="table-cell-sub">by <?php echo htmlspecialchars($event['creator_name']); ?></div>
                                    </td>
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
                                            <button onclick="viewEvent(<?php echo $event['id']; ?>)" class="btn-icon-small" title="View">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                            
                                            <button onclick="editEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)" class="btn-icon-small" title="Edit">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </button>
                                            
                                            <?php if ($event['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Approve this event?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn-icon-small btn-icon-success" title="Approve">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                                
                                                <button onclick="rejectEvent(<?php echo $event['id']; ?>)" class="btn-icon-small btn-icon-danger" title="Reject">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this event and all registrations?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn-icon-small btn-icon-danger" title="Delete">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Edit Event Modal -->
    <div id="editEventModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <h3>Edit Event</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="event_id" id="editEventId">
                <input type="hidden" name="action" value="edit_event">
                
                <div class="form-group">
                    <label>Event Name *</label>
                    <input type="text" name="event_name" id="editEventName" required class="filter-input">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Event Date *</label>
                        <input type="date" name="event_date" id="editEventDate" required class="filter-input">
                    </div>
                    
                    <div class="form-group">
                        <label>Event Time *</label>
                        <input type="time" name="event_time" id="editEventTime" required class="filter-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Venue *</label>
                    <input type="text" name="venue" id="editVenue" required class="filter-input">
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" id="editDescription" rows="4" required class="filter-input"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Max Participants *</label>
                        <input type="number" name="max_participants" id="editMaxParticipants" min="1" required class="filter-input">
                    </div>
                    
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="editDepartment" class="filter-select">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Registration Deadline *</label>
                    <input type="datetime-local" name="registration_deadline" id="editRegistrationDeadline" required class="filter-input">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Club</label>
                        <select name="club_id" id="editClubId" class="filter-select">
                            <option value="">No Club</option>
                            <?php foreach ($clubs as $club): ?>
                                <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['club_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="status" id="editStatus" required class="filter-select">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeEditEventModal()" class="btn btn-secondary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Reject Event</h3>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="event_id" id="rejectEventId">
                <input type="hidden" name="action" value="reject">
                
                <div class="form-group">
                    <label>Rejection Reason</label>
                    <textarea name="reason" rows="4" required class="filter-input" placeholder="Explain why this event is being rejected..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeRejectModal()" class="btn btn-secondary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Reject Event
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/admin.js?v=2"></script>
    <script src="<?php echo BASE_URL; ?>/public/js/toast.js?v=2"></script>
    <script>
        function viewEvent(eventId) {
            window.location.href = 'view-event.php?id=' + eventId;
        }
        
        function editEvent(event) {
            document.getElementById('editEventId').value = event.id;
            document.getElementById('editEventName').value = event.event_name;
            
            // Split event_date (DATETIME) into date and time parts
            const eventDateTime = new Date(event.event_date);
            const dateStr = eventDateTime.toISOString().split('T')[0];
            const timeStr = eventDateTime.toTimeString().split(' ')[0].substring(0, 5);
            
            document.getElementById('editEventDate').value = dateStr;
            document.getElementById('editEventTime').value = timeStr;
            document.getElementById('editVenue').value = event.venue;
            document.getElementById('editDescription').value = event.event_description || '';
            document.getElementById('editMaxParticipants').value = event.max_participants;
            document.getElementById('editDepartment').value = event.department || '';
            
            // Registration deadline
            if (event.registration_deadline) {
                const regDateTime = new Date(event.registration_deadline);
                const regDateTimeStr = regDateTime.getFullYear() + '-' + 
                    String(regDateTime.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(regDateTime.getDate()).padStart(2, '0') + 'T' +
                    String(regDateTime.getHours()).padStart(2, '0') + ':' +
                    String(regDateTime.getMinutes()).padStart(2, '0');
                document.getElementById('editRegistrationDeadline').value = regDateTimeStr;
            }
            
            // Club ID
            document.getElementById('editClubId').value = event.club_id || '';
            
            // Status
            document.getElementById('editStatus').value = event.status || 'pending';
            
            document.getElementById('editEventModal').style.display = 'flex';
        }
        
        function closeEditEventModal() {
            document.getElementById('editEventModal').style.display = 'none';
        }
        
        function rejectEvent(eventId) {
            document.getElementById('rejectEventId').value = eventId;
            document.getElementById('rejectModal').style.display = 'flex';
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const rejectModal = document.getElementById('rejectModal');
            const editModal = document.getElementById('editEventModal');
            if (event.target === rejectModal) {
                closeRejectModal();
            }
            if (event.target === editModal) {
                closeEditEventModal();
            }
        }
    </script>
    
    <style>
        .filters-container {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 24px;
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto auto;
            gap: 16px;
            align-items: center;
        }
        
        .filter-input, .filter-select {
            width: 100%;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }
        
        .filter-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .btn-icon-success {
            color: #10b981;
        }
        
        .btn-icon-success:hover {
            background: rgba(16, 185, 129, 0.1);
        }
        
        .btn-icon-danger {
            color: #ef4444;
        }
        
        .btn-icon-danger:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: rgba(20, 20, 40, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 24px;
            color: white;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .form-group textarea {
            width: 100%;
            resize: vertical;
        }
        
        @media (max-width: 1200px) {
            .filters-form {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
