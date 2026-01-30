<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('faculty');

$user = Auth::user();
$db = Database::getInstance()->getConnection();

$success = '';
$error = '';

// Get clubs and themes for dropdowns
$clubs_sql = "SELECT id, club_name FROM clubs WHERE is_active = 1 ORDER BY club_name";
$clubs = $db->query($clubs_sql)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = Security::sanitize($_POST['event_name'] ?? '');
    $event_description = Security::sanitize($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $venue = Security::sanitize($_POST['venue'] ?? '');
    $club_id = $_POST['club_id'] ?? null;
    $max_participants = intval($_POST['max_participants'] ?? 0);
    $registration_deadline = $_POST['registration_deadline'] ?? '';
    $department = Security::sanitize($_POST['department'] ?? '');
    
    // Combine date and time into datetime format
    $event_datetime = $event_date . ' ' . $event_time;
    
    try {
        $insert_sql = "INSERT INTO events (event_name, event_description, event_date, venue, club_id, max_participants, registration_deadline, department, created_by, status) 
                       VALUES (:event_name, :event_description, :event_date, :venue, :club_id, :max_participants, :registration_deadline, :department, :created_by, 'pending')";
        $stmt = $db->prepare($insert_sql);
        $stmt->execute([
            'event_name' => $event_name,
            'event_description' => $event_description,
            'event_date' => $event_datetime,
            'venue' => $venue,
            'club_id' => $club_id,
            'max_participants' => $max_participants,
            'registration_deadline' => $registration_deadline,
            'department' => $department,
            'created_by' => Auth::id()
        ]);
        
        $success = 'Event created successfully! Waiting for admin approval.';
    } catch (Exception $e) {
        $error = 'Failed to create event: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/dashboard.css">
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>My Events</span>
            </a>
            
            <a href="create-event.php" class="nav-item active">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Create Event</span>
            </a>
            
            <a href="registrations.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                <span>Registrations</span>
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

    <main class="main-content">
        <header class="header">
            <div class="header-left">
                <h1 class="page-title">Create Event</h1>
                <p class="page-subtitle">Create a new event</p>
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
                        <div class="user-role">Faculty</div>
                    </div>
                </div>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="glass-card">
                <form method="POST" class="form">
                    <div class="form-grid">
                        <div class="form-group col-span-2">
                            <label class="form-label">Event Name *</label>
                            <input type="text" name="event_name" class="form-input" required>
                        </div>
                        
                        <div class="form-group col-span-2">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-input" rows="4" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Event Date *</label>
                            <input type="date" name="event_date" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Event Time *</label>
                            <input type="time" name="event_time" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Venue *</label>
                            <input type="text" name="venue" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Club (Optional)</label>
                            <select name="club_id" class="form-input">
                                <option value="">No Club</option>
                                <?php foreach ($clubs as $club): ?>
                                    <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['club_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Max Participants *</label>
                            <input type="number" name="max_participants" class="form-input" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Registration Deadline *</label>
                            <input type="datetime-local" name="registration_deadline" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Event</button>
                        <a href="my-events.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js"></script>
</body>
</html>
