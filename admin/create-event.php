<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireRole('admin');
$user = Auth::user();$db = Database::getInstance()->getConnection();
$success = '';
$error = '';

// Get clubs for dropdown
$clubs_sql = "SELECT id, club_name FROM clubs WHERE is_active = 1 ORDER BY club_name";
$clubs = $db->query($clubs_sql)->fetchAll();

$departments = ['Computer Science', 'Electronics', 'Mechanical', 'Civil', 'IT', 'All Departments'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token.';
    } else {
        $event_name = Security::sanitize($_POST['event_name'] ?? '');
        $event_description = Security::sanitize($_POST['description'] ?? '');
        $event_date = $_POST['event_date'] ?? '';
        $event_time = $_POST['event_time'] ?? '';
        $venue = Security::sanitize($_POST['venue'] ?? '');
        $club_id = $_POST['club_id'] ?? null;
        $max_participants = intval($_POST['max_participants'] ?? 0);
        $registration_deadline = $_POST['registration_deadline'] ?? '';
        $department = Security::sanitize($_POST['department'] ?? '');
        $status = $_POST['status'] ?? 'pending';
        
        // Combine date and time
        $event_datetime = $event_date . ' ' . $event_time;
        
        if (empty($event_name) || empty($event_date) || empty($venue)) {
            $error = 'Please fill all required fields.';
        } else {
            try {
                $insert_sql = "INSERT INTO events (event_name, event_description, event_date, venue, club_id, max_participants, registration_deadline, department, created_by, status, approved_by, approved_at) 
                               VALUES (:event_name, :event_description, :event_date, :venue, :club_id, :max_participants, :registration_deadline, :department, :created_by, :status, :approved_by, :approved_at)";
                $stmt = $db->prepare($insert_sql);
                
                $approved_by = ($status === 'approved') ? Auth::id() : null;
                $approved_at = ($status === 'approved') ? date('Y-m-d H:i:s') : null;
                
                $stmt->execute([
                    'event_name' => $event_name,
                    'event_description' => $event_description,
                    'event_date' => $event_datetime,
                    'venue' => $venue,
                    'club_id' => $club_id ?: null,
                    'max_participants' => $max_participants,
                    'registration_deadline' => $registration_deadline,
                    'department' => $department,
                    'created_by' => Auth::id(),
                    'status' => $status,
                    'approved_by' => $approved_by,
                    'approved_at' => $approved_at
                ]);
                
                $event_id = $db->lastInsertId();
                Security::logAudit(Auth::id(), 'event_created', 'events', $event_id);
                $success = 'Event created successfully!';
                
                // Clear form
                $_POST = [];
                
            } catch (Exception $e) {
                $error = 'Failed to create event: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/admin.css">
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

    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div class="header-left">
                <h1 class="page-title">Create New Event</h1>
                <p class="page-subtitle">Add a new event to the system</p>
            </div>
            
            <div class="header-right">
                <a href="events.php" class="btn btn-secondary" style="margin-right: 1rem;">Back to Events</a>
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
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="glass-card">
                <form method="POST" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-grid">
                        <div class="form-group col-span-2">
                            <label class="form-label">Event Name *</label>
                            <input type="text" name="event_name" class="form-input" required value="<?php echo htmlspecialchars($_POST['event_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group col-span-2">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-input" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Event Date *</label>
                            <input type="date" name="event_date" class="form-input" required value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Event Time *</label>
                            <input type="time" name="event_time" class="form-input" required value="<?php echo htmlspecialchars($_POST['event_time'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Venue *</label>
                            <input type="text" name="venue" class="form-input" required value="<?php echo htmlspecialchars($_POST['venue'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Max Participants</label>
                            <input type="number" name="max_participants" class="form-input" min="0" value="<?php echo htmlspecialchars($_POST['max_participants'] ?? ''); ?>">
                            <small class="form-hint">Leave empty for unlimited</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Club</label>
                            <select name="club_id" class="form-input">
                                <option value="">No Club</option>
                                <?php foreach ($clubs as $club): ?>
                                    <option value="<?php echo $club['id']; ?>" <?php echo (($_POST['club_id'] ?? '') == $club['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($club['club_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-input">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>" <?php echo (($_POST['department'] ?? '') === $dept) ? 'selected' : ''; ?>>
                                        <?php echo $dept; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Registration Deadline *</label>
                            <input type="datetime-local" name="registration_deadline" class="form-input" required value="<?php echo htmlspecialchars($_POST['registration_deadline'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-input" required>
                                <option value="pending" <?php echo (($_POST['status'] ?? 'pending') === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo (($_POST['status'] ?? '') === 'approved') ? 'selected' : ''; ?>>Approved</option>
                            </select>
                            <small class="form-hint">Auto-approve if set to Approved</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Event</button>
                        <a href="events.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/admin.js"></script>
</body>
</html>
