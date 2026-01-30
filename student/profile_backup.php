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
    $whatsapp_number = Security::sanitize($_POST['whatsapp_number'] ?? '');
    $department = Security::sanitize($_POST['department'] ?? '');
    $institute = Security::sanitize($_POST['institute'] ?? '');
    $year = Security::sanitize($_POST['year'] ?? '');
    
    try {
        $update_sql = "UPDATE users SET full_name = :full_name, phone = :phone, whatsapp_number = :whatsapp_number, 
                       department = :department, institute = :institute, year = :year WHERE id = :id";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->execute([
            'full_name' => $full_name,
            'phone' => $phone,
            'whatsapp_number' => $whatsapp_number,
            'department' => $department,
            'institute' => $institute,
            'year' => $year,
            'id' => Auth::id()
        ]);
        
        $success = 'Profile updated successfully!';
        $_SESSION['user'] = array_merge($user, [
            'full_name' => $full_name, 
            'phone' => $phone, 
            'whatsapp_number' => $whatsapp_number,
            'department' => $department, 
            'institute' => $institute,
            'year' => $year
        ]);
        $user = Auth::user();
    } catch (Exception $e) {
        $error = 'Failed to update profile';
    }
}

// Get user stats
$stats_sql = "
    SELECT 
        (SELECT COUNT(*) FROM event_registrations WHERE user_id = :user_id1) as total_registered,
        (SELECT COUNT(*) FROM event_registrations WHERE user_id = :user_id2 AND attendance_status = 'attended') as total_attended
";
$stats_stmt = $db->prepare($stats_sql);
$stats_stmt->execute(['user_id1' => Auth::id(), 'user_id2' => Auth::id()]);
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
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2 class="sidebar-logo">Evento</h2>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>
            
            <a href="events.php" class="nav-item">
                <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>All Events</span>
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
        <header class="page-header">
            <div class="header-left">
                <h1 class="page-title">
                    <svg class="title-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    My Profile
                </h1>
                <p class="page-subtitle">Manage your account information and preferences</p>
            </div>
            <div class="header-right">
                <div class="profile-status">
                    <?php if (!empty($user['profile_completed']) && $user['profile_completed']): ?>
                        <span class="status-badge status-complete">
                            <svg class="status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Profile Complete
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-incomplete">
                            <svg class="status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Incomplete
                        </span>
                    <?php endif; ?>
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
                <!-- Left Column: Profile Photo & Stats -->
                <div class="profile-left-column">
                    <!-- Profile Image & Info -->
                    <div class="glass-card profile-photo-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <svg class="card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Profile Photo
                            </h3>
                            <?php if (!empty($user['auto_extracted']) && $user['auto_extracted']): ?>
                                <span class="auto-extracted-badge">
                                    <svg class="badge-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Auto-filled
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="profile-avatar-section">
                            <?php if (!empty($user['profile_image'])): ?>
                                <div class="profile-avatar">
                                    <img src="<?php echo BASE_URL; ?>/public/uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                         alt="Profile Photo" class="avatar-image">
                                    <div class="avatar-overlay">
                                        <svg class="overlay-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                        </svg>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="profile-avatar-placeholder">
                                    <svg class="avatar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <p>No Profile Photo</p>
                                    <small>Upload a photo</small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($user['roll_number'])): ?>
                                <div class="profile-info">
                                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                    <div class="info-row">
                                        <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                                        </svg>
                                        <span class="roll-number">Roll No: <?php echo htmlspecialchars($user['roll_number']); ?></span>
                                    </div>
                                    <?php if (!empty($user['department'])): ?>
                                        <div class="info-row">
                                            <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                            <span class="department"><?php echo htmlspecialchars($user['department']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($user['institute'])): ?>
                                        <div class="info-row">
                                            <svg class="info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M12 14l9-5-9-5-9 5 9 5z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path>
                                            </svg>
                                            <span class="institute"><?php echo htmlspecialchars($user['institute']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Profile Stats -->
                    <div class="glass-card stats-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <svg class="card-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                Activity Stats
                            </h3>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-item stat-events">
                                <div class="stat-icon">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $stats['total_registered']; ?></div>
                                    <div class="stat-label">Events Registered</div>
                                </div>
                            </div>
                            <div class="stat-item stat-attended">
                                <div class="stat-icon">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-value"><?php echo $stats['total_attended']; ?></div>
                                    <div class="stat-label">Events Attended</div>
                                </div>
                            </div>
                            <?php if (!empty($user['current_semester'])): ?>
                                <div class="stat-item stat-semester">
                                    <div class="stat-icon">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value"><?php echo $user['current_semester']; ?></div>
                                        <div class="stat-label">Current Semester</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Profile Form -->
                <div class="profile-right-column">
                <div class="glass-card">
                    <h3 class="card-title">Personal Information</h3>
                    <form method="POST" class="form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            
                            <?php if (!empty($user['roll_number'])): ?>
                            <div class="form-group">
                                <label class="form-label">Roll Number</label>
                                <input type="text" class="form-input" 
                                       value="<?php echo htmlspecialchars($user['roll_number']); ?>" disabled>
                                <small class="form-help">Auto-extracted from email</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">WhatsApp Number</label>
                                <input type="tel" name="whatsapp_number" class="form-input" 
                                       value="<?php echo htmlspecialchars($user['whatsapp_number'] ?? ''); ?>">
                                <small class="form-help">Leave empty if same as phone number</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Institute</label>
                                <select name="institute" class="form-input" required>
                                    <option value="">Select Institute</option>
                                    <option value="CSPIT" <?php echo ($user['institute'] ?? '') == 'CSPIT' ? 'selected' : ''; ?>>
                                        CSPIT - Charotar University of Science and Technology
                                    </option>
                                    <option value="Depstar" <?php echo ($user['institute'] ?? '') == 'Depstar' ? 'selected' : ''; ?>>
                                        Depstar - Devang Patel Institute of Advance Technology and Research
                                    </option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <select name="department" class="form-input" required>
                                    <option value="">Select Department</option>
                                    <option value="CSE" <?php echo ($user['department'] ?? '') == 'CSE' ? 'selected' : ''; ?>>
                                        Computer Science and Engineering
                                    </option>
                                    <option value="CE" <?php echo ($user['department'] ?? '') == 'CE' ? 'selected' : ''; ?>>
                                        Computer Engineering
                                    </option>
                                    <option value="IT" <?php echo ($user['department'] ?? '') == 'IT' ? 'selected' : ''; ?>>
                                        Information Technology
                                    </option>
                                    <option value="EC" <?php echo ($user['department'] ?? '') == 'EC' ? 'selected' : ''; ?>>
                                        Electronics and Communication
                                    </option>
                                    <option value="ME" <?php echo ($user['department'] ?? '') == 'ME' ? 'selected' : ''; ?>>
                                        Mechanical Engineering
                                    </option>
                                    <option value="EE" <?php echo ($user['department'] ?? '') == 'EE' ? 'selected' : ''; ?>>
                                        Electrical Engineering
                                    </option>
                                    <option value="MBA" <?php echo ($user['department'] ?? '') == 'MBA' ? 'selected' : ''; ?>>
                                        Master of Business Administration
                                    </option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Academic Year</label>
                                <select name="year" class="form-input">
                                    <option value="">Select Year</option>
                                    <option value="1" <?php echo ($user['year'] ?? '') == '1' ? 'selected' : ''; ?>>First Year</option>
                                    <option value="2" <?php echo ($user['year'] ?? '') == '2' ? 'selected' : ''; ?>>Second Year</option>
                                    <option value="3" <?php echo ($user['year'] ?? '') == '3' ? 'selected' : ''; ?>>Third Year</option>
                                    <option value="4" <?php echo ($user['year'] ?? '') == '4' ? 'selected' : ''; ?>>Fourth Year</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-update">
                                <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/public/js/dashboard.js"></script>
</body>
</html>
