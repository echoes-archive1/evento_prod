<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

Auth::requireAuth();

$user = Auth::user();
$db = Database::getInstance()->getConnection();

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = Security::sanitize($_POST['full_name'] ?? '');
    $phone = Security::sanitize($_POST['phone'] ?? '');
    
    try {
        $update_sql = "UPDATE users SET full_name = :full_name, phone = :phone WHERE id = :id";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->execute([
            'full_name' => $full_name,
            'phone' => $phone,
            'id' => Auth::id()
        ]);
        
        $success = 'Profile updated successfully!';
        $_SESSION['user'] = array_merge($user, ['full_name' => $full_name, 'phone' => $phone]);
        $user = Auth::user();
    } catch (Exception $e) {
        $error = 'Failed to update profile';
    }
}

// Get user stats
$stats_sql = "
    SELECT 
        (SELECT COUNT(*) FROM event_registrations WHERE user_id = :user_id1) as total_registered,
        (SELECT COUNT(*) FROM event_registrations WHERE user_id = :user_id2 AND attendance_status = 'attended') as total_attended,
        (SELECT COUNT(*) FROM event_registrations er 
         JOIN events e ON er.event_id = e.id 
         WHERE er.user_id = :user_id3 AND e.event_date >= NOW()) as upcoming_events,
        (SELECT COUNT(*) FROM event_registrations er 
         JOIN events e ON er.event_id = e.id 
         WHERE er.user_id = :user_id4 AND e.event_date < NOW()) as past_events
";
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute([
    'user_id1' => Auth::id(), 
    'user_id2' => Auth::id(),
    'user_id3' => Auth::id(),
    'user_id4' => Auth::id()
]);
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo APP_NAME; ?></title>
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
            
            <a href="my-events.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                <span>My Events</span>
            </a>
            
            <a href="profile.php" class="nav-item active">
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
                <h1 class="page-title">My Profile</h1>
                <p class="page-subtitle">Manage your account information</p>
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
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <!-- Profile Stats -->
                <div class="glass-card">
                    <h3 class="card-title">Event Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['total_registered']; ?></div>
                                <div class="stat-label">Total Registered</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['total_attended']; ?></div>
                                <div class="stat-label">Events Attended</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['upcoming_events']; ?></div>
                                <div class="stat-label">Upcoming Events</div>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value"><?php echo $stats['past_events']; ?></div>
                                <div class="stat-label">Past Events</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Form -->
                <div class="glass-card">
                    <h3 class="card-title">Personal Information</h3>
                    <form method="POST" class="form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-input" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Year</label>
                                <select class="form-input" disabled>
                                    <option value="">Select Year</option>
                                    <option value="1" <?php echo ($user['year'] ?? '') == '1' ? 'selected' : ''; ?>>First Year</option>
                                    <option value="2" <?php echo ($user['year'] ?? '') == '2' ? 'selected' : ''; ?>>Second Year</option>
                                    <option value="3" <?php echo ($user['year'] ?? '') == '3' ? 'selected' : ''; ?>>Third Year</option>
                                    <option value="4" <?php echo ($user['year'] ?? '') == '4' ? 'selected' : ''; ?>>Fourth Year</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js"></script>
</body>
</html>
